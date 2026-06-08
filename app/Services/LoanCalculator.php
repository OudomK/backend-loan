<?php

namespace App\Services;

use DateTime;
use DateInterval;

class LoanCalculator
{
    public function calculateLoanWithDates(float $principal, float $rate, int $duration, string $option, string $startDate, string $currency, float $adminFee = 0, string $adminFeeType = 'one_time', ?int $payDay1 = null, ?int $payDay2 = null)
    {
        $results = [];
        $startDateObj = new DateTime($startDate);
        $currentDate = clone $startDateObj;
        $periodCounter = 1;
        $remainingBalance = $principal;
        $startDay = (int) $currentDate->format('d');
        $adjustMonth = ($startDay > 11) ? 1 : 0;
        $currentDate->add(new DateInterval('P' . $adjustMonth . 'M'));

        // Custom rounding function for KHR
        $customRoundKHR = function ($amount) {
            $remainder = $amount % 1000;

            if ($remainder > 0 && $remainder < 500) {
                return floor($amount / 1000) * 1000 + 500;
            } elseif ($remainder >= 500) {
                return ceil($amount / 1000) * 1000;
            }

            return $amount;
        };

        // Function to apply currency-specific rounding
        $applyRounding = function ($amount, $currency) use ($customRoundKHR) {
            if (strpos($currency, 'KHR') !== false) {
                return $customRoundKHR($amount);
            }
            return round($amount, 0); // Round to whole number for USD
        };

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

        $buildFixedIntervalSchedule = function (int $intervalDays, ?int $totalPaymentsOverride = null) use ($principal, $rate, $duration, $startDateObj, $applyRounding, $calculatePeriodFee, $currency) {
            if ($principal <= 0 || $duration <= 0 || $intervalDays <= 0) {
                return [];
            }

            $totalPayments = $totalPaymentsOverride !== null
                ? max(1, $totalPaymentsOverride)
                : max(1, (int) ceil(($duration * 30) / $intervalDays));
            $periodPrincipalRaw = $principal / $totalPayments;
            $periodInterestRaw = $principal * ($rate / 100) * ($intervalDays / 30);
            $remainingBalanceLocal = $principal;
            $rows = [];

            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $startDateObj;
                $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * $i) . 'D'));

                if ($i === $totalPayments) {
                    $principalPay = $remainingBalanceLocal;
                    $remainingBalanceLocal = 0;
                } else {
                    $principalPay = $applyRounding($periodPrincipalRaw, $currency);
                    $principalPay = min($principalPay, $remainingBalanceLocal);
                    $remainingBalanceLocal = max(0, $remainingBalanceLocal - $principalPay);
                }

                $interestPay = $applyRounding($periodInterestRaw, $currency);
                $feePay = $calculatePeriodFee($i, $totalPayments);

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



        $buildSemiMonthlyDates = function (DateTime $loanStartDate, int $totalPayments, ?int $dayA, ?int $dayB): array {
            $firstDay = max(1, min(31, $dayA ?? 11));
            $secondDay = max(1, min(31, $dayB ?? 26));

            if ($firstDay === $secondDay) {
                $secondDay = min(31, $firstDay + 1);
            }

            $days = [$firstDay, $secondDay];
            sort($days);

            $cursor = new DateTime($loanStartDate->format('Y-m-01'));
            $dates = [];

            while (count($dates) < $totalPayments) {
                $year = (int) $cursor->format('Y');
                $month = (int) $cursor->format('m');
                $lastDayOfMonth = (int) $cursor->format('t');

                foreach ($days as $index => $day) {
                    $paymentDate = new DateTime($cursor->format('Y-m-01'));
                    $paymentDate->setDate($year, $month, min($day, $lastDayOfMonth));

                    $interval = $loanStartDate->diff($paymentDate);
                    $daysDiff = (int) $interval->format('%r%a');

                    if ($daysDiff < 5) {
                        continue;
                    }

                    $dates[] = [
                        'date' => $paymentDate,
                        'is_first_half' => $index === 0,
                    ];

                    if (count($dates) >= $totalPayments) {
                        break;
                    }
                }

                $cursor->modify('first day of next month');
            }

            return $dates;
        };

        $monthlyRepaymentDay = max(1, min(31, $payDay1 ?? 11));

        $initializeMonthlyPaymentDate = function (DateTime $loanStartDate) use ($monthlyRepaymentDay): DateTime {
            $paymentDate = new DateTime($loanStartDate->format('Y-m-01'));
            $year = (int) $paymentDate->format('Y');
            $month = (int) $paymentDate->format('m');
            $lastDayOfMonth = (int) $paymentDate->format('t');
            $paymentDate->setDate($year, $month, min($monthlyRepaymentDay, $lastDayOfMonth));

            $interval = $loanStartDate->diff($paymentDate);
            $daysDiff = (int) $interval->format('%r%a');

            if ($daysDiff < 5) {
                $paymentDate->modify('first day of next month');
                $year = (int) $paymentDate->format('Y');
                $month = (int) $paymentDate->format('m');
                $lastDayOfMonth = (int) $paymentDate->format('t');
                $paymentDate->setDate($year, $month, min($monthlyRepaymentDay, $lastDayOfMonth));
            }

            return $paymentDate;
        };

        $advanceMonthlyPaymentDate = function (DateTime $paymentDate) use ($monthlyRepaymentDay): void {
            $paymentDate->modify('first day of next month');
            $year = (int) $paymentDate->format('Y');
            $month = (int) $paymentDate->format('m');
            $lastDayOfMonth = (int) $paymentDate->format('t');
            $paymentDate->setDate($year, $month, min($monthlyRepaymentDay, $lastDayOfMonth));
        };

        if (strpos($option, 'fixed_15days_70_30') !== false) {
            $percentages = explode('_', $option);
            $firstPayPercent = (int) ($percentages[2] ?? 70);
            $secondPayPercent = (int) ($percentages[3] ?? 30);

            $totalPayments = $duration * 2;
            $monthlyPrincipal = $principal / $duration;
            $monthlyInterest = $principal * ($rate / 100);



            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);

            $remainingBalance = $principal;
            $allPayments = [];
            $loanStartDate = clone $startDateObj;
            $paymentDates = $buildSemiMonthlyDates($loanStartDate, $totalPayments, $payDay1, $payDay2);

            for ($i = 1; $i <= $totalPayments; $i++) {
                $paymentMeta = $paymentDates[$i - 1] ?? null;
                if (!$paymentMeta) {
                    break;
                }

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                if ($i == 1) {
                    // Flat method: no pro-rate on first payment, use full split percentage
                    $firstPaymentInterest = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $principalPay = $applyRounding($isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $principalPay = min($principalPay, $remainingBalance);

                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $firstPaymentInterest,
                        'fee' => $feePay,
                        'payment' => $applyRounding($principalPay + $firstPaymentInterest + $feePay, $currency),
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                } elseif ($i == $totalPayments) {
                    $finalInterestPercent = $isFirst ? $firstPayPercent : $secondPayPercent;
                    $finalInterest = $applyRounding($monthlyInterest * ($finalInterestPercent / 100), $currency);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $remainingBalance,
                        'interest' => $finalInterest,
                        'fee' => $feePay,
                        'payment' => $applyRounding($remainingBalance + $finalInterest + $feePay, $currency),
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $principalPay = $applyRounding($isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $principalPay = min($principalPay, $remainingBalance);
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $applyRounding($principalPay + $interestPay + $feePay, $currency),
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                }
            }

            usort($allPayments, fn($a, $b) => $a['order'] <=> $b['order']);
            $runningBalance = $principal;
            foreach ($allPayments as $idx => &$pay) {
                if ($idx === count($allPayments) - 1) {
                    $pay['balance'] = 0;
                } else {
                    $runningBalance -= $pay['principal'];
                    $pay['balance'] = max(0, $runningBalance);
                }
                $pay['date'] = date('d/m/Y', strtotime($pay['date']));
            }
            unset($pay);
            $results = $allPayments;
        } elseif ($option === 'fixed_15days_50_50') {
            $firstPayPercent = 50;
            $secondPayPercent = 50;

            $totalPayments = $duration * 2;
            $monthlyPrincipal = $principal / $duration;
            $monthlyInterest = $principal * ($rate / 100);

            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);

            $remainingBalance = $principal;
            $allPayments = [];
            $loanStartDate = clone $startDateObj;
            $paymentDates = $buildSemiMonthlyDates($loanStartDate, $totalPayments, $payDay1, $payDay2);

            for ($i = 1; $i <= $totalPayments; $i++) {
                $paymentMeta = $paymentDates[$i - 1] ?? null;
                if (!$paymentMeta) {
                    break;
                }

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                // Flat method: no pro-rate on first payment, use full split percentage
                $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);

                if ($i == $totalPayments) {
                    $principalPay = $remainingBalance;
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $applyRounding($principalPay + $interestPay + $feePay, $currency),
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $rawPrincipalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    $principalPay = $applyRounding($rawPrincipalPay, $currency);
                    $principalPay = min($principalPay, $remainingBalance);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $applyRounding($principalPay + $interestPay + $feePay, $currency),
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                }
            }

            usort($allPayments, fn($a, $b) => $a['order'] <=> $b['order']);
            $runningBalance = $principal;
            foreach ($allPayments as $idx => &$pay) {
                if ($idx === count($allPayments) - 1) {
                    $pay['balance'] = 0;
                } else {
                    $runningBalance -= $pay['principal'];
                    $pay['balance'] = max(0, $runningBalance);
                }
                $pay['date'] = date('d/m/Y', strtotime($pay['date']));
            }
            unset($pay);
            $results = $allPayments;
        } elseif ($option === 'fixed_daily') {
            $results = $buildFixedIntervalSchedule(1, $duration);
        } elseif ($option === 'fixed_biweekly') {
            $results = $buildFixedIntervalSchedule(14, $duration);
        } elseif ($option === 'fixed_weekly') {
            $results = $buildFixedIntervalSchedule(7, $duration);
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

            $monthlyPayment = $applyRounding($monthlyPayment, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    if ($daysFromStart > 30) {
                        $baseInterest = $remainingBalance * $monthlyInterestRate;
                        $extraDaysInterest = $remainingBalance * ($monthlyInterestRate / 30) * ($daysFromStart - 30);
                        $monthlyInterest = $baseInterest + $extraDaysInterest;
                    } else {
                        $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                    }
                } else {
                    $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                }

                $monthlyInterest = $applyRounding($monthlyInterest, $currency);

                if ($i == 1 && isset($daysFromStart) && $daysFromStart > 30) {
                    $monthlyPrincipal = $monthlyPayment - ($remainingBalance * $monthlyInterestRate);
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                } else {
                    $monthlyPrincipal = $monthlyPayment - $monthlyInterest;
                    $totalPayment = $monthlyPayment;
                }

                $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);
                $monthlyPrincipal = min($monthlyPrincipal, $remainingBalance);

                if ($i == $duration) {
                    $monthlyPrincipal = $remainingBalance;
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                    $remainingBalance = 0;
                } else {
                    $remainingBalance = max(0, $remainingBalance - $monthlyPrincipal);
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $periodCounter++,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $monthlyPrincipal,
                    'interest' => $monthlyInterest,
                    'fee' => $feePay,
                    'payment' => $applyRounding($totalPayment + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        } elseif ($option === 'linear_monthly') {
            $monthlyInterestRate = $rate / 100;
            $monthlyPrincipal = $principal / $duration;
            $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    if ($daysFromStart > 30) {
                        $baseInterest = $remainingBalance * $monthlyInterestRate;
                        $extraDaysInterest = $remainingBalance * ($monthlyInterestRate / 30) * ($daysFromStart - 30);
                        $monthlyInterest = $baseInterest + $extraDaysInterest;
                    } else {
                        $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                    }
                } else {
                    $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                }

                $monthlyInterest = $applyRounding($monthlyInterest, $currency);

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                } else {
                    $currentPrincipal = min($monthlyPrincipal, $remainingBalance);
                }

                $monthlyPayment = $applyRounding($currentPrincipal + $monthlyInterest, $currency);
                $remainingBalance = max(0, $remainingBalance - $currentPrincipal);

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $monthlyInterest,
                    'fee' => $feePay,
                    'payment' => $applyRounding($monthlyPayment + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        } elseif ($option === 'fixed_monthly') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyPrincipal = $principal / $duration;

            $monthlyInterest = $applyRounding($monthlyInterest, $currency);
            $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;
                // Flat method: same interest every period, no pro-rate on first payment
                $currentInterest = $monthlyInterest;

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $remainingBalance = 0;
                } else {
                    $currentPrincipal = min($currentPrincipal, $remainingBalance);
                    $remainingBalance = max(0, $remainingBalance - $currentPrincipal);
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $applyRounding($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $applyRounding($currentPrincipal + $currentInterest + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        } elseif ($option === 'Balloon') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyInterest = $applyRounding($monthlyInterest, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                // Balloon flat: same interest every period, no pro-rate
                $currentInterest = $applyRounding($monthlyInterest, $currency);

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
                    'payment' => $applyRounding($currentPrincipal + $currentInterest + $feePay, $currency),
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

            $monthlyInterest = $applyRounding($monthlyInterest, $currency);
            $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = $initializeMonthlyPaymentDate($loanStartDate);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;
                // Flat (negotiable) method: same interest every period, no pro-rate
                $currentInterest = $monthlyInterest;

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $remainingBalance = 0;
                } else {
                    $currentPrincipal = min($currentPrincipal, $remainingBalance);
                    $remainingBalance = max(0, $remainingBalance - $currentPrincipal);
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $applyRounding($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $applyRounding($currentPrincipal + $currentInterest + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }
        }

        return $results;
    }
}

