<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RepaymentTransaction;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RepaymentTransactionDateService
{
    public function __construct(
        private readonly PaymentSettlementTimingService $settlementTimingService
    ) {}

    public function update(RepaymentTransaction|int $transaction, string $transactionDate): RepaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $transactionDate): RepaymentTransaction {
            $transactionId = $transaction instanceof RepaymentTransaction
                ? $transaction->getKey()
                : $transaction;

            $transaction = RepaymentTransaction::query()
                ->whereKey($transactionId)
                ->lockForUpdate()
                ->firstOrFail();

            // Repayment processing locks the loan, so taking the same lock keeps
            // the transaction date and settlement snapshots in sync with it.
            Loan::query()
                ->whereKey($transaction->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedDate = Carbon::parse($transactionDate)->toDateString();
            $paymentIds = PaymentAllocation::query()
                ->where('repayment_transaction_id', $transaction->id)
                ->pluck('payment_id')
                ->merge(
                    Payment::query()
                        ->where('repayment_transaction_id', $transaction->id)
                        ->pluck('id')
                )
                ->unique()
                ->values();

            $transaction->update([
                'transaction_date' => $normalizedDate,
            ]);

            Revenue::query()
                ->where('repayment_transaction_id', $transaction->id)
                ->get()
                ->each(fn (Revenue $revenue) => $revenue->update([
                    'transaction_date' => $normalizedDate,
                ]));

            Payment::query()
                ->whereIn('id', $paymentIds)
                ->get()
                ->each(fn (Payment $payment) => $this->settlementTimingService->sync($payment));

            return $transaction->fresh();
        });
    }
}
