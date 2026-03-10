<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Payment;
use App\Services\BalloonPaymentCalculator;
use App\Services\LoanCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateLoanSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *  php artisan loan:generate-schedule        // all loans without payments
     *  php artisan loan:generate-schedule 3      // only loan id = 3
     */
    protected $signature = 'loan:generate-schedule {loan_id? : Optional, generate for a single loan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate repayment schedules into the payments table from existing loans';

    /**
     * Execute the console command.
     */
    public function handle(LoanCalculator $calculator): int
    {
        $loanId = $this->argument('loan_id');

        $query = Loan::query()
            ->whereNotNull('amount')
            ->whereNotNull('interest_rate')
            ->whereNotNull('duration_months')
            ->whereNotNull('repayment_method')
            ->whereNotNull('start_date');

        if ($loanId) {
            $query->where('id', $loanId);
        }

        // Only loans that do not yet have any payments
        $loans = $query->whereDoesntHave('payments')->get();

        if ($loans->isEmpty()) {
            $this->info('No loans found that need schedules.');
            return Command::SUCCESS;
        }

        foreach ($loans as $loan) {
            $this->info("Generating schedule for Loan ID {$loan->id} ({$loan->loan_code})");

            try {
                if ($loan->repayment_method === 'negotiable') {
                    $this->warn("Skipping negotiable loan {$loan->id} – requires custom schedule.");
                    continue;
                }

                if ($loan->repayment_method === 'Balloon') {
                    $loanData = [
                        'amount' => $loan->amount,
                        'interest_rate' => $loan->interest_rate,
                        'duration_months' => $loan->duration_months,
                        'start_date' => $loan->start_date,
                    ];

                    $schedule = BalloonPaymentCalculator::generateSchedule(
                        $loanData,
                        'interest_only'
                    );

                    if (empty($schedule)) {
                        $this->warn("No schedule generated for loan {$loan->id}");
                        continue;
                    }

                    // Reference monthly payment from first row
                    $loan->update([
                        'monthly_payment' => $schedule[0]['total_paid'] ?? 0,
                    ]);

                    foreach ($schedule as $row) {
                        $paymentDate = $this->normalizeDateString($row['payment_date'] ?? null);

                        Payment::create([
                            'loan_id' => $loan->id,
                            'payment_number' => $row['payment_number'],
                            'principal_amount' => $row['principal_amount'],
                            'interest_amount' => $row['interest_amount'],
                            'penalty_amount' => $row['penalty_amount'] ?? 0,
                            'total_paid' => 0,
                            'payment_date' => $paymentDate,
                            'payment_method' => 'Cash',
                        ]);
                    }
                } else {
                    $schedule = $calculator->calculateLoanWithDates(
                        $loan->amount,
                        $loan->interest_rate,
                        $loan->duration_months,
                        $loan->repayment_method,
                        $loan->start_date,
                        $loan->currency ?? 'USD'
                    );

                    if (empty($schedule)) {
                        $this->warn("No schedule generated for loan {$loan->id}");
                        continue;
                    }

                    // Reference monthly payment from first row
                    $loan->update([
                        'monthly_payment' => $schedule[0]['payment'] ?? 0,
                    ]);

                    foreach ($schedule as $row) {
                        $paymentDate = $this->normalizeDateString($row['date'] ?? null);

                        Payment::create([
                            'loan_id' => $loan->id,
                            'payment_number' => $row['period'],
                            'principal_amount' => $row['principal'],
                            'interest_amount' => $row['interest'],
                            'penalty_amount' => 0,
                            'total_paid' => 0,
                            'payment_date' => $paymentDate,
                            'payment_method' => 'Cash',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Error for loan {$loan->id}: " . $e->getMessage());
                Log::error('GenerateLoanSchedule error', [
                    'loan_id' => $loan->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Done generating schedules.');
        return Command::SUCCESS;
    }

    /**
     * Normalize schedule date string to Y-m-d for MySQL DATE column.
     */
    private function normalizeDateString(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Already Y-m-d?
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // d/m/Y (e.g. 26/01/2026)
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $dt = \DateTime::createFromFormat('d/m/Y', $value);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // Fallback: let DateTime try to parse anything else
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

