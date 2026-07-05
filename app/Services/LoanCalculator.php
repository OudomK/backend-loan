<?php

namespace App\Services;

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

        // Custom rounding function for KHR
        $customRoundKHR = function ($amount) {
            $amountInt = (int) round($amount);
            $remainder = $amountInt % 1000;

            if ($remainder > 0 && $remainder < 500) {
                return floor($amountInt / 1000) * 1000 + 500;
            } elseif ($remainder >= 500) {
                return ceil($amountInt / 1000) * 1000;
            }

            return (float) $amountInt;
        };

        // Function to apply currency-specific rounding
        $applyRounding = function ($amount, $currency) use ($customRoundKHR) {
            if (strpos($currency, 'KHR') !== false) {
                return $customRoundKHR($amount);
            }
            return ceil($amount); // Round up to next whole number for USD
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

        $buildFixedIntervalSchedule = function (int $intervalDays, ?int $totalPaymentsOverride = null) use ($principal, $rate, $duration, $startDateObj, $applyRounding, $calculatePeriodFee, $currency, $firstRepaymentDate) {
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
                if ($firstRepaymentDate) {
                    $currentPaymentDate = new DateTime($firstRepaymentDate);
                    if ($i > 1) {
                        $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * ($i - 1)) . 'D'));
                    }
                } else {
                    $currentPaymentDate = clone $startDateObj;
                    $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * $i) . 'D'));
                }

                if ($i === $totalPayments) {
                    $principalPay = $remainingBalanceLocal;

                    // Smart Check: Only adjust if final principal is higher than normal
                    $standardPrincipalPay = $applyRounding($periodPrincipalRaw, $currency);
                    if ($principalPay > $standardPrincipalPay) {
                        $diff = $principalPay - $standardPrincipalPay;
                        $adjustedInterest = $periodInterestRaw - $diff;
                        $interestPay = $applyRounding(max(0, $adjustedInterest), $currency);
                    } else {
                        $interestPay = $applyRounding($periodInterestRaw, $currency);
                    }

                    $remainingBalanceLocal = 0;
                } else {
                    $principalPay = $applyRounding($periodPrincipalRaw, $currency);
                    $principalPay = min($principalPay, $remainingBalanceLocal);
                    $remainingBalanceLocal = max(0, $remainingBalanceLocal - $principalPay);
                    $interestPay = $applyRounding($periodInterestRaw, $currency);
                }

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

            $startDay = (int) $loanStartDate->format('d');
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

                    if ($daysDiff <= 0) {
                        continue;
                    }

                    // For the first payment, apply the 1-15/16-30 rule:
                    // Day 1-15: first payment on the second pay day (e.g. 26th)
                    // Day 16-30/31: first payment on the first pay day (e.g. 11th) of next month
                    if (count($dates) === 0) {
                        if ($startDay >= 1 && $startDay <= 15) {
                            if ($index === 0) {
                                continue;
                            }
                        } else {
                            if ($paymentDate->format('Y-m') === $loanStartDate->format('Y-m')) {
                                continue;
                            }
                        }
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
                    $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1; // +1 for inclusive days

                    if ($daysFromStart < 15) {
                        // Shortage day: DO NOT pro-rate. Charge normal flat amount.
                        $firstPaymentInterest = $applyRounding($monthlyInterest * ($firstPayPercent / 100), $currency);
                        $principalPay = $applyRounding($firstPaymentPrincipal, $currency);
                    } else {
                        // Excess/Normal day: Pro-rate both interest and principal on first payment based on actual days
                        $firstPaymentInterest = $applyRounding($monthlyInterest * ($daysFromStart / 30), $currency);
                        $principalPay = $applyRounding($monthlyPrincipal * ($daysFromStart / 30), $currency);
                    }

                    $principalPay = min($principalPay, $remainingBalance);

                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $applyRounding($principalPay + $firstPaymentInterest + $feePay, $currency);
                    if ($paymentAmt != ($principalPay + $firstPaymentInterest + $feePay)) {
                        $firstPaymentInterest = max(0, $paymentAmt - $principalPay - $feePay);
                    }
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $firstPaymentInterest,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                } elseif ($i == $totalPayments) {
                    $finalInterestPercent = $isFirst ? $firstPayPercent : $secondPayPercent;
                    $finalInterest = $applyRounding($monthlyInterest * ($finalInterestPercent / 100), $currency);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $applyRounding($remainingBalance + $finalInterest + $feePay, $currency);
                    if ($paymentAmt != ($remainingBalance + $finalInterest + $feePay)) {
                        $finalInterest = max(0, $paymentAmt - $remainingBalance - $feePay);
                    }
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $remainingBalance,
                        'interest' => $finalInterest,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    $principalPay = $applyRounding($isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $principalPay = min($principalPay, $remainingBalance);
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $applyRounding($principalPay + $interestPay + $feePay, $currency);
                    if ($paymentAmt != ($principalPay + $interestPay + $feePay)) {
                        $interestPay = max(0, $paymentAmt - $principalPay - $feePay);
                    }
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                }
            }

            // Smart Check for 50_50 / 70_30 to prevent final payment spike or dip due to rounding
            if (count($allPayments) > 1) {
                // Check the last 2 payments (both halves of the final month)
                for ($checkIndex = max(0, count($allPayments) - 2); $checkIndex < count($allPayments); $checkIndex++) {
                    $paymentMeta = $paymentDates[$checkIndex] ?? null;
                    $isFirstHalf = $paymentMeta ? (bool) $paymentMeta['is_first_half'] : false;
                    $normalPrincipal = $applyRounding($isFirstHalf ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $normalInterest = $applyRounding($monthlyInterest * ($isFirstHalf ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $normalFee = $calculatePeriodFee($checkIndex + 1, $totalPayments);
                    $normalPayment = $applyRounding($normalPrincipal + $normalInterest + $normalFee, $currency);

                    if ($allPayments[$checkIndex]['payment'] != $normalPayment) {
                        $difference = $allPayments[$checkIndex]['payment'] - $normalPayment;
                        if ($difference > 0) {
                            // Payment is too high: waive interest
                            $allPayments[$checkIndex]['interest'] = max(0, $allPayments[$checkIndex]['interest'] - $difference);
                            $allPayments[$checkIndex]['payment'] = $applyRounding($allPayments[$checkIndex]['principal'] + $allPayments[$checkIndex]['interest'] + $allPayments[$checkIndex]['fee'], $currency);
                        } else {
                            // Payment is too low: pad interest
                            $diffAbs = abs($difference);
                            $allPayments[$checkIndex]['interest'] += $diffAbs;
                            $allPayments[$checkIndex]['payment'] += $diffAbs;
                        }
                    }
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
            $daysFromStart = 0;

            for ($i = 1; $i <= $totalPayments; $i++) {
                $paymentMeta = $paymentDates[$i - 1] ?? null;
                if (!$paymentMeta) {
                    break;
                }

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                if ($i == 1) {
                    $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1;
                    if ($daysFromStart < 15) {
                        // Shortage day: DO NOT pro-rate. Charge normal flat 50%.
                        $interestPay = $applyRounding($monthlyInterest * ($firstPayPercent / 100), $currency);
                    } else {
                        // Excess/Normal day: Pro-rate interest based on actual days
                        $interestPay = $applyRounding($monthlyInterest * ($daysFromStart / 30), $currency);
                    }
                } else {
                    $interestPay = $applyRounding($monthlyInterest * ($isFirst ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                }

                if ($i == $totalPayments) {
                    $principalPay = $remainingBalance;
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $applyRounding($principalPay + $interestPay + $feePay, $currency);
                    if ($paymentAmt != ($principalPay + $interestPay + $feePay)) {
                        $interestPay = max(0, $paymentAmt - $principalPay - $feePay);
                    }
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    break;
                } else {
                    if ($i == 1) {
                        if ($daysFromStart < 15) {
                            // Shortage day: DO NOT pro-rate. Charge normal flat 50% principal.
                            $principalPay = $applyRounding($firstPaymentPrincipal, $currency);
                        } else {
                            // Excess/Normal day: Pro-rate principal based on actual days
                            $principalPay = $applyRounding($monthlyPrincipal * ($daysFromStart / 30), $currency);
                        }
                    } else {
                        $rawPrincipalPay = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                        $principalPay = $applyRounding($rawPrincipalPay, $currency);
                    }
                    $principalPay = min($principalPay, $remainingBalance);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $applyRounding($principalPay + $interestPay + $feePay, $currency);
                    if ($paymentAmt != ($principalPay + $interestPay + $feePay)) {
                        $interestPay = max(0, $paymentAmt - $principalPay - $feePay);
                    }
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                    ];
                    $remainingBalance -= $principalPay;
                }
            }

            // Smart Check for 50_50 / 70_30 to prevent final payment spike due to rounding
            if (count($allPayments) > 1) {
                $lastIndex = count($allPayments) - 1;
                $paymentMeta = $paymentDates[$lastIndex] ?? null;
                $isLastFirstHalf = $paymentMeta ? (bool) $paymentMeta['is_first_half'] : false;
                $normalLastPrincipal = $applyRounding($isLastFirstHalf ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);

                if ($allPayments[$lastIndex]['principal'] > $normalLastPrincipal) {
                    $difference = $allPayments[$lastIndex]['principal'] - $normalLastPrincipal;
                    $allPayments[$lastIndex]['principal'] -= $difference;
                    $allPayments[$lastIndex]['payment'] -= $difference;

                    $allPayments[0]['principal'] += $difference;
                    $allPayments[0]['payment'] += $difference;
                } else {
                    $normalLastInterest = $applyRounding($monthlyInterest * ($isLastFirstHalf ? $firstPayPercent : $secondPayPercent) / 100, $currency);
                    $normalLastFee = $calculatePeriodFee($lastIndex + 1, $totalPayments);
                    $normalLastPayment = $applyRounding($normalLastPrincipal + $normalLastInterest + $normalLastFee, $currency);

                    if ($allPayments[$lastIndex]['payment'] < $normalLastPayment) {
                        $difference = $normalLastPayment - $allPayments[$lastIndex]['payment'];
                        $allPayments[$lastIndex]['interest'] += $difference;
                        $allPayments[$lastIndex]['payment'] += $difference;
                    }
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
            // Separate unbiased rounding exclusively for Annuity to prevent massive ballooning
            $applyAnnuityRounding = function ($amount, $currency) {
                if (strpos($currency, 'KHR') !== false) {
                    $remainder = $amount % 1000;
                    if ($remainder == 0)
                        return $amount;
                    elseif ($remainder < 250)
                        return floor($amount / 1000) * 1000;
                    elseif ($remainder < 750)
                        return floor($amount / 1000) * 1000 + 500;
                    else
                        return ceil($amount / 1000) * 1000;
                }
                return round($amount, 0);
            };

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

            $monthlyPayment = $applyAnnuityRounding($monthlyPayment, $currency);

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

                $monthlyInterest = $applyAnnuityRounding($monthlyInterest, $currency);

                if ($i == 1 && isset($daysFromStart)) {
                    $standardInterest = $remainingBalance * $monthlyInterestRate;
                    $monthlyPrincipal = $monthlyPayment - $standardInterest;
                    $totalPayment = $monthlyPrincipal + $monthlyInterest;
                } else {
                    $monthlyPrincipal = $monthlyPayment - $monthlyInterest;
                    $totalPayment = $monthlyPayment;
                }

                $monthlyPrincipal = $applyAnnuityRounding($monthlyPrincipal, $currency);
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
                    'payment' => $applyAnnuityRounding($totalPayment + $feePay, $currency),
                    'balance' => $remainingBalance,
                ];

                if ($i < $duration) {
                    $advanceMonthlyPaymentDate($currentPaymentDate);
                }
            }

            // Smart Check for annuity_monthly to prevent final payment spike or dip
            if (count($results) > 1) {
                $lastIndex = count($results) - 1;
                $expectedPayment = $applyAnnuityRounding($monthlyPayment + $calculatePeriodFee($duration, $duration), $currency);

                if ($results[$lastIndex]['payment'] != $expectedPayment) {
                    $difference = $results[$lastIndex]['payment'] - $expectedPayment;

                    if ($difference > 0) {
                        // Final payment is too high: waive interest to keep it flat
                        $results[$lastIndex]['interest'] = max(0, $results[$lastIndex]['interest'] - $difference);
                        $results[$lastIndex]['payment'] = $applyAnnuityRounding($results[$lastIndex]['principal'] + $results[$lastIndex]['interest'] + $results[$lastIndex]['fee'], $currency);
                    } else {
                        // Final payment is too low: pad the interest
                        $diffAbs = abs($difference);
                        $results[$lastIndex]['interest'] += $diffAbs;
                        $results[$lastIndex]['payment'] += $diffAbs;
                    }

                    // Recompute balances since principal might have shifted
                    $runningBalance = $principal;
                    foreach ($results as $idx => &$pay) {
                        if ($idx === count($results) - 1) {
                            $pay['balance'] = 0;
                        } else {
                            $runningBalance -= $pay['principal'];
                            $pay['balance'] = max(0, $runningBalance);
                        }
                    }
                    unset($pay);
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
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $monthlyInterest = $remainingBalance * ($monthlyInterestRate / 30) * $daysFromStart;
                } else {
                    $monthlyInterest = $remainingBalance * $monthlyInterestRate;
                }

                $monthlyInterest = $applyRounding($monthlyInterest, $currency);

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;
                    // Smart Check: Adjust interest to keep final payment flat
                    if ($currentPrincipal != $monthlyPrincipal) {
                        $diff = $currentPrincipal - $monthlyPrincipal;
                        if ($diff > 0) {
                            // Principal is higher: waive interest
                            $adjustedInterest = $monthlyInterest - $diff;
                            $currentInterest = max(0, $adjustedInterest);
                        } else {
                            // Principal is lower: pad interest
                            $currentInterest = $monthlyInterest + abs($diff);
                        }
                    } else {
                        $currentInterest = $monthlyInterest;
                    }
                } else {
                    $currentPrincipal = min($monthlyPrincipal, $remainingBalance);
                    $currentInterest = $monthlyInterest;
                }

                $monthlyPayment = $applyRounding($currentPrincipal + $currentInterest, $currency);
                $remainingBalance = max(0, $remainingBalance - $currentPrincipal);

                $feePay = $calculatePeriodFee($i, $duration);
                $results[] = [
                    'period' => $i,
                    'date' => $currentPaymentDate->format('d/m/Y'),
                    'principal' => $currentPrincipal,
                    'interest' => $currentInterest,
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

                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $rawInterest = $principal * (($rate / 100) / 30) * $daysFromStart;
                    $currentInterest = $applyRounding($rawInterest, $currency);
                } else {
                    $currentInterest = $monthlyInterest;
                }

                if ($i == $duration) {
                    $currentPrincipal = $remainingBalance;

                    if ($duration > 1) {
                        // Smart Check: Adjust interest to keep final payment flat
                        if ($currentPrincipal != $monthlyPrincipal) {
                            $diff = $currentPrincipal - $monthlyPrincipal;
                            if ($diff > 0) {
                                // Principal is higher: waive interest
                                $adjustedInterest = $monthlyInterest - $diff;
                                $currentInterest = max(0, $adjustedInterest);
                            } else {
                                // Principal is lower: pad interest
                                $currentInterest = $monthlyInterest + abs($diff);
                            }
                        } else {
                            $currentInterest = $monthlyInterest;
                        }
                    }

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
                if ($i == 1) {
                    $daysFromStart = $currentPaymentDate->diff($loanStartDate)->days + 1; // Inclusive days
                    $rawInterest = $principal * (($rate / 100) / 30) * $daysFromStart;
                    $currentInterest = $applyRounding($rawInterest, $currency);
                } else {
                    $currentInterest = $applyRounding($monthlyInterest, $currency);
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
                    // Smart Check: Only adjust if final principal is higher than normal
                    if ($currentPrincipal > $monthlyPrincipal) {
                        $diff = $currentPrincipal - $monthlyPrincipal;
                        $adjustedInterest = $monthlyInterest - $diff;
                        $currentInterest = max(0, $adjustedInterest);
                    } else {
                        $currentInterest = $monthlyInterest;
                    }
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

