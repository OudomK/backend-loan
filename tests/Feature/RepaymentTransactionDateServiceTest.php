<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Models\Revenue;
use App\Services\PaymentSettlementTimingService;
use App\Services\RepaymentTransactionDateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepaymentTransactionDateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([Loan::class, Payment::class, PaymentAllocation::class, RepaymentTransaction::class, Revenue::class] as $model) {
            $model::unsetEventDispatcher();
        }

        Schema::dropAllTables();
        $this->createTestSchema();
    }

    public function test_updating_a_transaction_date_keeps_linked_financial_dates_in_sync(): void
    {
        DB::table('loans')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transaction = RepaymentTransaction::query()->create([
            'loan_id' => 1,
            'amount_paid' => 100,
            'principal_paid' => 80,
            'interest_paid' => 20,
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => '2026-08-12',
        ]);

        $allocatedPayment = $this->createPayment();
        $directLegacyPayment = $this->createPayment($transaction->id);

        PaymentAllocation::query()->create([
            'payment_id' => $allocatedPayment->id,
            'repayment_transaction_id' => $transaction->id,
            'amount_applied' => 100,
            'fee_applied' => 0,
            'interest_applied' => 20,
            'principal_applied' => 80,
            'penalty_applied' => 0,
        ]);

        DB::table('revenues')->insert([
            'repayment_transaction_id' => $transaction->id,
            'amount' => 20,
            'transaction_date' => '2026-08-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settlementService = app(PaymentSettlementTimingService::class);
        $settlementService->sync($allocatedPayment);
        $settlementService->sync($directLegacyPayment);

        $updated = app(RepaymentTransactionDateService::class)->update(
            $transaction,
            '2026-08-05',
        );

        $this->assertSame('2026-08-05', $updated->transaction_date);
        $this->assertEquals(100.0, $updated->amount_paid);
        $this->assertSame(
            '2026-08-05',
            Revenue::query()->firstOrFail()->transaction_date->toDateString(),
        );

        foreach ([$allocatedPayment, $directLegacyPayment] as $payment) {
            $payment->refresh();

            $this->assertSame('2026-08-05', $payment->settled_at);
            $this->assertSame(5, $payment->settled_days_variance);
        }
    }

    private function createPayment(?int $repaymentTransactionId = null): Payment
    {
        return Payment::query()->create([
            'loan_id' => 1,
            'payment_number' => 1,
            'principal_amount' => 80,
            'interest_amount' => 20,
            'fee_amount' => 0,
            'fee_paid' => 0,
            'penalty_amount' => 0,
            'total_paid' => 100,
            'payment_date' => '2026-08-10',
            'payment_method' => 'Cash',
            'repayment_transaction_id' => $repaymentTransactionId,
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
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

        Schema::create('revenues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
