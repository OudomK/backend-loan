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

        // Principal installments in KHR are rounded to whole thousand riel.
        $roundScheduledPrincipal = function ($amount, $currency) {
            if (strpos($currency, 'KHR') !== false) {
                return ceil($amount / 1000) * 1000;
            }

            return ceil($amount);
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

        $buildFixedIntervalSchedule = function (int $intervalDays, ?int $totalPaymentsOverride = null, bool $ratePerPayment = false, bool $normalizeFinalPayment = false) use ($principal, $rate, $duration, $startDateObj, $applyRounding, $calculatePeriodFee, $currency, $firstRepaymentDate) {
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
                    $currentPaymentDate->add(new DateInterval('P' . ($intervalDays * $i) . 'D'));
                }

                $feePay = $calculatePeriodFee($i, $totalPayments);

                if ($i === $totalPayments) {
                    $principalPay = $remainingBalanceLocal;
                    $standardPrincipalPay = $applyRounding($periodPrincipalRaw, $currency);

                    if ($normalizeFinalPayment) {
                        $standardInterestPay = $applyRounding($periodInterestRaw, $currency);
                        $standardPayment = $applyRounding($standardPrincipalPay + $standardInterestPay + $feePay, $currency);
                        $interestPay = max(0, $standardPayment - $principalPay - $feePay);
                    } elseif ($principalPay > $standardPrincipalPay) {
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
            $periodInterest = $principal * ($rate / 100);



            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);

            $remainingBalance = $principal;
            $exactCumulativePrincipal = 0;
            $principalPaidSoFar = 0;
            $allPayments = [];
            $loanStartDate = clone $startDateObj;
            $paymentDates = $buildSemiMonthlyDates($loanStartDate, $totalPayments, $payDay1, $payDay2);

            for ($i = 1; $i <= $totalPayments; $i++) {
                $paymentMeta = $paymentDates[$i - 1] ?? null;
                if (!$paymentMeta) {
                    break;
                }

                if ($i > 1 && $remainingBalance <= 0) {
                    break;
                }

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                if ($i == 1) {
                    $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1; // +1 for inclusive days

                    if ($daysFromStart < 15) {
                        // Shortage day: DO NOT pro-rate. Charge normal flat amount.
                        $firstPaymentInterest = $applyRounding($periodInterest, $currency);
                        $principalPayRaw = $firstPaymentPrincipal;
                    } else {
                        // Excess/Normal day: Pro-rate both interest and principal on first payment based on actual days
                        $firstPaymentInterest = $applyRounding($periodInterest, $currency);
                        $principalPayRaw = $monthlyPrincipal * ($daysFromStart / 30);
                    }

                    // Split remaining balance in the final month to avoid $0 payment on the second half
                    if ($isFirst) {
                        $normalFirst = $roundScheduledPrincipal($firstPaymentPrincipal, $currency);
                        $normalSecond = $roundScheduledPrincipal($secondPaymentPrincipal, $currency);
                        $fullMonthPrincipal = $normalFirst + $normalSecond;
                        
                        if ($remainingBalance < $fullMonthPrincipal) {
                            $principalPayRaw = $remainingBalance * ($firstPayPercent / 100);
                        }
                    }

                    $principalPay = $roundScheduledPrincipal($principalPayRaw, $currency);
                    
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
                    $finalInterest = $applyRounding($periodInterest, $currency);
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
                    $principalPayRaw = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    
                    // Split remaining balance in the final month to avoid $0 payment on the second half
                    if ($isFirst) {
                        $normalFirst = $roundScheduledPrincipal($firstPaymentPrincipal, $currency);
                        $normalSecond = $roundScheduledPrincipal($secondPaymentPrincipal, $currency);
                        $fullMonthPrincipal = $normalFirst + $normalSecond;
                        
                        if ($remainingBalance < $fullMonthPrincipal) {
                            $principalPayRaw = $remainingBalance * ($firstPayPercent / 100);
                        }
                    }

                    $principalPay = $roundScheduledPrincipal($principalPayRaw, $currency);
                    $principalPay = min($principalPay, $remainingBalance);
                    
                    $interestPay = $applyRounding($periodInterest, $currency);
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

            // Normalize both half-payments in the final printed month.
            if (count($allPayments) > 0) {
                $firstCheckIndex = max(0, count($allPayments) - 2);

                for ($checkIndex = $firstCheckIndex; $checkIndex < count($allPayments); $checkIndex++) {
                    $paymentMeta = $paymentDates[$checkIndex] ?? null;
                    $isFirstHalf = $paymentMeta ? (bool) $paymentMeta['is_first_half'] : false;
                    $normalPrincipal = $applyRounding($isFirstHalf ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $normalInterest = $applyRounding($periodInterest, $currency);
                    $normalFee = $calculatePeriodFee($checkIndex + 1, $totalPayments);
                    $normalPayment = $applyRounding($normalPrincipal + $normalInterest + $normalFee, $currency);

                    $allPayments[$checkIndex]['interest'] = max(0, $normalPayment - $allPayments[$checkIndex]['principal'] - $allPayments[$checkIndex]['fee']);
                    $allPayments[$checkIndex]['payment'] = $applyRounding(
                        $allPayments[$checkIndex]['principal'] + $allPayments[$checkIndex]['interest'] + $allPayments[$checkIndex]['fee'],
                        $currency
                    );
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
            $periodInterest = $principal * ($rate / 100);

            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);

            $remainingBalance = $principal;
            $exactCumulativePrincipal = 0;
            $principalPaidSoFar = 0;
            $allPayments = [];
            $loanStartDate = clone $startDateObj;
            $paymentDates = $buildSemiMonthlyDates($loanStartDate, $totalPayments, $payDay1, $payDay2);
            $daysFromStart = 0;

            for ($i = 1; $i <= $totalPayments; $i++) {
                $paymentMeta = $paymentDates[$i - 1] ?? null;
                if (!$paymentMeta) {
                    break;
                }

                if ($i > 1 && $remainingBalance <= 0) {
                    break;
                }

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                if ($i == 1) {
                    $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1;
                }

                // The configured rate is charged once for every half-payment.
                $interestPay = $applyRounding($periodInterest, $currency);

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
                            $principalPayRaw = $firstPaymentPrincipal;
                        } else {
                            // Excess/Normal day: Pro-rate principal based on actual days
                            $principalPayRaw = $monthlyPrincipal * ($daysFromStart / 30);
                        }
                    } else {
                        $principalPayRaw = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;
                    }
                    
                    // Split remaining balance in the final month to avoid $0 payment on the second half
                    if ($isFirst) {
                        $normalFirst = $roundScheduledPrincipal($firstPaymentPrincipal, $currency);
                        $normalSecond = $roundScheduledPrincipal($secondPaymentPrincipal, $currency);
                        $fullMonthPrincipal = $normalFirst + $normalSecond;
                        
                        if ($remainingBalance < $fullMonthPrincipal) {
                            $principalPayRaw = $remainingBalance * ($firstPayPercent / 100);
                        }
                    }
                    
                    $principalPay = $roundScheduledPrincipal($principalPayRaw, $currency);
                    
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

            // Normalize both half-payments in the final printed month.
            if (count($allPayments) > 0) {
                $firstCheckIndex = max(0, count($allPayments) - 2);

                for ($checkIndex = $firstCheckIndex; $checkIndex < count($allPayments); $checkIndex++) {
                    $paymentMeta = $paymentDates[$checkIndex] ?? null;
                    $isFirstHalf = $paymentMeta ? (bool) $paymentMeta['is_first_half'] : false;
                    $normalPrincipal = $applyRounding($isFirstHalf ? $firstPaymentPrincipal : $secondPaymentPrincipal, $currency);
                    $normalInterest = $applyRounding($periodInterest, $currency);
                    $normalFee = $calculatePeriodFee($checkIndex + 1, $totalPayments);
                    $normalPayment = $applyRounding($normalPrincipal + $normalInterest + $normalFee, $currency);

                    $allPayments[$checkIndex]['interest'] = max(0, $normalPayment - $allPayments[$checkIndex]['principal'] - $allPayments[$checkIndex]['fee']);
                    $allPayments[$checkIndex]['payment'] = $applyRounding(
                        $allPayments[$checkIndex]['principal'] + $allPayments[$checkIndex]['interest'] + $allPayments[$checkIndex]['fee'],
                        $currency
                    );
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
            // Daily rate is charged once for every repayment and the final row is normalized.
            $results = $buildFixedIntervalSchedule(1, $duration, true, true);
        } elseif ($option === 'fixed_biweekly') {
            // Biweekly rate is charged once for every repayment and the final row is normalized.
            $results = $buildFixedIntervalSchedule(14, $duration, true, true);
        } elseif ($option === 'fixed_weekly') {
            // Weekly rate is charged once for every repayment and the final row is normalized.
            $results = $buildFixedIntervalSchedule(7, $duration, true, true);
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
                            $currentInterest = $monthlyInterest;
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
                        // Keep the final installment aligned with a normal monthly installment.
                        $feePay = $calculatePeriodFee($i, $duration);
                        $standardPayment = $applyRounding($monthlyPrincipal + $monthlyInterest + $feePay, $currency);
                        $currentInterest = max(0, $standardPayment - $currentPrincipal - $feePay);
                    }

                    $remainingBalance = 0;

                } else {
                    $currentPrincipal = min($currentPrincipal, $remainingBalance);
                    $remainingBalance = max(0, $remainingBalance - $currentPrincipal);
                }

                $feePay = $feePay ?? $calculatePeriodFee($i, $duration);
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
    public static function formatScheduleForPrint(array $schedule, string $repaymentMethod, float $loanAmount): array
    {
        $rows = [];
        $isBimonthly = str_contains(strtolower($repaymentMethod), '70_30') || 
                       str_contains(strtolower($repaymentMethod), '70/30') || 
                       str_contains(strtolower($repaymentMethod), '50_50') || 
                       str_contains(strtolower($repaymentMethod), '50/50') || 
                       str_contains(strtolower($repaymentMethod), 'biweekly') || 
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
