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
            'fixed_15days_70_30' => 'Flat 70-30',
            'fixed_15days_50_50' => 'Flat 50-50',
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
}
