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

        $buildFixedIntervalSchedule = function (int $intervalDays, ?int $totalPaymentsOverride = null, bool $ratePerPayment = false, bool $normalizeFinalPayment = false) use ($principal, $rate, $duration, $startDateObj, $applyRounding, $roundInterest, $calculatePeriodFee, $currency, $firstRepaymentDate) {
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

        // Smart Check for split schedules: normalize each pay-day lane
        // independently. A short principal row is topped up through interest,
        // matching the behavior of the other fixed repayment methods.
        $normalizeSplitPayments = function (array $payments): array {
            $targets = [];

            foreach ($payments as $payment) {
                if (!empty($payment['is_first_payment'])) {
                    continue;
                }

                $slot = (string) ($payment['split_slot'] ?? 'default');
                $targets[$slot] = max($targets[$slot] ?? 0, (float) $payment['payment']);
            }

            foreach ($payments as &$payment) {
                $slot = (string) ($payment['split_slot'] ?? 'default');
                $target = $targets[$slot] ?? (float) $payment['payment'];
                $shortfall = !empty($payment['is_first_payment'])
                    ? 0
                    : max(0, $target - (float) $payment['payment']);

                if ($shortfall > 0) {
                    $payment['interest'] += $shortfall;
                    $payment['payment'] = $target;
                }

                unset($payment['split_slot']);
                unset($payment['is_first_payment']);
            }
            unset($payment);

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

        if (strpos($option, 'fixed_15days_70_30') !== false) {
            $percentages = explode('_', $option);
            $firstPayPercent = (int) ($percentages[2] ?? 70);
            $secondPayPercent = (int) ($percentages[3] ?? 30);

            $totalPayments = $duration * 2;
            $monthlyPrincipal = $principal / $duration;
            $monthlyInterest = $principal * ($rate / 100);



            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);
            $firstPaymentInterest = $monthlyInterest * ($firstPayPercent / 100);
            $secondPaymentInterest = $monthlyInterest * ($secondPayPercent / 100);

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

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                $interestPayRaw = $isFirst ? $firstPaymentInterest : $secondPaymentInterest;
                $interestPay = $roundInterest($interestPayRaw, $currency);

                if ($i == $totalPayments) {
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $remainingBalance + $interestPay + $feePay;
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $remainingBalance,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                        'split_slot' => $isFirst ? 'first' : 'second',
                        'is_first_payment' => $i === 1,
                    ];
                    break;
                } else {
                    $principalPayRaw = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;

                    if ($i === 1) {
                        $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1;
                        if ($daysFromStart >= 15) {
                            $principalPayRaw = $monthlyPrincipal * ($daysFromStart / 30);
                        }
                        $principalPay = $roundCumulativePrincipal($principalPayRaw, $currency);
                    } else {
                        $exactCumulativePrincipal += $principalPayRaw;
                        $targetCumulativePrincipal = min(
                            $principal,
                            $roundCumulativePrincipal($exactCumulativePrincipal, $currency)
                        );
                        $principalPay = max(0, $targetCumulativePrincipal - $principalPaidSoFar);
                    }

                    $principalPay = min($principalPay, $remainingBalance);
                    
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $principalPay + $interestPay + $feePay;
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                        'split_slot' => $isFirst ? 'first' : 'second',
                        'is_first_payment' => $i === 1,
                    ];
                    $remainingBalance -= $principalPay;
                    if ($i > 1) {
                        $principalPaidSoFar += $principalPay;
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
            $results = $normalizeSplitPayments($allPayments);
        } elseif ($option === 'fixed_15days_50_50') {
            $firstPayPercent = 50;
            $secondPayPercent = 50;

            $totalPayments = $duration * 2;
            $monthlyPrincipal = $principal / $duration;
            $monthlyInterest = $principal * ($rate / 100);

            $firstPaymentPrincipal = $monthlyPrincipal * ($firstPayPercent / 100);
            $secondPaymentPrincipal = $monthlyPrincipal * ($secondPayPercent / 100);
            $firstPaymentInterest = $monthlyInterest * ($firstPayPercent / 100);
            $secondPaymentInterest = $monthlyInterest * ($secondPayPercent / 100);

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

                /** @var DateTime $currentPaymentDate */
                $currentPaymentDate = clone $paymentMeta['date'];
                $isFirst = (bool) $paymentMeta['is_first_half'];

                $interestPayRaw = $isFirst ? $firstPaymentInterest : $secondPaymentInterest;
                $interestPay = $roundInterest($interestPayRaw, $currency);

                if ($i == $totalPayments) {
                    $principalPay = $remainingBalance;
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $principalPay + $interestPay + $feePay;
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => 0,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                        'split_slot' => $isFirst ? 'first' : 'second',
                        'is_first_payment' => $i === 1,
                    ];
                    break;
                } else {
                    $principalPayRaw = $isFirst ? $firstPaymentPrincipal : $secondPaymentPrincipal;

                    if ($i === 1) {
                        $daysFromStart = $loanStartDate->diff($currentPaymentDate)->days + 1;
                        if ($daysFromStart >= 15) {
                            $principalPayRaw = $monthlyPrincipal * ($daysFromStart / 30);
                        }
                        $principalPay = $roundCumulativePrincipal($principalPayRaw, $currency);
                    } else {
                        $exactCumulativePrincipal += $principalPayRaw;
                        $targetCumulativePrincipal = min(
                            $principal,
                            $roundCumulativePrincipal($exactCumulativePrincipal, $currency)
                        );
                        $principalPay = max(0, $targetCumulativePrincipal - $principalPaidSoFar);
                    }

                    $principalPay = min($principalPay, $remainingBalance);
                    $feePay = $calculatePeriodFee($i, $totalPayments);
                    $paymentAmt = $principalPay + $interestPay + $feePay;
                    $allPayments[] = [
                        'period' => $i,
                        'date' => $currentPaymentDate->format('Y-m-d'),
                        'principal' => $principalPay,
                        'interest' => $interestPay,
                        'fee' => $feePay,
                        'payment' => $paymentAmt,
                        'balance' => null,
                        'order' => (int) $currentPaymentDate->format('Ymd'),
                        'split_slot' => $isFirst ? 'first' : 'second',
                        'is_first_payment' => $i === 1,
                    ];
                    $remainingBalance -= $principalPay;
                    if ($i > 1) {
                        $principalPaidSoFar += $principalPay;
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
            $results = $normalizeSplitPayments($allPayments);
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

            $monthlyPayment = $exactAmount($monthlyPayment);

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

        } elseif ($option === 'linear_monthly') {
            $monthlyInterestRate = $rate / 100;
            $monthlyPrincipal = $exactAmount($principal / $duration);

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
            $monthlyPrincipal = $exactAmount($monthlyPrincipal);

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
            $monthlyPrincipal = $exactAmount($monthlyPrincipal);

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
