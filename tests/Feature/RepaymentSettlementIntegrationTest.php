<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Services\LoanScheduleService;
use App\Services\RepaymentService;
use App\Services\RepaymentPreviewService;
use App\Services\RepaymentTransactionEditService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepaymentSettlementIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Loan::class, Payment::class, PaymentAllocation::class, RepaymentTransaction::class] as $model) {
            $model::unsetEventDispatcher();
        }
        activity()->disableLogging();
        Schema::dropAllTables();
        $this->createTestSchema();
        Carbon::setTestNow('2026-08-11 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_normal_repayment_snapshots_the_settled_row_and_resets_current_aging(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'SETTLEMENT-INTEGRATION',
            'amount' => 100,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 1,
            'monthly_payment' => 110,
            'start_date' => '2026-07-10',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 0,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);
        $payment = Payment::query()->create([
            'loan_id' => $loan->id,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 10,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Cash',
        ]);

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-08-11',
        ]);

        $payment->refresh();
        $loan->refresh();

        $this->assertSame('2026-08-11', $payment->settled_at);
        $this->assertSame('2026-08-10', $payment->settled_due_date);
        $this->assertSame(-1, (int) $payment->settled_days_variance);
        $this->assertSame('allocation', $payment->settlement_source);
        $this->assertSame(0, $loan->currentAging());
        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);
    }

    public function test_historical_on_time_entries_use_transaction_date_instead_of_system_date(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'HISTORICAL-AS-OF-DATE',
            'amount' => 200,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 2,
            'monthly_payment' => 110,
            'start_date' => '2026-05-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 2.5,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);

        foreach ([1 => '2026-06-01', 2 => '2026-07-01'] as $number => $date) {
            Payment::query()->create([
                'loan_id' => $loan->id,
                'payment_number' => $number,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $date,
                'payment_method' => 'Cash',
            ]);
        }

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-06-01',
        ]);

        $preview = app(RepaymentPreviewService::class)->build(
            $loan->fresh(),
            Carbon::parse('2026-07-01'),
            'Normal'
        );

        $this->assertEquals(0, $preview['penalty_due']);
        $this->assertSame(0, $preview['aging']);
        $this->assertSame('2026-07-01', $preview['transaction_date']);

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-07-01',
        ]);

        $loan->refresh();
        $this->assertSame('completed', $loan->status);
        $this->assertEquals(0, $loan->currentPenaltyDue());
        $this->assertSame(0, $loan->currentAging());
    }

    public function test_historical_entries_must_be_posted_oldest_to_newest(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'HISTORICAL-ORDER',
            'amount' => 200,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 2,
            'monthly_payment' => 110,
            'start_date' => '2026-05-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 0,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);
        foreach ([1 => '2026-06-01', 2 => '2026-07-01'] as $number => $date) {
            Payment::query()->create([
                'loan_id' => $loan->id,
                'payment_number' => $number,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $date,
                'payment_method' => 'Cash',
            ]);
        }

        $service = app(RepaymentService::class);
        $service->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-07-01',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('oldest date first');
        $service->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-06-01',
        ]);
    }

    public function test_loan_level_preview_aging_moves_with_the_filtered_date_across_multiple_rows(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'FILTERED-LOAN-AGING',
            'amount' => 200,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 2,
            'monthly_payment' => 110,
            'start_date' => '2026-05-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 2.5,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);

        foreach ([1 => '2026-06-01', 2 => '2026-06-05'] as $number => $date) {
            Payment::query()->create([
                'loan_id' => $loan->id,
                'payment_number' => $number,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $date,
                'payment_method' => 'Cash',
            ]);
        }

        $service = app(RepaymentPreviewService::class);
        $earlier = $service->build($loan, Carbon::parse('2026-06-08'), 'Normal');
        $later = $service->build($loan, Carbon::parse('2026-06-12'), 'Normal');

        $this->assertSame(7, $earlier['aging']);
        $this->assertSame(11, $later['aging']);
        $this->assertEquals(17.50, $earlier['penalty_due']);
        $this->assertEquals(27.50, $later['penalty_due']);
        $this->assertLessThan($later['aging'], $earlier['aging']);
    }

    public function test_aging_is_locked_until_the_remaining_penalty_is_paid(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'PENALTY-LOCK-INTEGRATION',
            'amount' => 100,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 1,
            'monthly_payment' => 110,
            'start_date' => '2026-07-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 2.5,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);
        Payment::query()->create([
            'loan_id' => $loan->id,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 10,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-08-01',
            'payment_method' => 'Cash',
        ]);

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 110,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 10,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-08-11',
        ]);

        $loan->refresh();

        $this->assertSame('active', $loan->status);
        $this->assertSame(10, (int) $loan->aging);
        $this->assertSame(10, (int) $loan->locked_aging);
        $this->assertEquals(15, $loan->accumulated_penalty);
        $this->assertEquals(15, $loan->currentPenaltyDue());
        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 0,
            'waived_amount' => 10,
            'fee_amount' => 0,
            'penalty_amount' => 5,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-08-11',
        ]);

        $loan->refresh();

        $this->assertSame('completed', $loan->status);
        $this->assertSame(0, (int) $loan->aging);
        $this->assertSame(0, (int) $loan->locked_aging);
        $this->assertEquals(0, $loan->accumulated_penalty);
        $this->assertEquals(0, $loan->currentPenaltyDue());
        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);
    }

    public function test_backend_schedule_values_are_persisted_exactly_to_payments(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'SCHEDULE-PARITY',
            'amount' => 184,
            'total_paid' => 0,
            'interest_rate' => 3.7,
            'duration_months' => 2,
            'monthly_payment' => 0,
            'start_date' => '2026-08-01',
            'status' => 'pending_check',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 0,
        ]);

        $service = app(LoanScheduleService::class);
        $backendSchedule = $service->normalize([
            [
                'period' => 1,
                'date' => '11/09/2026',
                'principal' => 91.67,
                'interest' => 135.55,
                'fee' => 0,
                'payment' => 227.22,
                'balance' => 91.67,
            ],
            [
                'period' => 2,
                'date' => '11/10/2026',
                'principal' => 91.67,
                'interest' => 135.55,
                'fee' => 0,
                'payment' => 227.22,
                'balance' => 0,
            ],
        ], 'USD', 184);

        $payments = $service->persist($loan, $backendSchedule);

        foreach ($backendSchedule as $index => $row) {
            $payment = $payments[$index];
            $this->assertSame($row['period'], (int) $payment->payment_number);
            $this->assertEquals($row['principal'], (float) $payment->principal_amount);
            $this->assertEquals($row['interest'], (float) $payment->interest_amount);
            $this->assertEquals($row['fee'], (float) $payment->fee_amount);
            $this->assertEquals($row['balance'], (float) $payment->outstanding_balance);
            $this->assertEquals(
                $row['payment'],
                round(
                    (float) $payment->principal_amount
                    + (float) $payment->interest_amount
                    + (float) $payment->fee_amount,
                    2
                )
            );
        }

        $loan->refresh();
        $this->assertEquals(228, (float) $loan->monthly_payment);
        $this->assertSame('2026-10-11', $loan->maturity_date);
    }

    public function test_partial_repayments_never_rewrite_future_schedule_values(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'PARTIAL-SCHEDULE-IMMUTABLE',
            'amount' => 300,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 3,
            'monthly_payment' => 110,
            'start_date' => '2026-07-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 0,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);

        foreach ([
            [1, 100, 10, '2026-08-10'],
            // Deliberately represent an already-inflated production schedule.
            [2, 110, 10, '2026-09-10'],
            [3, 110, 10, '2026-10-10'],
        ] as [$number, $principal, $interest, $date]) {
            Payment::query()->create([
                'loan_id' => $loan->id,
                'payment_number' => $number,
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $date,
                'payment_method' => 'Cash',
            ]);
        }

        $futureBefore = Payment::query()
            ->where('loan_id', $loan->id)
            ->whereIn('payment_number', [2, 3])
            ->orderBy('payment_number')
            ->get(['payment_number', 'principal_amount', 'interest_amount'])
            ->toArray();

        foreach ([50, 60] as $amount) {
            app(RepaymentService::class)->process([
                'loan_id' => $loan->id,
                'collector_id' => null,
                'amount_paid' => $amount,
                'waived_amount' => 0,
                'fee_amount' => 0,
                'penalty_amount' => 0,
                'payment_method' => 'Cash',
                'repayment_type' => 'Partial',
                'transaction_date' => '2026-08-11',
            ]);
        }

        $futureAfter = Payment::query()
            ->where('loan_id', $loan->id)
            ->whereIn('payment_number', [2, 3])
            ->orderBy('payment_number')
            ->get(['payment_number', 'principal_amount', 'interest_amount'])
            ->toArray();

        $this->assertSame($futureBefore, $futureAfter);
        $this->assertEquals(110, (float) Payment::query()
            ->where('loan_id', $loan->id)
            ->where('payment_number', 1)
            ->value('total_paid'));
    }

    public function test_latest_partial_repayment_can_be_edited_and_safely_reprocessed(): void
    {
        $loan = Loan::query()->create([
            'loan_code' => 'EDIT-REPAYMENT',
            'amount' => 100,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 1,
            'monthly_payment' => 110,
            'start_date' => '2026-07-10',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'penalty_rate' => 0,
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
        ]);
        $payment = Payment::query()->create([
            'loan_id' => $loan->id,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 10,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Cash',
        ]);

        $original = app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 50,
            'waived_amount' => 0,
            'fee_amount' => 0,
            'penalty_amount' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Partial',
            'transaction_date' => '2026-08-11',
        ])['transaction'];

        $replacement = app(RepaymentTransactionEditService::class)->update($original, [
            'loan_id' => $loan->id,
            'collector_id' => null,
            'amount_paid' => 70,
            'principal_paid' => 60,
            'interest_paid' => 10,
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Bank Transfer',
            'repayment_type' => 'Partial',
            'transaction_date' => '2026-08-12',
        ]);

        $this->assertNotSame($original->id, $replacement->id);
        $this->assertSoftDeleted('repayment_transactions', ['id' => $original->id]);
        $this->assertSame('2026-08-12', $replacement->transaction_date);
        $this->assertSame('Bank Transfer', $replacement->payment_method);
        $this->assertEquals(70, (float) $replacement->amount_paid);
        $this->assertEquals(60, (float) $replacement->principal_paid);
        $this->assertEquals(10, (float) $replacement->interest_paid);
        $this->assertEquals(70, (float) $payment->fresh()->total_paid);
        $this->assertDatabaseMissing('payment_allocations', [
            'repayment_transaction_id' => $original->id,
        ]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'repayment_transaction_id' => $replacement->id,
            'amount_applied' => 70,
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->string('loan_code');
            $table->decimal('amount', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->integer('duration_months')->default(1);
            $table->decimal('monthly_payment', 15, 2)->default(0);
            $table->date('start_date');
            $table->date('maturity_date')->nullable();
            $table->string('status');
            $table->string('currency')->default('USD');
            $table->string('repayment_method');
            $table->string('payment_frequency');
            $table->decimal('admin_fee', 8, 4)->default(0);
            $table->string('admin_fee_type')->default('one_time');
            $table->decimal('penalty_rate', 15, 2)->nullable();
            $table->integer('aging')->default(0);
            $table->integer('locked_aging')->default(0);
            $table->decimal('accumulated_penalty', 15, 2)->default(0);
            $table->date('late_since_date')->nullable();
            $table->date('penalty_late_since_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('prepayment', 15, 2)->default(0);
            $table->date('payment_date');
            $table->string('payment_method')->default('Cash');
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
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

        Schema::create('revenue_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('revenues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('revenue_category_id')->nullable();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency')->nullable();
            $table->date('transaction_date');
            $table->string('reference_no')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
