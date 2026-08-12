<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\RepaymentTransaction;
use App\Models\Revenue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepaymentTransactionEditService
{
    private const AUDITED_FIELDS = [
        'loan_id',
        'collector_id',
        'amount_paid',
        'principal_paid',
        'interest_paid',
        'penalty_paid',
        'fee_paid',
        'payment_method',
        'repayment_type',
        'transaction_date',
    ];

    public function __construct(
        private readonly RepaymentService $repaymentService,
        private readonly RepaymentTransactionDateService $dateService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RepaymentTransaction $transaction, array $data): RepaymentTransaction
    {
        $transaction = $transaction->fresh();

        if (! $this->hasFinancialChanges($transaction, $data)) {
            return $this->updateMetadata($transaction, $data);
        }

        return DB::transaction(function () use ($transaction, $data): RepaymentTransaction {
            $transaction = RepaymentTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Loan::query()
                ->whereKey($transaction->loan_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->repayment_type === 'Pay Off') {
                throw new \RuntimeException(
                    'Pay Off financial details cannot be edited because its future schedule rows no longer exist. You may still edit its date, credit officer, and payment method.'
                );
            }

            $latestTransactionId = RepaymentTransaction::query()
                ->where('loan_id', $transaction->loan_id)
                ->latest('id')
                ->value('id');

            if ((int) $latestTransactionId !== (int) $transaction->id) {
                throw new \RuntimeException(
                    'Only the latest repayment on a loan can have its loan, type, or amounts edited. Later repayments depend on this transaction.'
                );
            }

            $oldAttributes = Arr::only($transaction->getAttributes(), self::AUDITED_FIELDS);
            $oldTransactionId = $transaction->id;

            $this->repaymentService->void($transaction);

            $result = $this->repaymentService->process([
                'loan_id' => $data['loan_id'],
                'collector_id' => $data['collector_id'],
                'amount_paid' => $data['amount_paid'],
                'waived_amount' => $transaction->waived_amount ?? 0,
                'fee_amount' => $data['fee_paid'] ?? 0,
                'penalty_amount' => $data['penalty_paid'] ?? 0,
                'payment_method' => $data['payment_method'],
                'repayment_type' => $data['repayment_type'],
                'transaction_date' => $data['transaction_date'],
            ]);

            $replacement = $result['transaction'];
            $activity = activity('repayment_transactions')
                ->performedOn($replacement)
                ->event('updated')
                ->withProperties([
                    'old' => $oldAttributes,
                    'attributes' => Arr::only($replacement->getAttributes(), self::AUDITED_FIELDS),
                    'replaced_transaction_id' => $oldTransactionId,
                    'replacement_transaction_id' => $replacement->id,
                ]);

            if ($causer = auth()->user()) {
                $activity->causedBy($causer);
            }

            $activity->log('Repayment transaction edited and reprocessed');

            return $replacement->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateMetadata(RepaymentTransaction $transaction, array $data): RepaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $data): RepaymentTransaction {
            $transaction = $this->dateService->update(
                $transaction,
                (string) $data['transaction_date'],
            );

            $transaction->update([
                'collector_id' => $data['collector_id'],
                'payment_method' => $data['payment_method'],
            ]);

            Revenue::query()
                ->where('repayment_transaction_id', $transaction->id)
                ->get()
                ->each(fn (Revenue $revenue) => $revenue->update([
                    'payment_method' => $data['payment_method'],
                ]));

            return $transaction->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasFinancialChanges(RepaymentTransaction $transaction, array $data): bool
    {
        return (int) $data['loan_id'] !== (int) $transaction->loan_id
            || (string) $data['repayment_type'] !== (string) $transaction->repayment_type
            || $this->moneyChanged($data['amount_paid'], $transaction->amount_paid)
            || $this->moneyChanged($data['principal_paid'], $transaction->principal_paid)
            || $this->moneyChanged($data['interest_paid'], $transaction->interest_paid)
            || $this->moneyChanged($data['penalty_paid'] ?? 0, $transaction->penalty_paid)
            || $this->moneyChanged($data['fee_paid'] ?? 0, $transaction->fee_paid);
    }

    private function moneyChanged(mixed $newValue, mixed $oldValue): bool
    {
        return abs(round((float) $newValue, 2) - round((float) $oldValue, 2)) > 0.001;
    }
}
