<?php

namespace App\Services;

use App\Support\CurrencyRounding;
use DateTime;
use DateInterval;

class LoanCalculator
{
    public function calculateLoanWithDates(float $principal, float $rate, int $duration, string $option, string $startDate, string $currency, float $adminFee = 0, string $adminFeeType = 'one_time', ?int $payDay1 = null, ?int $payDay2 = null, ?string $firstRepaymentDate = null)
    {
        $results = [];
        $startDateObj = new DateTime($startDate);
        $currentDate = clone $startDateObj;
        $periodCounter = 1;
        $remainingBalance = $principal;
        $startDay = (int) $currentDate->format('d');
        $adjustMonth = ($startDay > 11) ? 1 : 0;
        $currentDate->add(new DateInterval('P' . $adjustMonth . 'M'));

        // Interest always rounds upward. Principal keeps the original schedule
        // allocation so KHR split schedules do not display hundred-riel tails.
        $applyRounding = fn ($amount, $currency) => CurrencyRounding::standard((float) $amount, (string) $currency);
        $roundInterest = fn ($amount, $currency) => CurrencyRounding::up((float) $amount, (string) $currency);
        $exactAmount = fn ($amount) => round((float) $amount, 2);
        $roundCumulativePrincipal = fn ($amount, $currency) => CurrencyRounding::cumulativePrincipal((float) $amount, (string) $currency);
        $isKhrCurrency = stripos($currency, 'KHR') !== false;
        $isUsdCurrency = stripos($currency, 'USD') !== false;
        $roundMonthlyPrincipal = fn ($amount) => $applyRounding($amount, $currency);
        $calculatePeriodFee = function ($periodNumber, $totalPayments) use ($principal, $adminFee, $adminFeeType, $applyRounding, $currency) {
            if ($adminFee <= 0)
                return 0;
            // Upfront fee types are recognized when the loan is created, not during repayment.
            if ($adminFeeType !== 'monthly') {
                return 0;
            }
            $totalFeeAmount = $principal * ($adminFee / 100);
            return $applyRounding($totalFeeAmount / $totalPayments, $currency);
        };

        $buildFixedIntervalSchedule = function (
            int $intervalDays,
            ?int $totalPaymentsOverride = null,
            bool $ratePerPayment = false,
            bool $normalizeFinalPayment = false,
            bool $startNextDay = false
        ) use ($principal, $rate, $duration, $startDateObj, $applyRounding, $roundInterest, $calculatePeriodFee, $currency, $firstRepaymentDate) {
            if ($principal <= 0 || $duration <= 0 || $intervalDays <= 0) {
                return [];
            }

            $totalPayments = $totalPaymentsOverride !== null
                ? max(1, $totalPaymentsOverride)
                : max(1, (int) ceil(($duration * 30) / $intervalDays));
            $periodPrincipalRaw = $principal / $totalPayments;
            $periodInterestRaw = $principal * ($rate / 100) * ($ratePerPayment ? 1 : ($intervalDays / 30));
            $remainingBalanceLocal = $principal;
            $rows = [];

            for ($i = 1; $i <= $totalPayments; $i++) {
                if ($firstRepaymentDate) {
                    $currentPaymentDate = new DateTime($firstRepaymentDate);
                    if ($i > 1) {
                        $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * ($i - 1)) . 'D'));
                    }
                } else {
                    $currentPaymentDate = clone $startDateObj;
                    // Daily starts repayment on the day after disbursement.
                    // Other interval methods preserve their inclusive dates.
                    $inclusiveOffsetDays = ($intervalDays * $i) - 1 + ($startNextDay ? 1 : 0);
                    if ($inclusiveOffsetDays > 0) {
                        $currentPaymentDate->add(new DateInterval('P' . $inclusiveOffsetDays . 'D'));
                    }
                }

                $feePay = $calculatePeriodFee($i, $totalPayments);

                if ($i === $totalPayments) {
                    $principalPay = $remainingBalanceLocal;
                    $standardPrincipalPay = $applyRounding($periodPrincipalRaw, $currency);

                    if ($normalizeFinalPayment) {
                        $standardInterestPay = $roundInterest($periodInterestRaw, $currency);
                        $standardPayment = $applyRounding($standardPrincipalPay + $standardInterestPay + $feePay, $currency);
                        $interestPay = max(0, $standardPayment - $principalPay - $feePay);
                    } elseif ($principalPay > $standardPrincipalPay) {
                        $diff = $principalPay - $standardPrincipalPay;
                        $interestPay = $roundInterest(max(0, $periodInterestRaw - $diff), $currency);
                    } else {
                        $interestPay = $roundInterest($periodInterestRaw, $currency);
                    }
                    $remainingBalanceLocal = 0;
                } else {
                    $principalPay = $applyRounding($periodPrincipalRaw, $currency);
                    $principalPay = min($principalPay, $remainingBalanceLocal);
                    $remainingBalanceLocal = max(0, $remainingBalanceLocal - $principalPay);
                    $interestPay = $roundInterest($periodInterestRaw, $currency);
                }

                $rows[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $principalPay,
                    'interest' => $interestPay,
                    'fee' => $feePay,
                    'payment' => $applyRounding($principalPay + $interestPay + $feePay, $currency),
                    'balance' => $remainingBalanceLocal,
                ];
            }

            return $rows;
        };



        // KHR monthly Smart Check is a dedicated branch. Its target has already
        // been rounded with KHR rules (500-riel units).
        $normalizeKhrMonthlyFinalPayment = function (
            array $payments,
            float $targetPayment,
            bool $allowReduction = false
        ) use ($exactAmount): array {
            if ($payments === [] || $targetPayment <= 0) {
                return $payments;
            }

            $lastIndex = array_key_last($payments);
            $currentPayment = (float) ($payments[$lastIndex]['payment'] ?? 0);
            $finalPrincipal = (float) ($payments[$lastIndex]['principal'] ?? 0);
            $finalFee = (float) ($payments[$lastIndex]['fee'] ?? 0);
            $adjustedInterest = $targetPayment - $finalPrincipal - $finalFee;

            $shouldAdjust = $targetPayment > $currentPayment
                || ($allowReduction && abs($targetPayment - $currentPayment) >= 0.001);

            if ($shouldAdjust && $adjustedInterest >= 0) {
                $payments[$lastIndex]['interest'] = $exactAmount($adjustedInterest);
                $payments[$lastIndex]['payment'] = $exactAmount($targetPayment);
            }

            return $payments;
        };

        // USD monthly Smart Check is separate from KHR. Its target has already
        // been rounded upward to a whole dollar.
        $normalizeUsdMonthlyFinalPayment = function (
            array $payments,
            float $targetPayment,
            bool $allowReduction = false
        ) use ($exactAmount): array {
            if ($payments === [] || $targetPayment <= 0) {
                return $payments;
            }

            $lastIndex = array_key_last($payments);
            $currentPayment = (float) ($payments[$lastIndex]['payment'] ?? 0);
            $finalPrincipal = (float) ($payments[$lastIndex]['principal'] ?? 0);
            $finalFee = (float) ($payments[$lastIndex]['fee'] ?? 0);
            $adjustedInterest = $targetPayment - $finalPrincipal - $finalFee;

            $shouldAdjust = $targetPayment > $currentPayment
                || ($allowReduction && abs($targetPayment - $currentPayment) >= 0.001);

            if ($shouldAdjust && $adjustedInterest >= 0) {
                $payments[$lastIndex]['interest'] = $exactAmount($adjustedInterest);
                $payments[$lastIndex]['payment'] = $exactAmount($targetPayment);
            }

            return $payments;
        };

        $monthlyRepaymentDay = max(1, min(31, $payDay1 ?? 11));

        $initializeMonthlyPaymentDate = function (DateTime $loanStartDate) use ($monthlyRepaymentDay, $firstRepaymentDate): DateTime {
            if ($firstRepaymentDate) {
                return new DateTime($firstRepaymentDate);
            }
            $paymentDate = clone $loanStartDate;
            $paymentDate->modify('first day of next month');
            $year = (int) $paymentDate->format('Y');
            $month = (int) $paymentDate->format('m');
            $lastDayOfMonth = (int) $paymentDate->format('t');
            $paymentDate->setDate($year, $month, min($monthlyRepaymentDay, $lastDayOfMonth));

            return $paymentDate;
        };

        $advanceMonthlyPaymentDate = function (DateTime $paymentDate) use ($monthlyRepaymentDay, $firstRepaymentDate): void {
            if ($firstRepaymentDate) {
                $dayToUse = (int) (new DateTime($firstRepaymentDate))->format('d');
                $paymentDate->modify('first day of next month');
                $year = (int) $paymentDate->format('Y');
                $month = (int) $paymentDate->format('m');
                $lastDayOfMonth = (int) $paymentDate->format('t');
                $paymentDate->setDate($year, $month, min($dayToUse, $lastDayOfMonth));
                return;
            }
            $paymentDate->modify('first day of next month');
            $year = (int) $paymentDate->format('Y');
            $month = (int) $paymentDate->format('m');
            $lastDayOfMonth = (int) $paymentDate->format('t');
            $paymentDate->setDate($year, $month, min($monthlyRepaymentDay, $lastDayOfMonth));
        };

        if ($option === 'fixed_daily') {
            // Daily Smart Check keeps the final total payment equal to the
            // regular daily payment while the final principal closes balance.
            $results = $buildFixedIntervalSchedule(1, $duration, true, true, true);
        } elseif ($option === 'fixed_biweekly') {
            $results = $buildFixedIntervalSchedule(14, $duration, true);
        } elseif ($option === 'fixed_weekly') {
            // Weekly Smart Check mirrors Daily without sharing monthly/split
            // normalization: only the final weekly interest is adjusted.
            $results = $buildFixedIntervalSchedule(7, $duration, true, true);
        } elseif ($option === 'annuity_monthly') {
            if ($principal <= 0 || $duration <= 0) {
                return [];
            }

            $monthlyInterestRate = $rate / 100;

            if ($monthlyInterestRate > 0) {
                $denominator = pow(1 + $monthlyInterestRate, $duration) - 1;
                if ($denominator == 0) {
                    $monthlyPayment = $principal / $duration;
                } else {
                    $monthlyPayment = $principal * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $duration) / $denominator;
                }
            } else {
                $monthlyPayment = $principal / $duration;
            }

            $monthlyPayment = $roundMonthlyPrincipal($monthlyPayment);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $monthlyInterest = $remainingBalance * ($monthlyInterestRate / 30) * $daysFromStart;
                } else {
                    $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                }

                $monthlyInterest = $roundInterest($monthlyInterest, $currency);

                if ($i == 1 && isset($daysFromStart)) {
                    $standardInterest = $remainingBalance * $monthlyInterestRate;
                    if ($isKhrCurrency) {
                        $standardInterest = $roundInterest($standardInterest, $currency);
                    }
                    $monthlyPrincipal = $monthlyPayment - $standardInterest;
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                } else {
                    $monthlyPrincipal = $monthlyPayment - $monthlyInterest;
                    $totalPayment = $monthlyPayment;
                }

                $monthlyPrincipal = $exactAmount($monthlyPrincipal);
                $monthlyPrincipal = min($monthlyPrincipal, $remainingBalance);

                if ($i == $duration) {
                    $monthlyPrincipal = $remainingBalance;
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                    $remainingBalance = 0;
                } else {
                    $remainingBalance = $exactAmount(max(0, $remainingBalance - $monthlyPrincipal));
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $periodCounter++,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $monthlyPrincipal,
                    'interest' => $monthlyInterest,
                    'fee' => $feePay,
                    'payment' => $exactAmount($monthlyPrincipal + $monthlyInterest + $feePay),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }

            $monthlySmartCheckTarget = $monthlyPayment + $calculatePeriodFee($duration, $duration);
            if ($isKhrCurrency) {
                $results = $normalizeKhrMonthlyFinalPayment($results, $monthlySmartCheckTarget, true);
            } elseif ($isUsdCurrency) {
                $results = $normalizeUsdMonthlyFinalPayment($results, $monthlySmartCheckTarget, true);
            }

        } elseif ($option === 'linear_monthly') {
            $monthlyInterestRate = $rate / 100;
            $monthlyPrincipal = $roundMonthlyPrincipal($principal / $duration);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $monthlyInterest = $remainingBalance * ($monthlyInterestRate / 30) * $daysFromStart;
                } else {
                    $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                }

                $monthlyInterest = $roundInterest($monthlyInterest, $currency);

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $currentInterest = $monthlyInterest;
                } else {
                    $currentPrincipal = min($monthlyPrincipal, $remainingBalance);
                    $currentInterest = $monthlyInterest;
                }

                $monthlyPayment = $exactAmount($currentPrincipal + $currentInterest);
                $remainingBalance = $exactAmount(max(0, $remainingBalance - $currentPrincipal));

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $currentInterest,
                    'fee' => $feePay,
                    'payment' => $exactAmount($monthlyPayment + $feePay),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        } elseif ($option === 'fixed_monthly') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyPrincipal = $principal / $duration;

            $monthlyInterest = $roundInterest($monthlyInterest, $currency);
            $monthlyPrincipal = $roundMonthlyPrincipal($monthlyPrincipal);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;

                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $rawInterest = $principal * (($rate / 100) / 30) * $daysFromStart;
                    $currentInterest = $roundInterest($rawInterest, $currency);
                } else {
                    $currentInterest = $monthlyInterest;
                }

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;

                    $remainingBalance = 0;

                } else {
                    $currentPrincipal = min($currentPrincipal, $remainingBalance);
                    $remainingBalance = $exactAmount(max(0, $remainingBalance - $currentPrincipal));
                }

                $feePay = $feePay ?? $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $roundInterest($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $exactAmount($currentPrincipal + $currentInterest + $feePay),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }

            $monthlySmartCheckTarget = $monthlyPrincipal
                + $monthlyInterest
                + $calculatePeriodFee($duration, $duration);
            if ($isKhrCurrency) {
                $results = $normalizeKhrMonthlyFinalPayment($results, $monthlySmartCheckTarget);
            } elseif ($isUsdCurrency) {
                $results = $normalizeUsdMonthlyFinalPayment($results, $monthlySmartCheckTarget);
            }
        } elseif ($option === 'Balloon') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyInterest = $roundInterest($monthlyInterest, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $rawInterest = $principal * (($rate / 100) / 30) * $daysFromStart;
                    $currentInterest = $roundInterest($rawInterest, $currency);
                } else {
                    $currentInterest = $roundInterest($monthlyInterest, $currency);
                }

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $remainingBalance = 0;
                } else {
                    $currentPrincipal = 0;
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $currentInterest,
                    'fee' => $feePay,
                    'payment' => $exactAmount($currentPrincipal + $currentInterest + $feePay),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        } elseif ($option === 'negotiable') {
            // Negotiable uses the same logic as fixed_monthly for preview
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyPrincipal = $principal / $duration;

            $monthlyInterest = $roundInterest($monthlyInterest, $currency);
            $monthlyPrincipal = $roundMonthlyPrincipal($monthlyPrincipal);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;
                // Flat (negotiable) method: same interest every period, no pro-rate
                $currentInterest = $monthlyInterest;

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $currentInterest = $monthlyInterest;
                    $remainingBalance = 0;
                } else {
                    $currentPrincipal = min($currentPrincipal, $remainingBalance);
                    $remainingBalance = $exactAmount(max(0, $remainingBalance - $currentPrincipal));
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $roundInterest($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $exactAmount($currentPrincipal + $currentInterest + $feePay),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }

            $monthlySmartCheckTarget = $monthlyPrincipal
                + $monthlyInterest
                + $calculatePeriodFee($duration, $duration);
            if ($isKhrCurrency) {
                $results = $normalizeKhrMonthlyFinalPayment($results, $monthlySmartCheckTarget);
            } elseif ($isUsdCurrency) {
                $results = $normalizeUsdMonthlyFinalPayment($results, $monthlySmartCheckTarget);
            }
        }

        return $results;
    }
    public static function formatScheduleForPrint(array $schedule, string $repaymentMethod, float $loanAmount): array
    {
        $rows = [];
        $isBimonthly = str_contains(strtolower($repaymentMethod), 'biweekly') ||
                       str_contains(strtolower($repaymentMethod), 'bimonthly');

        if (!$isBimonthly) {
            // For monthly, daily, weekly, just map directly to rows
            $balance = $loanAmount;
            $no = 1;
            foreach ($schedule as $payment) {
                $principal = (float)($payment['principal'] ?? $payment['principal_amount'] ?? 0);
                
                // Use provided balance or calculate it
                $pBalance = $payment['balance'] ?? $payment['outstanding_balance'] ?? $payment['remaining_balance'] ?? null;
                if ($pBalance !== null && $pBalance >= 0) {
                    $balance = (float)$pBalance;
                } else {
                    $balance = max(0, $balance - $principal);
                }

                $totalDue = (float)($payment['payment'] ?? $payment['total_paid'] ?? 0);
                // If payment key is missing but interest/fee are present
                if (empty($payment['payment']) && isset($payment['interest_amount'])) {
                    $totalDue = $principal + (float)($payment['interest_amount'] ?? 0) + (float)($payment['fee_amount'] ?? 0);
                }

                $dateStr = $payment['date'] ?? $payment['payment_date'] ?? '';

                $rows[] = [
                    'no' => $no++,
                    'date' => $dateStr,
                    'principal' => $principal,
                    'payment' => $totalDue,
                    'balance' => $balance,
                    'notes' => ''
                ];
            }
            return $rows;
        }

        // Bimonthly logic
        if (empty($schedule)) return $rows;

        $balance = $loanAmount;
        $currentDate = null;
        $currentPrincipal = 0.0;
        $currentPayment11 = 0.0;
        $currentPayment26 = 0.0;
        $paymentsInRow = 0;
        $lastPaymentInRow = null;

        $flushRow = function() use (&$rows, &$currentDate, &$currentPrincipal, &$currentPayment11, &$currentPayment26, &$balance, &$paymentsInRow, &$lastPaymentInRow) {
            if ($currentDate === null) return;

            $principal = $currentPrincipal;
            $pBalance = $lastPaymentInRow['balance'] ?? $lastPaymentInRow['outstanding_balance'] ?? $lastPaymentInRow['remaining_balance'] ?? null;
            
            if ($pBalance !== null && $pBalance >= 0) {
                // If it's the last payment or balance > 0, use it
                $balance = (float)$pBalance;
            } else {
                $balance -= $principal;
                if ($balance < 0.01) $balance = 0;
            }

            $rows[] = [
                'no' => count($rows) + 1,
                'date' => $currentDate,
                'principal' => $principal,
                'payment11' => $currentPayment11,
                'payment26' => $currentPayment26,
                'balance' => $balance,
                'notes' => ''
            ];

            $currentDate = null;
            $currentPrincipal = 0.0;
            $currentPayment11 = 0.0;
            $currentPayment26 = 0.0;
            $paymentsInRow = 0;
            $lastPaymentInRow = null;
        };

        $currentMonth = null;
        $currentYear = null;

        // Sort schedule by date or order if needed, but we assume it's already sorted
        foreach ($schedule as $payment) {
            $rawDate = $payment['date'] ?? $payment['payment_date'] ?? '';
            // Parse date (d/m/Y or Y-m-d)
            $pDate = null;
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $rawDate, $matches)) {
                $pDate = DateTime::createFromFormat('d/m/Y', $rawDate);
            } else {
                $pDate = DateTime::createFromFormat('Y-m-d', $rawDate);
            }

            if ($pDate) {
                $month = (int)$pDate->format('m');
                $year = (int)$pDate->format('Y');

                if ($currentMonth !== null && ($month !== $currentMonth || $year !== $currentYear)) {
                    $flushRow();
                }
                $currentMonth = $month;
                $currentYear = $year;
            } else {
                if ($paymentsInRow >= 2) {
                    $flushRow();
                }
            }

            $lastPaymentInRow = $payment;
            if ($currentDate === null) {
                $currentDate = $rawDate;
            }
            
            $principal = (float)($payment['principal'] ?? $payment['principal_amount'] ?? 0);
            $currentPrincipal += $principal;

            $totalPayment = (float)($payment['payment'] ?? $payment['total_paid'] ?? 0);
            if (empty($payment['payment']) && isset($payment['interest_amount'])) {
                $totalPayment = $principal + (float)($payment['interest_amount'] ?? 0) + (float)($payment['fee_amount'] ?? 0);
            }

            // Determine slot
            $slot = 26;
            if ($pDate) {
                $day = (int)$pDate->format('d');
                if ($day == 11) $slot = 11;
                elseif ($day == 26) $slot = 26;
                else $slot = $day <= 15 ? 11 : 26;
            }

            if ($slot === 11) {
                $currentPayment11 += $totalPayment;
            } else {
                $currentPayment26 += $totalPayment;
            }

            $paymentsInRow++;
        }

        $flushRow();

        return $rows;
    }
}
