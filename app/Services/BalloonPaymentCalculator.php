<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalloonPaymentCalculator
{
    /**
     * Calculate Interest-Only Balloon Payment Schedule
     * Monthly payments = Interest only
     * Final payment = Principal + Interest
     * 
     * @param float $principal Loan amount
     * @param float $annualRate Annual interest rate (e.g., 12 for 12%)
     * @param int $durationMonths Loan duration in months
     * @param string $startDate Loan start date (Y-m-d)
     * @return array Payment schedule
     */
    public static function calculateInterestOnlySchedule(
        float $principal,
        float $annualRate,
        int $durationMonths,
        string $startDate,
        float $adminFee = 0,
        string $adminFeeType = 'one_time'
    ): array {
        $schedule = [];
        $monthlyInterestRate = $annualRate / 100; // Treat as Monthly Rate (standard for this app)
        $monthlyInterest = round($principal * $monthlyInterestRate, 2);

        $startDateObj = Carbon::parse($startDate);

        for ($month = 1; $month <= $durationMonths; $month++) {
            $paymentDate = $startDateObj->copy()->addMonths($month);

            $isFinalPayment = ($month === $durationMonths);

            $totalFeeAmount = $principal * ($adminFee / 100);
            $feeAmount = 0;
            if ($adminFee > 0) {
                if ($adminFeeType === 'deducted_upfront' || $adminFeeType === 'capitalized_upfront') {
                    $feeAmount = 0;
                } elseif ($adminFeeType === 'one_time') {
                    $feeAmount = ($month === 1) ? round($totalFeeAmount, 2) : 0;
                } else {
                    $feeAmount = round($totalFeeAmount / $durationMonths, 2);
                }
            }

            $schedule[] = [
                'payment_number' => $month,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'principal_amount' => $isFinalPayment ? $principal : 0,
                'interest_amount' => $monthlyInterest,
                'fee_amount' => $feeAmount,
                'penalty_amount' => 0,
                'total_paid' => $isFinalPayment ? ($principal + $monthlyInterest + $feeAmount) : ($monthlyInterest + $feeAmount),
                'is_balloon' => $isFinalPayment,
                'remaining_balance' => $isFinalPayment ? 0 : $principal,
            ];
        }

        return $schedule;
    }

    /**
     * Calculate Minimal Payment Balloon Schedule
     * Monthly payments = Small custom amount (covers interest + minimal principal)
     * Final payment = Remaining principal + interest
     * 
     * @param float $principal Loan amount
     * @param float $annualRate Annual interest rate (e.g., 12 for 12%)
     * @param int $durationMonths Loan duration in months
     * @param string $startDate Loan start date (Y-m-d)
     * @param float $monthlyPayment Custom monthly payment amount (optional, defaults to 110% of interest)
     * @return array Payment schedule
     */
    public static function calculateMinimalPaymentSchedule(
        float $principal,
        float $annualRate,
        int $durationMonths,
        string $startDate,
        ?float $monthlyPayment = null,
        float $adminFee = 0,
        string $adminFeeType = 'one_time'
    ): array {
        $schedule = [];
        $monthlyInterestRate = $annualRate / 100; // Treat as Monthly Rate
        $monthlyInterest = round($principal * $monthlyInterestRate, 2);

        // Default monthly payment = 110% of interest (covers interest + small principal)
        if ($monthlyPayment === null) {
            $monthlyPayment = round($monthlyInterest * 1.1, 2);
        }

        $remainingPrincipal = $principal;
        $startDateObj = Carbon::parse($startDate);

        for ($month = 1; $month <= $durationMonths; $month++) {
            $paymentDate = $startDateObj->copy()->addMonths($month);

            // Recalculate interest on remaining principal
            $interest = round($remainingPrincipal * $monthlyInterestRate, 2);

            $isFinalPayment = ($month === $durationMonths);

            if ($isFinalPayment) {
                // Final balloon payment: all remaining principal + interest
                $principalPortion = $remainingPrincipal;
                $totalPayment = $remainingPrincipal + $interest;
            } else {
                // Regular payment: interest + small principal
                $principalPortion = max(0, $monthlyPayment - $interest);
                $totalPayment = $monthlyPayment;
                $remainingPrincipal -= $principalPortion;
            }

            $totalFeeAmount = $principal * ($adminFee / 100);
            $feeAmount = 0;
            if ($adminFee > 0) {
                if ($adminFeeType === 'deducted_upfront' || $adminFeeType === 'capitalized_upfront') {
                    $feeAmount = 0;
                } elseif ($adminFeeType === 'one_time') {
                    $feeAmount = ($month === 1) ? round($totalFeeAmount, 2) : 0;
                } else {
                    $feeAmount = round($totalFeeAmount / $durationMonths, 2);
                }
            }

            $schedule[] = [
                'payment_number' => $month,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'principal_amount' => round($principalPortion, 2),
                'interest_amount' => $interest,
                'fee_amount' => $feeAmount,
                'penalty_amount' => 0,
                'total_paid' => round($totalPayment + $feeAmount, 2),
                'is_balloon' => $isFinalPayment,
                'remaining_balance' => $isFinalPayment ? 0 : round($remainingPrincipal, 2),
            ];
        }

        return $schedule;
    }

    /**
     * Generate payment schedule based on loan details
     * Auto-detects which calculation method to use
     * 
     * @param array $loanData Loan data with keys: amount, interest_rate, duration_months, start_date
     * @param string $balloonType 'interest_only' or 'minimal_payment'
     * @param float|null $customMonthlyPayment Optional for minimal_payment type
     * @return array Payment schedule
     */
    public static function generateSchedule(
        array $loanData,
        string $balloonType = 'interest_only',
        ?float $customMonthlyPayment = null,
        float $adminFee = 0,
        string $adminFeeType = 'one_time'
    ): array {
        $principal = $loanData['amount'];
        $annualRate = $loanData['interest_rate'];
        $durationMonths = $loanData['duration_months'];
        $startDate = $loanData['start_date'];

        if ($balloonType === 'minimal_payment') {
            return self::calculateMinimalPaymentSchedule(
                $principal,
                $annualRate,
                $durationMonths,
                $startDate,
                $customMonthlyPayment,
                $adminFee,
                $adminFeeType
            );
        }

        // Default: interest_only
        return self::calculateInterestOnlySchedule(
            $principal,
            $annualRate,
            $durationMonths,
            $startDate,
            $adminFee,
            $adminFeeType
        );
    }

    /**
     * Save payment schedule to database
     * 
     * @param int $loanId Loan ID
     * @param array $schedule Payment schedule array
     * @return bool Success status
     */
    public static function saveScheduleToDatabase(int $loanId, array $schedule): bool
    {
        try {
            DB::beginTransaction();

            // Delete existing payment schedule for this loan
            DB::table('payments')->where('loan_id', $loanId)->delete();

            // Insert new schedule
            foreach ($schedule as $payment) {
                DB::table('payments')->insert([
                    'loan_id' => $loanId,
                    'payment_number' => $payment['payment_number'],
                    'payment_date' => $payment['payment_date'],
                    'principal_amount' => $payment['principal_amount'],
                    'interest_amount' => $payment['interest_amount'],
                    'penalty_amount' => $payment['penalty_amount'] ?? 0,
                    'total_paid' => $payment['total_paid'],
                    'payment_method' => 'Cash', // Default
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save payment schedule: " . $e->getMessage());
            return false;
        }
    }
}
