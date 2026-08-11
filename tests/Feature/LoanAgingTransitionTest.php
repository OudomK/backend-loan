<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanAgingTransitionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loan::unsetEventDispatcher();
        Payment::unsetEventDispatcher();
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

    public function test_loan_aging_stays_continuous_when_the_oldest_overdue_row_is_paid(): void
    {
        $loan = $this->createLoan([
            'late_since_date' => '2026-05-03',
            'penalty_late_since_date' => '2026-05-03',
            'aging' => 100,
            'locked_aging' => 100,
            'penalty_rate' => 2.5,
        ]);

        $this->createPayment($loan, 1, '2026-05-03', 110);
        $this->createPayment($loan, 2, '2026-06-02', 0);

        $loan->updateAging();
        $loan->refresh();

        $this->assertSame('2026-06-02', $loan->late_since_date);
        $this->assertSame('2026-05-03', $loan->penalty_late_since_date);
        $this->assertSame(100, (int) $loan->aging);
        $this->assertSame(100, $loan->currentAging());
        $this->assertSame(0, (int) $loan->locked_aging);
        $this->assertEquals(250, $loan->currentPenaltyDue());
    }

    public function test_aging_and_penalty_are_frozen_when_rows_are_paid_but_penalty_remains(): void
    {
        $loan = $this->createLoan([
            'late_since_date' => '2026-05-03',
            'penalty_late_since_date' => '2026-05-03',
            'aging' => 100,
            'locked_aging' => 100,
        ]);
        $this->createPayment($loan, 1, '2026-05-03', 110);

        $loan->updateAging();
        $loan->refresh();

        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);
        $this->assertSame(100, (int) $loan->aging);
        $this->assertSame(100, (int) $loan->locked_aging);
        $this->assertSame(100, $loan->currentAging());
        $this->assertEquals(250, $loan->accumulated_penalty);
        $this->assertEquals(250, $loan->currentPenaltyDue());
    }

    public function test_aging_resets_when_rows_and_penalty_are_fully_cleared(): void
    {
        $loan = $this->createLoan([
            'late_since_date' => '2026-05-03',
            'penalty_late_since_date' => '2026-05-03',
            'aging' => 100,
            'locked_aging' => 100,
        ]);
        $this->createPayment($loan, 1, '2026-05-03', 110);
        RepaymentTransaction::query()->create([
            'loan_id' => $loan->id,
            'penalty_paid' => 250,
            'waived_amount' => 0,
            'transaction_date' => '2026-08-11',
        ]);

        $loan->updateAging();
        $loan->refresh();

        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);
        $this->assertSame(0, (int) $loan->aging);
        $this->assertSame(0, (int) $loan->locked_aging);
        $this->assertSame(0, $loan->currentAging());
        $this->assertEquals(0, $loan->accumulated_penalty);
        $this->assertEquals(0, $loan->currentPenaltyDue());
    }

    public function test_frozen_aging_and_penalty_do_not_grow_without_overdue_rows(): void
    {
        $loan = $this->createLoan([
            'aging' => 20,
            'locked_aging' => 20,
            'accumulated_penalty' => 20,
        ]);
        $this->createPayment($loan, 1, '2026-07-22', 110);

        Carbon::setTestNow('2026-08-21 12:00:00');
        $loan->updateAging();
        $loan->refresh();

        $this->assertSame(20, (int) $loan->aging);
        $this->assertSame(20, (int) $loan->locked_aging);
        $this->assertEquals(20, $loan->accumulated_penalty);
        $this->assertEquals(20, $loan->currentPenaltyDue());
        $this->assertNull($loan->late_since_date);
        $this->assertNull($loan->penalty_late_since_date);
    }

    public function test_new_late_period_starts_from_the_new_row_after_the_old_period_is_cleared(): void
    {
        $loan = $this->createLoan();
        $this->createPayment($loan, 1, '2026-05-03', 110);
        $this->createPayment($loan, 2, '2026-08-06', 0);

        $loan->updateAging();
        $loan->refresh();

        $this->assertSame('2026-08-06', $loan->late_since_date);
        $this->assertSame('2026-08-06', $loan->penalty_late_since_date);
        $this->assertSame(5, (int) $loan->aging);
        $this->assertSame(5, $loan->currentAging());
        $this->assertSame(0, (int) $loan->locked_aging);
        $this->assertEquals(12.5, $loan->currentPenaltyDue());
    }

    public function test_report_aging_uses_the_reference_date_and_keeps_due_today_at_zero(): void
    {
        $loan = $this->createLoan();

        $this->assertSame(
            70,
            $loan->agingAt(Carbon::parse('2026-08-11'), '2026-06-02', true)
        );
        $this->assertSame(
            0,
            $loan->agingAt(Carbon::parse('2026-08-11'), '2026-08-11', true)
        );
    }

    private function createLoan(array $overrides = []): Loan
    {
        return Loan::query()->create(array_merge([
            'loan_code' => 'AGING-TEST',
            'amount' => 1000,
            'total_paid' => 0,
            'interest_rate' => 1,
            'duration_months' => 12,
            'monthly_payment' => 100,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'payment_frequency' => 'monthly',
            'admin_fee_type' => 'one_time',
            'aging' => 0,
            'locked_aging' => 0,
            'accumulated_penalty' => 0,
            'late_since_date' => null,
            'penalty_late_since_date' => null,
            'penalty_rate' => 2.5,
        ], $overrides));
    }

    private function createPayment(
        Loan $loan,
        int $number,
        string $paymentDate,
        float $totalPaid
    ): Payment {
        return Payment::query()->create([
            'loan_id' => $loan->id,
            'payment_number' => $number,
            'principal_amount' => 100,
            'interest_amount' => 10,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => $totalPaid,
            'payment_date' => $paymentDate,
            'payment_method' => 'Cash',
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
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
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
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('waived_amount', 15, 2)->default(0);
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
