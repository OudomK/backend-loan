<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanController;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EditLoanScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loan::unsetEventDispatcher();
        Payment::unsetEventDispatcher();
        activity()->disableLogging();
        Schema::dropAllTables();
        $this->createTestSchema();
    }

    public function test_balance_cannot_be_edited_directly(): void
    {
        $loan = $this->createLoan('EDIT-OS-ONLY');
        $payment = $this->createPayment($loan, [
            'principal_amount' => 100,
            'interest_amount' => 20,
            'fee_amount' => 5,
            'outstanding_balance' => 900,
            'payment_date' => '2026-09-11',
        ]);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Correct old schedule',
                'payments' => [[
                    'id' => $payment->id,
                    'outstanding_balance' => 875,
                ]],
            ]);
            $this->fail('Balance must be calculated by the backend.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $payment->refresh();
        $this->assertEquals(900, $payment->outstanding_balance);
        $this->assertEquals(100, $payment->principal_amount);
        $this->assertEquals(20, $payment->interest_amount);
        $this->assertEquals(5, $payment->fee_amount);
        $this->assertSame('2026-09-11', $payment->payment_date);
    }

    public function test_schedule_update_rejects_negative_amounts(): void
    {
        $loan = $this->createLoan('EDIT-NEGATIVE');
        $payment = $this->createPayment($loan);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Correct old schedule',
                'payments' => [[
                    'id' => $payment->id,
                    'principal_amount' => -1,
                ]],
            ]);
            $this->fail('Negative schedule amounts must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertEquals(100, $payment->fresh()->principal_amount);
    }

    public function test_schedule_update_rejects_a_payment_from_another_loan(): void
    {
        $loan = $this->createLoan('EDIT-OWNER');
        $otherLoan = $this->createLoan('EDIT-OTHER');
        $otherPayment = $this->createPayment($otherLoan);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Correct old schedule',
                'payments' => [[
                    'id' => $otherPayment->id,
                    'principal_amount' => 1,
                ]],
            ]);
            $this->fail('A schedule row from another loan must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertEquals(900, $otherPayment->fresh()->outstanding_balance);
    }

    public function test_closed_modification_cycle_schedule_is_read_only(): void
    {
        $loan = $this->createLoan('EDIT-CLOSED-CYCLE');
        $loan->update(['status' => 'rescheduled']);
        $payment = $this->createPayment($loan);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Correct old schedule',
                'payments' => [[
                    'id' => $payment->id,
                    'principal_amount' => 50,
                ]],
            ]);
            $this->fail('A closed rescheduled/refinanced cycle must be read-only.');
        } catch (ValidationException) {
            $this->assertEquals(100, $payment->fresh()->principal_amount);
        }
    }

    public function test_actual_os_base_ignores_editable_schedule_balances(): void
    {
        $loan = $this->createLoan('OS-SOURCE');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
            'outstanding_balance' => 123,
        ]);
        $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'outstanding_balance' => 0,
        ]);

        $this->assertEquals(1000, $loan->fresh()->getBasePrincipalForOS());

        $first->update(['outstanding_balance' => 999]);

        $this->assertEquals(1000, $loan->fresh()->getBasePrincipalForOS());
    }

    public function test_manual_edit_changes_only_the_requested_column(): void
    {
        $loan = $this->createLoan('EDIT-PRINCIPAL-SOURCE');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
            'outstanding_balance' => 600,
        ]);
        $second = $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'outstanding_balance' => 0,
            'payment_date' => '2026-10-11',
        ]);

        $response = $this->updateSchedule($loan, [
            'reason' => 'Correct one principal cell',
            'payments' => [[
                'id' => $first->id,
                'principal_amount' => 450,
            ]],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $loan->refresh();
        $this->assertEquals(1000, $loan->amount);
        $this->assertEquals(1000, $loan->getBasePrincipalForOS());
        $this->assertEquals(1000, $loan->disbursed_amount);
        $this->assertEquals(500, $loan->monthly_payment);
        $this->assertEquals(450, $first->fresh()->principal_amount);
        $this->assertEquals(20, $first->fresh()->interest_amount);
        $this->assertEquals(600, $first->fresh()->outstanding_balance);
        $this->assertSame('2026-09-11', $first->fresh()->payment_date);
        $this->assertEquals(600, $second->fresh()->principal_amount);
        $this->assertEquals(0, $second->fresh()->outstanding_balance);
    }

    public function test_usd_preview_rounds_changed_cents_up_and_does_not_write(): void
    {
        $loan = $this->createLoan('EDIT-PREVIEW');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
            'interest_amount' => 20,
            'outstanding_balance' => 600,
        ]);
        $second = $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'interest_amount' => 20,
            'outstanding_balance' => 0,
            'payment_date' => '2026-10-11',
        ]);

        $response = $this->previewSchedule($loan, [
            'payments' => [
                ['id' => $first->id, 'principal_amount' => 399.01, 'interest_amount' => 20.01],
                ['id' => $second->id, 'principal_amount' => 600],
            ],
        ]);

        $payload = $response->getData(true);
        $this->assertSame(400.0, (float) $payload['schedule'][0]['principal_amount']);
        $this->assertSame(21.0, (float) $payload['schedule'][0]['interest_amount']);
        $this->assertEquals(20, $first->fresh()->interest_amount);
        $this->assertEquals(400, $first->fresh()->principal_amount);
    }

    public function test_installment_with_partial_payment_is_read_only(): void
    {
        $loan = $this->createLoan('EDIT-PARTIAL-LOCKED');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
            'total_paid' => 10,
            'outstanding_balance' => 600,
        ]);
        $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'payment_date' => '2026-10-11',
            'outstanding_balance' => 0,
        ]);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Try changing paid row',
                'payments' => [[
                    'id' => $first->id,
                    'interest_amount' => 25,
                ]],
            ]);
            $this->fail('A partially paid installment must be read-only.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('read-only', $exception->getMessage());
        }

        $this->assertEquals(20, $first->fresh()->interest_amount);
    }

    public function test_manual_principal_edit_does_not_change_the_loan_contract_amount(): void
    {
        $loan = $this->createLoan('EDIT-PRINCIPAL-TOTAL');
        $first = $this->createPayment($loan, ['principal_amount' => 400]);
        $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'payment_date' => '2026-10-11',
        ]);

        $response = $this->updateSchedule($loan, [
            'reason' => 'Correct one principal cell only',
            'payments' => [[
                'id' => $first->id,
                'principal_amount' => 500,
            ]],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(1000, $loan->fresh()->amount);
        $this->assertEquals(500, $first->fresh()->principal_amount);
    }

    public function test_manual_edit_rejects_duplicate_payment_ids(): void
    {
        $loan = $this->createLoan('EDIT-DUPLICATE-ID');
        $first = $this->createPayment($loan, ['principal_amount' => 1000]);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Duplicate request row',
                'payments' => [
                    ['id' => $first->id, 'interest_amount' => 21],
                    ['id' => $first->id, 'interest_amount' => 22],
                ],
            ]);
            $this->fail('Duplicate payment IDs must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertEquals(20, $first->fresh()->interest_amount);
    }

    public function test_first_payment_date_cannot_be_before_loan_start_date(): void
    {
        $loan = $this->createLoan('EDIT-BEFORE-START');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
            'outstanding_balance' => 600,
        ]);
        $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
            'payment_date' => '2026-10-11',
            'outstanding_balance' => 0,
        ]);

        try {
            $this->updateSchedule($loan, [
                'reason' => 'Invalid first date',
                'payments' => [[
                    'id' => $first->id,
                    'payment_date' => '2026-08-01',
                ]],
            ]);
            $this->fail('First payment date before loan start must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('start date', $exception->getMessage());
        }

        $this->assertSame('2026-09-11', $first->fresh()->payment_date);
    }

    public function test_schedule_route_requires_customer_history_edit_permission(): void
    {
        $route = collect(app('router')->getRoutes())->first(function ($route): bool {
            return $route->uri() === 'api/loans/{id}/schedule'
                && in_array('PUT', $route->methods(), true);
        });

        $this->assertNotNull($route);
        $this->assertContains(
            'permission:ui:customer_history:edit',
            $route->gatherMiddleware()
        );

        $previewRoute = collect(app('router')->getRoutes())->first(function ($route): bool {
            return $route->uri() === 'api/loans/{id}/schedule/preview'
                && in_array('POST', $route->methods(), true);
        });
        $this->assertNotNull($previewRoute);
        $this->assertContains(
            'permission:ui:customer_history:edit',
            $previewRoute->gatherMiddleware()
        );
    }

    private function createLoan(string $code): Loan
    {
        return Loan::create([
            'loan_code' => $code,
            'amount' => 1000,
            'disbursed_amount' => 1000,
            'interest_rate' => 12,
            'duration_months' => 2,
            'monthly_payment' => 500,
            'start_date' => '2026-08-09',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
        ]);
    }

    private function createPayment(Loan $loan, array $overrides = []): Payment
    {
        return $loan->payments()->create(array_merge([
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 20,
            'fee_amount' => 0,
            'outstanding_balance' => 900,
            'penalty_amount' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-09-11',
            'payment_method' => 'Cash',
        ], $overrides));
    }

    private function updateSchedule(Loan $loan, array $payload)
    {
        return app(LoanController::class)->updateSchedule(
            Request::create('/', 'PUT', $payload),
            $loan->id
        );
    }

    private function previewSchedule(Loan $loan, array $payload)
    {
        return app(LoanController::class)->previewScheduleUpdate(
            Request::create('/', 'POST', $payload),
            $loan->id
        );
    }

    private function createTestSchema(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->string('loan_code');
            $table->decimal('amount', 15, 2);
            $table->decimal('disbursed_amount', 15, 2)->nullable();
            $table->decimal('interest_rate', 8, 2)->default(0);
            $table->decimal('monthly_interest', 15, 2)->default(0);
            $table->integer('duration_months')->default(1);
            $table->decimal('monthly_payment', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('status')->default('active');
            $table->string('currency')->default('USD');
            $table->string('repayment_method')->nullable();
            $table->string('payment_frequency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('prepayment', 15, 2)->default(0);
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->date('payment_date');
            $table->string('payment_method')->default('Cash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('prepayment_paid', 15, 2)->default(0);
            $table->decimal('paid_off_amount', 15, 2)->default(0);
            $table->decimal('withdrawn_prepayment', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('repayment_transaction_id');
            $table->decimal('amount_applied', 15, 2)->default(0);
            $table->decimal('fee_applied', 15, 2)->default(0);
            $table->decimal('interest_applied', 15, 2)->default(0);
            $table->decimal('principal_applied', 15, 2)->default(0);
            $table->decimal('penalty_applied', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
