<?php

require __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;

$today = Carbon::parse('2026-02-08');
$past = Carbon::parse('2025-02-26'); // Approx 347 days ago?
// 2026-02-08 -> 2025-02-08 is 365 days.
// 2025-02-26 is later than 2025-02-08. So < 365.
// 365 - 18 = 347.

echo "Today: " . $today->toDateString() . "\n";
echo "Past: " . $past->toDateString() . "\n";

$diff1 = $today->diffInDays($past);
echo "Today -> Past (default): " . $diff1 . "\n";

$diff2 = $today->diffInDays($past, false);
echo "Today -> Past (false): " . $diff2 . "\n";

$diff3 = $past->diffInDays($today, false);
echo "Past -> Today (false): " . $diff3 . "\n";
