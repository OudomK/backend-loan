<?php

namespace App\Services;

use DateTime;
use DateInterval;

class LoanCalculator
{
    public function calculateLoanWithDates($principal, $rate, $duration, $option, $startDate, $currency)
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
            return round($amount); // No cents for USD
        };



        if (strpos($option, 'fixed_15days_70_30') !== false) {
            $percentages = explode('_', $option);
            $firstPayPercent = (int) ($percentages[2] ?? 70);
            $secondPayPercent = (int) ($percentages[3] ?? 30);

            $totalPayments = $duration * 2;
            $monthlyPrincipal = $principal / $duration;
            $monthlyInterest = $principal * ($rate / 100);

            $dailyInterestRate = ($rate / 100) / 365;

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
                    $fullMonthInterest = $monthlyInterest;
                    $additionalDaysInterest = $principal * $dailyInterestRate * $daysFromStart;
                    $totalFirstInterest = $fullMonthInterest + $additionalDaysInterest;
                    $firstPaymentInterest = $applyRounding($totalFirstInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $principalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;

                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($principalPay, $currency),
                        'interest' => $firstPaymentInterest,
                        'payment' => $applyRounding($principalPay + $firstPaymentInterest, $currency),
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                } elseif ($i == $totalPayments) {
                    $finalInterestPercent = $isFirst ? $firstPayPercent : $secondPayPercent;
                    $finalInterest = $applyRounding($monthlyInterest * ($finalInterestPercent / 100), $currency);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($remainingBalance, $currency),
                        'interest' => $finalInterest,
                        'payment' => $applyRounding($remainingBalance + $finalInterest, $currency),
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $principalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($principalPay, $currency),
                        'interest' => $interestPay,
                        'payment' => $applyRounding($principalPay + $interestPay, $currency),
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
                    $interestPay = $applyRounding($monthlyInterest * ($days / 30), $currency);
                } else {
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                }

                if ($i == $totalPayments) {
                    $principalPay = $applyRounding($remainingBalance, $currency);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'payment' => $applyRounding($principalPay + $interestPay, $currency),
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $rawPrincipalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    $principalPay = $applyRounding($rawPrincipalPay, $currency);
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'payment' => $applyRounding($principalPay + $interestPay, $currency),
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
        } elseif ($option === 'annuity_monthly') {
            if ($principal <= 0 || $rate <= 0 || $duration <= 0) {
                return [];
            }

            $monthlyInterestRate = $rate / 100;

            $denominator = pow(1 + $monthlyInterestRate, $duration) - 1;
            if ($denominator == 0) {
                return [];
            }

            $monthlyPayment = $principal * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $duration) / $denominator;
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

                $results[] = [
                    'period' => $periodCounter++,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $monthlyPrincipal,
                    'interest' => $monthlyInterest,
                    'payment' => $applyRounding($totalPayment, $currency),
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

                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $monthlyInterest,
                    'payment' => $monthlyPayment,
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

                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $applyRounding($currentPrincipal, $currency),
                    'interest' => $applyRounding($currentInterest, $currency),
                    'payment' => $applyRounding($currentPrincipal + $currentInterest, $currency),
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

                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $currentInterest,
                    'payment' => $applyRounding($currentPrincipal + $currentInterest, $currency),
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

                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $applyRounding($currentPrincipal, $currency),
                    'interest' => $applyRounding($currentInterest, $currency),
                    'payment' => $applyRounding($currentPrincipal + $currentInterest, $currency),
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
