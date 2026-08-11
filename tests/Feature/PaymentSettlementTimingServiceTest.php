<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Services\PaymentSettlementTimingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSettlementTimingServiceTest extends TestCase
{
    private PaymentSettlementTimingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Payment::class, PaymentAllocation::class, RepaymentTransaction::class] as $model) {
            $model::unsetEventDispatcher();
        }

        Schema::dropAllTables();
        $this->createTestSchema();
        $this->service = app(PaymentSettlementTimingService::class);
    }

    public function test_partial_allocations_record_the_date_the_installment_became_fully_paid(): void
    {
        $payment = $this->createPayment(totalPaid: 100);
        $earlyTransaction = $this->createTransaction('2026-08-05');
        $lateTransaction = $this->createTransaction('2026-08-13');

        $this->createAllocation($payment, $earlyTransaction, 50);
        $this->createAllocation($payment, $lateTransaction, 50);

        $result = $this->service->resolve($payment);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame('2026-08-13', $result['settled_at']);
        $this->assertSame('2026-08-10', $result['settled_due_date']);
        $this->assertSame(-3, $result['settled_days_variance']);
        $this->assertSame('allocation', $result['settlement_source']);
    }

    public function test_direct_legacy_transaction_records_an_early_payment(): void
    {
        $transaction = $this->createTransaction('2026-08-05');
        $payment = $this->createPayment(
            totalPaid: 100,
            repaymentTransactionId: $transaction->id
        );

        $result = $this->service->resolve($payment);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(5, $result['settled_days_variance']);
        $this->assertSame('transaction', $result['settlement_source']);
    }

    public function test_payoff_settles_a_row_even_when_future_interest_was_not_paid(): void
    {
        $transaction = $this->createTransaction('2026-08-12', 'Pay Off');
        $payment = $this->createPayment(
            totalPaid: 80,
            repaymentTransactionId: $transaction->id
        );

        $result = $this->service->resolve($payment);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(-2, $result['settled_days_variance']);
        $this->assertSame('payoff', $result['settlement_source']);
    }

    public function test_unpaid_rows_remain_dynamic_and_are_not_snapshotted(): void
    {
        $payment = $this->createPayment(totalPaid: 50);

        $result = $this->service->resolve($payment);

        $this->assertSame('unsettled', $result['status']);
    }

    public function test_updated_at_fallback_is_explicit_and_opt_in(): void
    {
        $payment = $this->createPayment(totalPaid: 100);
        DB::table('payments')->where('id', $payment->id)->update([
            'updated_at' => '2026-08-14 12:00:00',
        ]);
        $payment->refresh();

        $this->assertSame('unresolved', $this->service->resolve($payment)['status']);

        $result = $this->service->resolve($payment, allowUpdatedAtFallback: true);

        $this->assertSame('resolved', $result['status']);
        $this->assertSame(-4, $result['settled_days_variance']);
        $this->assertSame('legacy_updated_at', $result['settlement_source']);
    }

    public function test_sync_clears_a_snapshot_when_the_row_is_no_longer_settled(): void
    {
        $payment = $this->createPayment(totalPaid: 50);
        $payment->forceFill([
            'settled_at' => '2026-08-10',
            'settled_due_date' => '2026-08-10',
            'settled_days_variance' => 0,
            'settlement_source' => 'transaction',
        ])->saveQuietly();

        $status = $this->service->sync($payment->fresh());

        $this->assertSame('unsettled', $status);
        $this->assertNull($payment->fresh()->settled_at);
        $this->assertNull($payment->fresh()->settled_days_variance);
    }

    private function createPayment(
        float $totalPaid,
        ?int $repaymentTransactionId = null
    ): Payment {
        return Payment::query()->create([
            'loan_id' => 1,
            'payment_number' => 1,
            'principal_amount' => 80,
            'interest_amount' => 20,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => $totalPaid,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Cash',
            'repayment_transaction_id' => $repaymentTransactionId,
        ]);
    }

    private function createTransaction(
        string $transactionDate,
        string $repaymentType = 'Normal'
    ): RepaymentTransaction {
        return RepaymentTransaction::query()->create([
            'loan_id' => 1,
            'amount_paid' => 100,
            'principal_paid' => 80,
            'interest_paid' => 20,
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => $repaymentType,
            'transaction_date' => $transactionDate,
        ]);
    }

    private function createAllocation(
        Payment $payment,
        RepaymentTransaction $transaction,
        float $principalApplied
    ): PaymentAllocation {
        return PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'repayment_transaction_id' => $transaction->id,
            'amount_applied' => $principalApplied,
            'fee_applied' => 0,
            'interest_applied' => 0,
            'principal_applied' => $principalApplied,
            'penalty_applied' => 0,
        ]);
    }

    private function createTestSchema(): void
    {
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
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->string('payment_method')->default('Cash');
            $table->string('repayment_type')->default('Normal');
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
    }
}
