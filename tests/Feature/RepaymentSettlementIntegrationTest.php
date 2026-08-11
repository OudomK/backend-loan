<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Services\RepaymentService;
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
    }
}
