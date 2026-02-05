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
            if ($currency === 'KHR') {
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

            $currentDate = clone $startDateObj;
            $loanStartDate = clone $startDateObj;
            $year = (int) $currentDate->format('Y');
            $month = (int) $currentDate->format('m');
            $startDay = (int) $loanStartDate->format('d');

            if ($startDay >= 1 && $startDay <= 15) {
                $paymentDate = new DateTime("$year-$month-26");
            } else {
                $nextMonth = clone $loanStartDate;
                $nextMonth->modify('first day of next month');
                $paymentDate = new DateTime($nextMonth->format('Y-m-11'));
            }

            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $paymentDate;
                $isFirst = $currentPaymentDate->format('d') == '11';

                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days;
                    $paymentDay = (int) $currentPaymentDate->format('d');
                    $isFirstPayment = ($paymentDay == 11);

                    // User's logic: Full month + additional days interest
                    $fullMonthInterest = $monthlyInterest;
                    $additionalDaysInterest = $principal * $dailyInterestRate * $daysFromStart;
                    $totalFirstInterest = $fullMonthInterest + $additionalDaysInterest;

                    $firstPaymentInterest = $applyRounding($totalFirstInterest * ($isFirstPayment ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $principalPay = $isFirstPayment ? $firstPaymentPrincipal : $secondPaymentPrincipal;

                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $applyRounding($principalPay, $currency),
                        'interest' => $firstPaymentInterest,
                        'payment' => $applyRounding($principalPay + $firstPaymentInterest, $currency),
                        'balance' => null,
                        'order' => strtotime($currentPaymentDate->format('Y-m-d')),
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
                        'order' => strtotime($currentPaymentDate->format('Y-m-d')),
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
                        'order' => strtotime($currentPaymentDate->format('Y-m-d')),
                    ];

                    $remainingBalance -= $principalPay;
                }

                if ($currentPaymentDate->format('d') == '11') {
                    $nextYear = (int) $currentPaymentDate->format('Y');
                    $nextMonth = (int) $currentPaymentDate->format('m');
                    $paymentDate = new DateTime("$nextYear-$nextMonth-26");
                } else {
                    $nextMonth = clone $currentPaymentDate;
                    $nextMonth->modify('first day of next month');
                    $nextYear = (int) $nextMonth->format('Y');
                    $nextMonthNum = (int) $nextMonth->format('m');
                    $paymentDate = new DateTime("$nextYear-$nextMonthNum-11");
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

            $currentDate = clone $startDateObj;
            $loanStartDate = clone $startDateObj;
            $year = (int) $currentDate->format('Y');
            $month = (int) $currentDate->format('m');
            $startDay = (int) $loanStartDate->format('d');

            if ($startDay >= 1 && $startDay <= 15) {
                $paymentDate = new DateTime("$year-$month-26");
            } else {
                $nextMonth = clone $loanStartDate;
                $nextMonth->modify('first day of next month');
                $paymentDate = new DateTime($nextMonth->format('Y-m-11'));
            }

            for ($i = 1; $i <= $totalPayments; $i++) {
                $currentPaymentDate = clone $paymentDate;
                $isFirst = $currentPaymentDate->format('d') == '11';

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
                        'order' => strtotime($currentPaymentDate->format('Y-m-d')),
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
                        'order' => strtotime($currentPaymentDate->format('Y-m-d')),
                    ];

                    $remainingBalance -= $principalPay;
                }

                if ($currentPaymentDate->format('d') == '11') {
                    $paymentDate = new DateTime($currentPaymentDate->format('Y-m-26'));
                } else {
                    $nextMonth = clone $currentPaymentDate;
                    $nextMonth->modify('first day of next month');
                    $paymentDate = new DateTime($nextMonth->format('Y-m-11'));
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
