<?php

namespace App\Support;

class FormatHelper
{
    /**
     * Format the payment method into an abbreviated or readable string.
     *
     * @param string|null $method
     * @return string
     */
    public static function formatPaymentMethod(?string $method): string
    {
        if (!$method) {
            return '-';
        }

        $abbreviations = [
            'fixed_monthly' => 'Flat Monthly',
            'fixed_weekly' => 'Weekly',
            'fixed_daily' => 'Daily',
            'linear' => 'Linear',
            'annuity_monthly' => 'Annuity',
            'balloon' => 'Balloon',
            'negotiable' => 'Negotiable',
        ];

        $normalizedMethod = strtolower(trim($method));

        if (isset($abbreviations[$normalizedMethod])) {
            return $abbreviations[$normalizedMethod];
        }

        // Fallback for any other methods
        $methodStr = str_replace('_monthly', '', $method);
        $methodStr = str_replace('_', ' ', $methodStr);
        return ucwords(trim($methodStr));
    }

    /**
     * Format the loan code, substituting long suffixes with abbreviations.
     *
     * @param string|null $loanCode
     * @return string|null
     */
    public static function formatLoanCode(?string $loanCode): ?string
    {
        if (!$loanCode) {
            return $loanCode;
        }
        $loanCode = str_replace(['Rescheduled', 'Reschedule'], 'RS', $loanCode);
        $loanCode = str_replace(['Refinanced', 'Refinance'], 'RF', $loanCode);
        return $loanCode;
    }

    /**
     * Format the phone number with spaces.
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function formatPhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return $phone;
        }

        // Remove any existing spaces
        $cleaned = str_replace(' ', '', $phone);

        if (strlen($cleaned) === 9) {
            return substr($cleaned, 0, 3) . ' ' . substr($cleaned, 3, 3) . ' ' . substr($cleaned, 6);
        } elseif (strlen($cleaned) === 10) {
            return substr($cleaned, 0, 3) . ' ' . substr($cleaned, 3, 3) . ' ' . substr($cleaned, 6);
        }

        return $phone;
    }

    /**
     * Calculate monthly interest rate from per-period interest rate and payment frequency.
     *
     * Interest Rate stored in the system is the rate per payment period (per tenor).
     * This method converts it to a monthly rate by multiplying by the number of
     * payment periods in one month.
     *
     * Examples:
     *   Bi-weekly (5%) → 5% × 2 = 10% monthly
     *   Weekly    (2%) → 2% × 4 = 8%  monthly
     *   Daily  (0.53%) → 0.53% × 30 = 15.9% monthly
     *   Monthly   (7%) → 7% × 1 = 7%  monthly
     *
     * @param float $interestRate  The per-period interest rate
     * @param string|null $paymentFrequency  The payment frequency / tenor
     * @return float  The monthly interest rate
     */
    public static function calculateMonthlyRate(float $interestRate, ?string $paymentFrequency): float
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        $multiplier = match ($normalized) {
            'weekly' => 4,
            'daily' => 30,
            default => 1, // monthly, biweekly, bi-weekly, semi-monthly, installments, term, etc.
        };

        return $interestRate * $multiplier;
    }

    public static function effectivePaymentFrequency(?string $paymentFrequency, ?string $repaymentMethod): string
    {
        return trim((string) $paymentFrequency);
    }
}
