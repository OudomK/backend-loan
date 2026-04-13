<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Borrower;
use App\Models\Loan;
use App\Services\BalloonPaymentCalculator;
use App\Services\LoanCalculator;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['borrower_id'])) {
            $cycle = Loan::where('borrower_id', $data['borrower_id'])->count() + 1;
            $data['loan_cycle'] = $cycle;

            if (empty($data['loan_code'])) {
                $data['loan_code'] = $this->buildLoanCode((int) $data['borrower_id'], $cycle);
            }
        }

        if (!empty($data['loan_officer_id']) && empty($data['disbursed_by_officer_id'])) {
            $data['disbursed_by_officer_id'] = $data['loan_officer_id'];
        }

        $data['admin_fee'] = (float) ($data['admin_fee'] ?? 0);
        $data['admin_fee_type'] = $data['admin_fee_type'] ?? 'one_time';

        if (isset($data['amount'], $data['interest_rate']) && $data['amount'] !== '' && $data['interest_rate'] !== '') {
            $data['monthly_interest'] = round(((float) $data['amount'] * (float) $data['interest_rate']) / 100, 2);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function afterCreate(): void
    {
        if (!$this->record instanceof Loan) {
            return;
        }

        if (!$this->canGenerateSchedule($this->record)) {
            return;
        }

        if ($this->record->repayment_method === 'negotiable') {
            Notification::make()
                ->title('Loan created without schedule')
                ->body('Negotiable repayment requires a custom schedule.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->generateSchedule($this->record);
        } catch (\Throwable $e) {
            Log::error('Filament loan schedule generation failed', [
                'loan_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Loan created, but schedule generation failed')
                ->body('Please verify repayment method and financial terms.')
                ->warning()
                ->send();
        }
    }

    private function canGenerateSchedule(Loan $loan): bool
    {
        return !empty($loan->amount)
            && !empty($loan->interest_rate)
            && !empty($loan->duration_months)
            && !empty($loan->repayment_method)
            && !empty($loan->start_date);
    }

    private function generateSchedule(Loan $loan): void
    {
        if ($loan->repayment_method === 'Balloon') {
            $loanData = [
                'amount' => (float) $loan->amount,
                'interest_rate' => (float) $loan->interest_rate,
                'duration_months' => (int) $loan->duration_months,
                'start_date' => (string) $loan->start_date,
            ];

            $schedule = BalloonPaymentCalculator::generateSchedule(
                $loanData,
                'interest_only',
                null,
                (float) ($loan->admin_fee ?? 0),
                (string) ($loan->admin_fee_type ?: 'one_time')
            );

            if (empty($schedule)) {
                return;
            }

            $loan->update([
                'monthly_payment' => (float) ($schedule[0]['total_paid'] ?? 0),
            ]);

            foreach ($schedule as $payment) {
                $paymentDate = $this->normalizeScheduleDate((string) ($payment['payment_date'] ?? ''));
                if ($paymentDate === null) {
                    continue;
                }

                $loan->payments()->create([
                    'payment_number' => (int) ($payment['payment_number'] ?? 0),
                    'principal_amount' => (float) ($payment['principal_amount'] ?? 0),
                    'interest_amount' => (float) ($payment['interest_amount'] ?? 0),
                    'fee_amount' => (float) ($payment['fee_amount'] ?? 0),
                    'total_due' => round(
                        ((float) ($payment['principal_amount'] ?? 0))
                            + ((float) ($payment['interest_amount'] ?? 0))
                            + ((float) ($payment['fee_amount'] ?? 0)),
                        2
                    ),
                    'penalty_amount' => (float) ($payment['penalty_amount'] ?? 0),
                    'total_paid' => (float) ($payment['total_paid'] ?? 0),
                    'payment_date' => $paymentDate,
                    'payment_method' => 'Cash',
                ]);
            }
        } else {
            /** @var LoanCalculator $calculator */
            $calculator = app(LoanCalculator::class);

            $schedule = $calculator->calculateLoanWithDates(
                (float) $loan->amount,
                (float) $loan->interest_rate,
                (int) $loan->duration_months,
                (string) $loan->repayment_method,
                (string) $loan->start_date,
                (string) ($loan->currency ?? 'USD'),
                (float) ($loan->admin_fee ?? 0),
                (string) ($loan->admin_fee_type ?: 'one_time')
            );

            if (empty($schedule)) {
                return;
            }

            $loan->update([
                'monthly_payment' => (float) ($schedule[0]['payment'] ?? 0),
            ]);

            foreach ($schedule as $item) {
                $paymentDate = $this->normalizeScheduleDate((string) ($item['date'] ?? ''));
                if ($paymentDate === null) {
                    continue;
                }

                $loan->payments()->create([
                    'payment_number' => (int) ($item['period'] ?? 0),
                    'principal_amount' => (float) ($item['principal'] ?? 0),
                    'interest_amount' => (float) ($item['interest'] ?? 0),
                    'fee_amount' => (float) ($item['fee'] ?? 0),
                    'total_due' => round(
                        ((float) ($item['principal'] ?? 0))
                            + ((float) ($item['interest'] ?? 0))
                            + ((float) ($item['fee'] ?? 0)),
                        2
                    ),
                    'penalty_amount' => 0,
                    'total_paid' => 0,
                    'payment_date' => $paymentDate,
                    'payment_method' => 'Cash',
                ]);
            }
        }

        $lastPaymentDate = $loan->payments()->max('payment_date');
        if (!empty($lastPaymentDate) && empty($loan->maturity_date)) {
            $loan->update(['maturity_date' => $lastPaymentDate]);
        }
    }

    private function normalizeScheduleDate(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $date)) {
            $parsed = Carbon::createFromFormat('d/m/Y', $date);
            return $parsed ? $parsed->format('Y-m-d') : null;
        }

        if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
            return $date;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildLoanCode(int $borrowerId, int $cycle): string
    {
        $borrower = Borrower::withoutGlobalScopes()->find($borrowerId);
        $customerCode = trim((string) ($borrower?->customer_code ?? ''));

        if ($customerCode === '') {
            $customerCode = 'L' . str_pad((string) $borrowerId, 3, '0', STR_PAD_LEFT);
        }

        return $customerCode . '-C' . $cycle;
    }
}
