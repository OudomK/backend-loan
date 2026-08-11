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

    public function test_balance_only_update_does_not_modify_other_schedule_fields(): void
    {
        $loan = $this->createLoan('EDIT-OS-ONLY');
        $payment = $this->createPayment($loan, [
            'principal_amount' => 100,
            'interest_amount' => 20,
            'fee_amount' => 5,
            'outstanding_balance' => 900,
            'payment_date' => '2026-09-11',
        ]);

        $response = $this->updateSchedule($loan, [
            'payments' => [[
                'id' => $payment->id,
                'outstanding_balance' => 875,
            ]],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $payment->refresh();
        $this->assertEquals(875, $payment->outstanding_balance);
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
                'payments' => [[
                    'id' => $otherPayment->id,
                    'outstanding_balance' => 1,
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

    public function test_principal_edit_synchronizes_the_contract_principal_used_by_os(): void
    {
        $loan = $this->createLoan('EDIT-PRINCIPAL-SOURCE');
        $first = $this->createPayment($loan, [
            'payment_number' => 1,
            'principal_amount' => 400,
        ]);
        $this->createPayment($loan, [
            'payment_number' => 2,
            'principal_amount' => 600,
        ]);

        $response = $this->updateSchedule($loan, [
            'payments' => [[
                'id' => $first->id,
                'principal_amount' => 500,
            ]],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $loan->refresh();
        $this->assertEquals(1100, $loan->amount);
        $this->assertEquals(1100, $loan->getBasePrincipalForOS());
        $this->assertEquals(1000, $loan->disbursed_amount);
        $this->assertEquals(132, $loan->monthly_interest);
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
    }
}
