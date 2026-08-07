<?php
$calc = app(App\Services\LoanCalculator::class);
$res = $calc->calculateLoanWithDates(3700, 2, 60, 'Annuity (Bimonthly)', '2030-11-20', 'USD', 0, 'one_time', 11, 26, '2030-11-26');
$formatted = \App\Services\LoanCalculator::formatScheduleForPrint($res, 'Annuity (Bimonthly)', 3700);
file_put_contents('output_calc.json', json_encode($res, JSON_PRETTY_PRINT));
file_put_contents('output_format.json', json_encode($formatted, JSON_PRETTY_PRINT));
echo "Done";
