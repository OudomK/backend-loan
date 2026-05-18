<?php

namespace App\Services;

use DateTime;
use DateInterval;

class LoanCalculator
{
    public function calculateLoanWithDates(float $principal, float $rate, int $duration, string $option, string $startDate, string $currency, float $adminFee = 0, string $adminFeeType = 'one_time')
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
            return ceil($amount); // Round up to whole number for USD
        };

        $calculatePeriodFee = function ($periodNumber, $totalPayments) use ($principal, $adminFee, $adminFeeType, $applyRounding, $currency) {
            if ($adminFee <= 0) return 0;
            // Upfront fee types are recognized when the loan is created, not during repayment.
            if ($adminFeeType !== 'monthly') {
                return 0;
            }
            $totalFeeAmount = $principal * ($adminFee / 100);
            return $applyRounding($totalFeeAmount / $totalPayments, $currency);
        };

        $buildFixedIntervalSchedule = function (int $intervalDays) use (
            $principal,
            $rate,
            $duration,
            $startDateObj,
            $applyRounding,
            $calculatePeriodFee,
            $currency
        ) {
            if ($principal <= 0 || $duration <= 0 || $intervalDays <= 0) {
                return [];
            }

            $totalDays = $duration * 30;
            $totalPayments = max(1, (int) ceil($totalDays / $intervalDays));
            $periodPrincipalRaw = $principal / $totalPayments;
            $periodInterestRaw = $principal * ($rate / 100) * ($intervalDays / 30);
            $remainingBalanceLocal = $principal;
            $rows = [];

            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $startDateObj;
                $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * $i) . 'D'));

                if ($i === $totalPayments) {
                    $principalPay = $applyRounding($remainingBalanceLocal, $currency);
                    $remainingBalanceLocal = 0;
                } else {
                    $principalPay = $applyRounding($periodPrincipalRaw, $currency);
                    $remainingBalanceLocal = max(0, $applyRounding($remainingBalanceLocal - $principalPay, $currency));
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

            // Exactly 15 days between each payment: period i = start_date + (15 * i) days
            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $loanStartDate;
                $currentPaymentDate->add(new DateInterval('P' . (15 * $i) . 'D'));
                $isFirst = ($i % 2 === 1); // odd period = first half (70%), even = second half (30%)

                if ($i == 1) {
                    $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days;
                    // Pro-rate first payment interest based on actual days (same as 50/50 method)
                    $totalFirstInterest = $monthlyInterest * ($daysFromStart / 30);
                    $firstPaymentInterest = $applyRounding($totalFirstInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $principalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;

                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($principalPay, $currency),
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
                        'principal' => $applyRounding($remainingBalance, $currency),
                        'interest' => $finalInterest,
                        'fee' => $feePay,
                        'payment' => $applyRounding($remainingBalance + $finalInterest + $feePay, $currency),
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $principalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($principalPay, $currency),
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
                    $pay['balance'] = $applyRounding($runningBalance, $currency);
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

            // Exactly 15 days between each payment: period i = start_date + (15 * i) days
            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $loanStartDate;
                $currentPaymentDate->add(new DateInterval('P' . (15 * $i) . 'D'));
                $isFirst = ($i % 2 === 1);

                if ($i == 1) {
                    $days = $loanStartDate->diff($currentPaymentDate)->days;
                    // Pro-rate first payment interest and split by percentage (50/50)
                    $interestPay = $applyRounding($monthlyInterest * ($days / 30) * ($firstPayPercent / 100), $currency);
                } else {
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                }

                if ($i == $totalPayments) {
                    $principalPay = $applyRounding($remainingBalance, $currency);
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
                    $pay['balance'] = $applyRounding($runningBalance, $currency);
                }
                $pay['date'] = date('d/m/Y', strtotime($pay['date']));
            }
            unset($pay);
            $results = $allPayments;
        } elseif ($option === 'fixed_daily') {
            $results = $buildFixedIntervalSchedule(1);
        } elseif ($option === 'fixed_weekly') {
            $results = $buildFixedIntervalSchedule(7);
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

            $currentPaymentDate = clone $loanStartDate;
            $currentPaymentDate->modify('first day of next month');
            $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);

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
                $remainingBalance = $applyRounding($remainingBalance - $monthlyPrincipal, $currency);

                if ($i == $duration && $remainingBalance !== 0) {
                    $monthlyPrincipal += $remainingBalance;
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                    $remainingBalance = 0;
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
                    $currentPaymentDate->modify('first day of next month');
                    $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);
                }
            }
        } elseif ($option === 'linear_monthly') {
            $monthlyInterestRate = $rate / 100;
            $monthlyPrincipal = $principal / $duration;
            $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = clone $loanStartDate;
            $currentPaymentDate->modify('first day of next month');
            $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);

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
                    $currentPrincipal = $monthlyPrincipal;
                }

                $monthlyPayment = $applyRounding($currentPrincipal + $monthlyInterest, $currency);
                $remainingBalance = $applyRounding($remainingBalance - $currentPrincipal, $currency);

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
                    $currentPaymentDate->modify('first day of next month');
                    $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);
                }
            }
        } elseif ($option === 'fixed_monthly') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyPrincipal = $principal / $duration;

            $monthlyInterest = $applyRounding($monthlyInterest, $currency);
            $monthlyPrincipal = $applyRounding($monthlyPrincipal, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = clone $loanStartDate;
            $currentPaymentDate->modify('first day of next month');
            $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;

                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    if ($daysFromStart > 30) {
                        $extraDaysInterest = $principal * ($rate / 100) * (($daysFromStart - 30) / 30);
                        $currentInterest = $monthlyInterest + $extraDaysInterest;
                    } else {
                        $currentInterest = $monthlyInterest;
                    }
                } else {
                    $currentInterest = $monthlyInterest;
                }

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $remainingBalance = 0;
                } else {
                    $remainingBalance = $applyRounding($remainingBalance - $monthlyPrincipal, $currency);
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $applyRounding($currentPrincipal, $currency),
                    'interest' => $applyRounding($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $applyRounding($currentPrincipal + $currentInterest + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $currentPaymentDate->modify('first day of next month');
                    $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);
                }
            }
        } elseif ($option === 'Balloon') {
            $monthlyInterest = $principal * ($rate / 100);
            $monthlyInterest = $applyRounding($monthlyInterest, $currency);

            $remainingBalance = $principal;

            $loanStartDate = clone $startDateObj;

            $currentPaymentDate = clone $loanStartDate;
            $currentPaymentDate->modify('first day of next month');
            $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);

            for ($i = 1; $i <= $duration; $i++) {
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    if ($daysFromStart > 30) {
                        $extraDaysInterest = $principal * ($rate / 100) * (($daysFromStart - 30) / 30);
                        $currentInterest = $monthlyInterest + $extraDaysInterest;
                    } else {
                        $currentInterest = $monthlyInterest;
                    }
                } else {
                    $currentInterest = $monthlyInterest;
                }

                $currentInterest = $applyRounding($currentInterest, $currency);

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
                    $currentPaymentDate->modify('first day of next month');
                    $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);
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

            $currentPaymentDate = clone $loanStartDate;
            $currentPaymentDate->modify('first day of next month');
            $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);

            for ($i = 1; $i <= $duration; $i++) {
                $currentPrincipal = $monthlyPrincipal;

                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    if ($daysFromStart > 30) {
                        $extraDaysInterest = $principal * ($rate / 100) * (($daysFromStart - 30) / 30);
                        $currentInterest = $monthlyInterest + $extraDaysInterest;
                    } else {
                        $currentInterest = $monthlyInterest;
                    }
                } else {
                    $currentInterest = $monthlyInterest;
                }

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    $remainingBalance = 0;
                } else {
                    $remainingBalance = $applyRounding($remainingBalance - $monthlyPrincipal, $currency);
                }

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $applyRounding($currentPrincipal, $currency),
                    'interest' => $applyRounding($currentInterest, $currency),
                    'fee' => $feePay,
                    'payment' => $applyRounding($currentPrincipal + $currentInterest + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $currentPaymentDate->modify('first day of next month');
                    $currentPaymentDate->setDate($currentPaymentDate->format('Y'), $currentPaymentDate->format('m'), 11);
                }
            }
        }

        return $results;
    }
}
