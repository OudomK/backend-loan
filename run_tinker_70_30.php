<?php
$calc = app(App\Services\LoanCalculator::class);
$res = $calc->calculateLoanWithDates(3700, 2, 60, 'fixed_15days_70_30', '2026-08-01', 'USD', 0, 'one_time', 11, 26, '2026-08-11');
$formatted = \App\Services\LoanCalculator::formatScheduleForPrint($res, 'fixed_15days_70_30', 3700);
file_put_contents('output_70_30.json', json_encode($formatted, JSON_PRETTY_PRINT));
echo "Done";
