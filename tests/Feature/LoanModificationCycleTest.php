<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanModification;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Services\LoanScheduleService;
use App\Services\LoanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class LoanModificationCycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        activity()->disableLogging();
        foreach ([Loan::class, Payment::class, RepaymentTransaction::class, PaymentAllocation::class, LoanModification::class] as $model) {
            $model::unsetEventDispatcher();
        }
        Schema::dropAllTables();
        $this->createTestSchema();
    }

    public function test_reschedule_closes_only_selected_cycle_and_creates_next_customer_cycle(): void
    {
        $firstLoan = $this->createLoan(77, 1, 'QF-077-C1');
        $secondLoan = $this->createLoan(77, 2, 'QF-077-C2');
        $selectedLoan = $this->createLoan(77, 3, 'QF-077-C3', [
            'total_paid' => 500,
            'aging' => 19,
            'locked_aging' => 5,
            'accumulated_penalty' => 25,
        ]);
        $oldPayment = $this->createPayment($selectedLoan, 1000, 100);

        $newCycle = $this->serviceWithSingleInstallment(1000, '2026-10-09')->reschedule($selectedLoan, [
            'new_rate' => 5,
            'remaining_term' => 1,
            'reschedule_date' => '2026-09-09',
            'first_payment_date' => '2026-10-09',
            'repayment_method' => 'fixed_monthly',
            'pay_off_principal' => 0,
            'accrued_interest' => 0,
        ]);

        $this->assertSame('active', $firstLoan->fresh()->status);
        $this->assertSame('active', $secondLoan->fresh()->status);
        $this->assertSame('rescheduled', $selectedLoan->fresh()->status);
        $this->assertSame('QF-077-C3', $selectedLoan->fresh()->loan_code);
        $this->assertSame(4, (int) $newCycle->loan_cycle);
        $this->assertSame('QF-077-C4', $newCycle->loan_code);
        $this->assertSame($selectedLoan->id, (int) $newCycle->refinanced_from_loan_id);
        $this->assertSame('active', $newCycle->status);
        $this->assertEquals(0, $newCycle->disbursed_amount);
        $this->assertEquals(0, $newCycle->total_paid);
        $this->assertSame(0, (int) $newCycle->aging);
        $this->assertSame(0, (int) $newCycle->locked_aging);
        $this->assertEquals(0, $newCycle->accumulated_penalty);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'loan_id' => $selectedLoan->id]);
        $this->assertDatabaseHas('payments', ['loan_id' => $newCycle->id, 'principal_amount' => 1000]);
    }

    public function test_refinance_closes_only_selected_cycle_and_disburses_only_additional_cash(): void
    {
        $firstLoan = $this->createLoan(88, 1, 'QF-088-C1');
        $secondLoan = $this->createLoan(88, 2, 'QF-088-C2');
        $selectedLoan = $this->createLoan(88, 3, 'QF-088-C3');
        $oldPayment = $this->createPayment($selectedLoan, 1000, 100);

        $newCycle = $this->serviceWithSingleInstallment(1500, '2026-10-09')->refinance($selectedLoan, [
            'additional_amount' => 500,
            'new_rate' => 6,
            'new_term' => 1,
            'start_date' => '2026-10-09',
            'refinance_fee' => 0,
            'penalty_amount' => 0,
            'repayment_method' => 'fixed_monthly',
        ]);

        $this->assertSame('active', $firstLoan->fresh()->status);
        $this->assertSame('active', $secondLoan->fresh()->status);
        $this->assertSame('refinanced', $selectedLoan->fresh()->status);
        $this->assertSame(4, (int) $newCycle->loan_cycle);
        $this->assertSame('QF-088-C4', $newCycle->loan_code);
        $this->assertEquals(1500, $newCycle->amount);
        $this->assertEquals(500, $newCycle->disbursed_amount);
        $this->assertEquals(1000, $newCycle->refinanced_amount);
        $this->assertDatabaseHas('payments', ['id' => $oldPayment->id, 'loan_id' => $selectedLoan->id]);
    }

    public function test_reschedule_paydown_is_recorded_on_source_cycle_with_auditable_allocation(): void
    {
        $selectedLoan = $this->createLoan(89, 3, 'QF-089-C3');
        $oldPayment = $this->createPayment($selectedLoan, 1000, 100);

        $newCycle = $this->serviceWithSingleInstallment(900, '2026-10-09')->reschedule($selectedLoan, [
            'new_rate' => 5,
            'remaining_term' => 1,
            'reschedule_date' => '2026-09-09',
            'first_payment_date' => '2026-10-09',
            'repayment_method' => 'fixed_monthly',
            'pay_off_principal' => 100,
            'accrued_interest' => 100,
        ]);

        $transaction = RepaymentTransaction::query()->where('loan_id', $selectedLoan->id)->sole();
        $this->assertSame('Reschedule', $transaction->repayment_type);
        $this->assertEquals(100, $transaction->principal_paid);
        $this->assertEquals(100, $transaction->interest_paid);
        $this->assertEquals(0, $transaction->withdrawn_prepayment);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $oldPayment->id,
            'repayment_transaction_id' => $transaction->id,
            'principal_applied' => 100,
            'interest_applied' => 100,
        ]);
        $this->assertEquals(900, $newCycle->amount);
        $this->assertEquals(200, $oldPayment->fresh()->total_paid);
    }

    public function test_closed_cycle_cannot_be_modified_again_from_a_stale_instance(): void
    {
        $selectedLoan = $this->createLoan(99, 3, 'QF-099-C3');
        $this->createPayment($selectedLoan, 1000, 100);
        $staleLoan = $selectedLoan->fresh();
        $service = $this->serviceWithSingleInstallment(1000, '2026-10-09');

        $service->reschedule($selectedLoan, [
            'new_rate' => 5,
            'remaining_term' => 1,
            'reschedule_date' => '2026-09-09',
            'first_payment_date' => '2026-10-09',
        ]);

        try {
            $service->reschedule($staleLoan, [
                'new_rate' => 5,
                'remaining_term' => 1,
                'reschedule_date' => '2026-09-09',
                'first_payment_date' => '2026-10-09',
            ]);
            $this->fail('A closed source cycle must not create another active cycle.');
        } catch (ValidationException) {
            $this->assertSame(1, Loan::query()->where('borrower_id', 99)->where('loan_cycle', 4)->count());
        }
    }

    public function test_modification_routes_require_feature_permissions(): void
    {
        $routes = collect(app('router')->getRoutes());

        foreach ([
            'api/loan-modification/search' => 'permission:ui:reschedule_refinance:view',
            'api/loan-modification/preview' => 'permission:ui:reschedule_refinance:view',
            'api/loan-modification/reschedule' => 'permission:ui:reschedule_refinance:create',
            'api/loan-modification/refinance' => 'permission:ui:reschedule_refinance:create',
        ] as $uri => $permission) {
            $route = $routes->first(fn ($candidate): bool => $candidate->uri() === $uri);
            $this->assertNotNull($route, "Missing route {$uri}");
            $this->assertContains($permission, $route->gatherMiddleware());
        }
    }

    public function test_recalculate_schedule_does_not_allocate_partial_row_principal_twice(): void
    {
        $loan = $this->createLoan(101, 1, 'QF-101-C1', ['amount' => 1200]);
        $transaction = RepaymentTransaction::create([
            'loan_id' => $loan->id,
            'amount_paid' => 184,
            'principal_paid' => 70,
            'interest_paid' => 114,
            'penalty_paid' => 0,
            'prepayment_paid' => 0,
            'paid_off_amount' => 0,
            'recovery_amount' => 0,
            'withdrawn_prepayment' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Partial',
            'transaction_date' => '2026-08-09',
        ]);

        foreach ([
            [1, 70, 84, 154],
            [2, 30, 36, 30],
            [3, 550, 55, 0],
            [4, 550, 55, 0],
        ] as [$number, $principal, $interest, $paid]) {
            $loan->payments()->create([
                'payment_number' => $number,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'outstanding_balance' => 0,
                'penalty_amount' => 0,
                'total_paid' => $paid,
                'repayment_transaction_id' => $paid > 0 ? $transaction->id : null,
                'payment_date' => '2026-0'.($number + 4).'-09',
                'payment_method' => 'Cash',
            ]);
        }

        $loan->recalculateSchedule();

        $this->assertEquals(1200, $loan->payments()->sum('principal_amount'));
        $this->assertEquals(
            1100,
            $loan->payments()->where('payment_number', '>', 2)->sum('principal_amount')
        );
        $this->assertEquals(1200, $loan->fresh()->getBasePrincipalForOS());
    }

    private function serviceWithSingleInstallment(float $principal, string $date): LoanService
    {
        $schedule = Mockery::mock(LoanScheduleService::class);
        $schedule->shouldReceive('generate')->once()->andReturn([[
            'period' => 1,
            'date' => $date,
            'principal' => $principal,
            'interest' => 0,
            'fee' => 0,
            'balance' => 0,
        ]]);

        return new LoanService($schedule);
    }

    private function createLoan(int $borrowerId, int $cycle, string $code, array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'borrower_id' => $borrowerId,
            'loan_code' => $code,
            'loan_cycle' => $cycle,
            'amount' => 1000,
            'disbursed_amount' => 1000,
            'total_paid' => 0,
            'interest_rate' => 5,
            'duration_months' => 1,
            'monthly_payment' => 1100,
            'monthly_interest' => 50,
            'start_date' => '2026-09-09',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee' => 0,
            'admin_fee_type' => 'one_time',
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ], $overrides));
    }

    private function createPayment(Loan $loan, float $principal, float $interest): Payment
    {
        return $loan->payments()->create([
            'payment_number' => 1,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'outstanding_balance' => 0,
            'penalty_amount' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-10-09',
            'payment_method' => 'Cash',
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('borrower_id')->nullable();
            $table->unsignedBigInteger('co_borrower_id')->nullable();
            $table->unsignedBigInteger('guarantor_id')->nullable();
            $table->unsignedBigInteger('loan_officer_id')->nullable();
            $table->unsignedBigInteger('disbursed_by_officer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('payment_qr_id')->nullable();
            $table->unsignedBigInteger('refinanced_from_loan_id')->nullable();
            $table->string('loan_code');
            $table->integer('loan_cycle')->default(1);
            $table->decimal('amount', 15, 2);
            $table->decimal('disbursed_amount', 15, 2)->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
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
            $table->string('purpose')->nullable();
            $table->string('sector')->nullable();
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->string('admin_fee_type')->default('one_time');
            $table->decimal('penalty_rate', 15, 2)->default(0);
            $table->decimal('refinance_fee', 15, 2)->default(0);
            $table->decimal('refinanced_amount', 15, 2)->default(0);
            $table->decimal('reschedule_fee', 15, 2)->default(0);
            $table->timestamp('rescheduled_at')->nullable();
            $table->integer('aging')->default(0);
            $table->integer('locked_aging')->default(0);
            $table->decimal('accumulated_penalty', 15, 2)->default(0);
            $table->date('late_since_date')->nullable();
            $table->date('penalty_late_since_date')->nullable();
            $table->date('written_off_at')->nullable();
            $table->string('write_off_reason')->nullable();
            $table->string('classify_wo')->nullable();
            $table->decimal('write_off_balance', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('prepayment', 15, 2)->default(0);
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->date('payment_date');
            $table->string('payment_method')->default('Cash');
            $table->date('settled_at')->nullable();
            $table->date('settled_due_date')->nullable();
            $table->integer('settled_days_variance')->nullable();
            $table->string('settlement_source', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('collector_id')->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('waived_amount', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('prepayment_paid', 15, 2)->default(0);
            $table->decimal('paid_off_amount', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->decimal('withdrawn_prepayment', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('repayment_type');
            $table->date('transaction_date');
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

        Schema::create('loan_modifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('type');
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('collaterals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }
}
