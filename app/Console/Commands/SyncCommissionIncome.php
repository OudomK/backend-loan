<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\MiscellaneousTransaction;
use App\Services\CommissionIncomeService;
use Illuminate\Console\Command;

class SyncCommissionIncome extends Command
{
    protected $signature = 'commission:sync {loan_code? : Optional loan code to limit sync to one loan}';

    protected $description = 'Backfill loan_id on commission transactions and remove duplicate commission income records.';

    public function handle(CommissionIncomeService $commissionIncomeService): int
    {
        $loanCode = trim((string) ($this->argument('loan_code') ?? ''));
        $syncedLoanIds = [];
        $backfilled = 0;

        $query = MiscellaneousTransaction::query()
            ->where('type', 'revenue')
            ->where('category', 'Commission Income');

        if ($loanCode !== '') {
            $query->where('description', 'like', '%' . $loanCode . '%');
        }

        $transactions = $query->get();

        foreach ($transactions as $transaction) {
            if ($transaction->loan_id) {
                $syncedLoanIds[] = (int) $transaction->loan_id;
                continue;
            }

            $description = (string) ($transaction->description ?? '');
            if (!preg_match('/loan\s+([A-Za-z0-9\-]+)/i', $description, $matches)) {
                $this->warn("Skipped transaction {$transaction->id}: cannot parse loan code from description.");
                continue;
            }

            $matchedLoan = Loan::query()->where('loan_code', $matches[1])->first();
            if (!$matchedLoan) {
                $this->warn("Skipped transaction {$transaction->id}: loan {$matches[1]} not found.");
                continue;
            }

            $transaction->update(['loan_id' => $matchedLoan->id]);
            $syncedLoanIds[] = (int) $matchedLoan->id;
            $backfilled++;
        }

        $loanIds = array_values(array_unique(array_filter($syncedLoanIds)));

        if ($loanCode !== '' && empty($loanIds)) {
            $matchedLoan = Loan::query()->where('loan_code', $loanCode)->first();
            if ($matchedLoan) {
                $loanIds[] = (int) $matchedLoan->id;
            }
        }

        foreach ($loanIds as $loanId) {
            $loan = Loan::query()->find($loanId);
            if ($loan) {
                $commissionIncomeService->syncForLoan($loan);
                $this->info("Synced commission for loan {$loan->loan_code}.");
            }
        }

        $this->info("Backfilled {$backfilled} transaction(s).");
        $this->info('Done.');

        return self::SUCCESS;
    }
}
