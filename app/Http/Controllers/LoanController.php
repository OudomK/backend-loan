<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\BalloonPaymentCalculator;
use App\Services\CommissionIncomeService;

class LoanController extends Controller
{
    protected \App\Services\LoanCalculator $calculator;
    protected CommissionIncomeService $commissionIncomeService;

    public function __construct(\App\Services\LoanCalculator $calculator, CommissionIncomeService $commissionIncomeService)
    {
        $this->calculator = $calculator;
        $this->commissionIncomeService = $commissionIncomeService;
    }

    public function getPaymentQrs()
    {
        return response()->json(\App\Models\PaymentQr::where('is_active', true)->get());
    }

    /** Normalize schedule date (d/m/Y or Y-m-d) to Y-m-d for DB. */
    private function normalizeScheduleDate(string $date): string
    {
        if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $date)) {
            $parsed = Carbon::createFromFormat('d/m/Y', $date);
            return $parsed ? $parsed->format('Y-m-d') : $date;
        }
        return $date;
    }

    public function index()
    {
        return response()->json(Loan::with(['borrower', 'coBorrower', 'guarantor', 'officer', 'collaterals', 'product', 'paymentQr'])->get());
    }

    /**
     * Suggest loan code + cycle for a borrower (e.g. QF-001-C1, QF-001-C2).
     * GET /loans/suggest-code?borrower_id=1
     */
    public function suggestCode(Request $request)
    {
        $request->validate(['borrower_id' => 'required|exists:borrowers,id']);
        $borrowerId = $request->input('borrower_id');
        $borrower = Borrower::withoutGlobalScopes()->find($borrowerId);
        $customerCode = $borrower ? trim($borrower->customer_code ?? '') : '';
        if ($customerCode === '') {
            $customerCode = 'L' . str_pad((string) $borrowerId, 3, '0', STR_PAD_LEFT);
        }
        $cycle = Loan::where('borrower_id', $borrowerId)->count() + 1;
        $suggestedLoanCode = $customerCode . '-C' . $cycle;
        return response()->json([
            'cycle' => $cycle,
            'suggested_loan_code' => $suggestedLoanCode,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'amount' => 'nullable|numeric',
            'interest_rate' => 'nullable|numeric',
            'duration_months' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'status' => 'required|in:pending,active,completed,paid_off',
            'currency' => 'nullable|string',
            'repayment_method' => 'nullable|string',
            'purpose' => 'nullable|string|max:255',
            'loan_code' => 'nullable|string',
            'payment_frequency' => 'nullable|string',
            'loan_officer_id' => 'nullable|exists:loan_officers,id',
            'admin_fee' => 'nullable|numeric',
            'admin_fee_type' => 'nullable|string|in:one_time,monthly,deducted_upfront,capitalized_upfront',
            'co_borrower_id' => 'nullable|exists:co_borrowers,id',
            'co_borrower_relationship' => 'nullable|string',
            'guarantor_id' => 'nullable|exists:guarantors,id',
            'guarantor_relationship' => 'nullable|string',
            'product_id' => 'nullable|exists:loan_products,id',
            'collaterals' => 'nullable|array',
            'collaterals.*.type' => 'nullable|string',
            'collaterals.*.certificate_number' => 'nullable|string',
            'collaterals.*.license_plate' => 'nullable|string',
            'collaterals.*.owner_name' => 'nullable|string',
            'collaterals.*.value' => 'nullable|numeric',
            'collaterals.*.currency' => 'nullable|string',
            'collaterals.*.description' => 'nullable|string',
            'custom_schedule' => 'nullable|array', // For negotiable loans
            'payment_qr_id' => 'nullable|exists:payment_qrs,id',
            'pay_day_1' => 'nullable|integer|min:1|max:31',
            'pay_day_2' => 'nullable|integer|min:1|max:31',
        ]);

        $requestedAmount = (float) ($validated['amount'] ?? 0);
        $adminFeePercent = (float) ($request->input('admin_fee') ?? $validated['admin_fee'] ?? 0);
        $adminFeeValue = ($requestedAmount * $adminFeePercent) / 100;

        $feeType = $request->input('admin_fee_type') ?: ($validated['admin_fee_type'] ?? 'one_time');

        $validated['admin_fee'] = $adminFeePercent;
        $validated['admin_fee_type'] = $feeType;

        if ($feeType === 'deducted_upfront') {
            $validated['disbursed_amount'] = round($requestedAmount - $adminFeeValue, 2);
            $validated['amount'] = $requestedAmount; // Schedule runs on requested amount
        } elseif ($feeType === 'capitalized_upfront') {
            $validated['disbursed_amount'] = $requestedAmount;
            $validated['amount'] = round($requestedAmount + $adminFeeValue, 2); // Schedule runs on higher amount
        } else {
            $validated['disbursed_amount'] = $requestedAmount;
            $validated['amount'] = $requestedAmount;
        }

        // Calculate Cycle
        $cycle = Loan::where('borrower_id', $validated['borrower_id'])->count() + 1;
        $validated['loan_cycle'] = $cycle;

        if (isset($validated['loan_officer_id'])) {
            $validated['disbursed_by_officer_id'] = $validated['loan_officer_id'];
        }

        // Calculate monthly interest for persistence
        if (isset($validated['amount']) && isset($validated['interest_rate'])) {
            $validated['monthly_interest'] = round(($validated['amount'] * $validated['interest_rate']) / 100, 2);
        }

        DB::beginTransaction();
        try {
            $loan = Loan::create($validated);
            $this->commissionIncomeService->syncForLoan($loan);

            // Save collaterals if any
            if (isset($validated['collaterals'])) {
                foreach ($validated['collaterals'] as $collateralData) {
                    // Only save if type or value is provided
                    if (!empty($collateralData['type']) || !empty($collateralData['value'])) {
                        $loan->collaterals()->create($collateralData);
                    }
                }
            }

            $requiresSchedule =
                !empty($validated['amount']) &&
                !empty($validated['interest_rate']) &&
                !empty($validated['duration_months']) &&
                !empty($validated['repayment_method']) &&
                !empty($validated['start_date']);

            // Calculate schedule if essential data is provided
            if ($requiresSchedule) {
                // Handle Negotiable (Custom Schedule)
                if ($validated['repayment_method'] === 'negotiable' && !empty($validated['custom_schedule'])) {
                    $schedule = $validated['custom_schedule'];

                    // Use the first payment as monthly reference (though it varies)
                    $loan->update(['monthly_payment' => $schedule[0]['payment'] ?? 0]);

                    foreach ($schedule as $item) {
                        $loan->payments()->create([
                            'payment_number' => $item['period'],
                            'principal_amount' => $item['principal'],
                            'interest_amount' => $item['interest'],
                            'fee_amount' => $item['fee'] ?? 0,
                            'total_due' => round(($item['principal'] ?? 0) + ($item['interest'] ?? 0) + ($item['fee'] ?? 0), 2),
                            'penalty_amount' => 0,
                            'total_paid' => $item['payment'],
                            'payment_date' => $this->normalizeScheduleDate($item['date']),
                            'payment_method' => 'Cash',
                        ]);
                    }
                }
                // Use Balloon calculator for Balloon repayment method
                else if ($validated['repayment_method'] === 'Balloon') {
                    $loanData = [
                        'amount' => $validated['amount'],
                        'interest_rate' => $validated['interest_rate'],
                        'duration_months' => $validated['duration_months'],
                        'start_date' => $validated['start_date'],
                    ];

                    // Generate interest-only balloon schedule by default
                    $schedule = BalloonPaymentCalculator::generateSchedule(
                        $loanData,
                        'interest_only',
                        null,
                        $validated['pay_day_1'] ?? null,
                        $validated['admin_fee'] ?? 0,
                        $validated['admin_fee_type'] ?? 'one_time'
                    );

                    if (!empty($schedule)) {
                        // Update monthly payment reference (first payment)
                        $loan->update(['monthly_payment' => $schedule[0]['total_paid'] ?? 0]);

                        // Save schedule as payments
                        foreach ($schedule as $payment) {
                            $loan->payments()->create([
                                'payment_number' => $payment['payment_number'],
                                'principal_amount' => $payment['principal_amount'],
                                'interest_amount' => $payment['interest_amount'],
                                'fee_amount' => $payment['fee_amount'] ?? 0,
                                'total_due' => round(($payment['principal_amount'] ?? 0) + ($payment['interest_amount'] ?? 0) + ($payment['fee_amount'] ?? 0), 2),
                                'penalty_amount' => $payment['penalty_amount'],
                                'total_paid' => $payment['total_paid'],
                                'payment_date' => $payment['payment_date'],
                                'payment_method' => 'Cash',
                            ]);
                        }
                    }
                } else {
                    // Use existing calculator for other repayment methods
                    $schedule = $this->calculator->calculateLoanWithDates(
                        $validated['amount'],
                        $validated['interest_rate'],
                        $validated['duration_months'],
                        $validated['repayment_method'],
                        $validated['start_date'],
                        $validated['currency'] ?? 'USD',
                        $validated['admin_fee'] ?? 0,
                        $validated['admin_fee_type'] ?? 'one_time',
                        $validated['pay_day_1'] ?? null,
                        $validated['pay_day_2'] ?? null,
                    );

                    if (!empty($schedule)) {
                        // Update monthly payment reference
                        $loan->update(['monthly_payment' => $schedule[0]['payment'] ?? 0]);

                        // Save schedule as payments
                        foreach ($schedule as $item) {
                            $loan->payments()->create([
                                'payment_number' => $item['period'],
                                'principal_amount' => $item['principal'],
                                'interest_amount' => $item['interest'],
                                'fee_amount' => $item['fee'] ?? 0,
                                'total_due' => round(($item['principal'] ?? 0) + ($item['interest'] ?? 0) + ($item['fee'] ?? 0), 2),
                                'penalty_amount' => 0,
                                'total_paid' => 0,
                                'payment_date' => $this->normalizeScheduleDate($item['date']),
                                'payment_method' => 'Cash',
                            ]);
                        }
                    }
                }

                $savedPaymentsCount = $loan->payments()->count();
                if ($savedPaymentsCount === 0) {
                    throw new \RuntimeException('Schedule generation failed: no payment rows were created. Please verify repayment method, dates, and terms.');
                }
            }

            DB::commit();
            return response()->json($loan->load(['borrower', 'coBorrower', 'guarantor', 'officer', 'collaterals', 'payments', 'product', 'paymentQr']), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Loan create failed: " . $e->getMessage());
            return response()->json([
                'error' => 'Create loan failed.',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function previewSchedule(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'interest_rate' => 'required|numeric',
            'duration_months' => 'required|integer',
            'repayment_method' => 'required|string',
            'start_date' => 'required|date',
            'currency' => 'nullable|string',
            'admin_fee' => 'nullable|numeric',
            'admin_fee_type' => 'nullable|string|in:one_time,monthly,deducted_upfront,capitalized_upfront',
            'pay_day_1' => 'nullable|integer|min:1|max:31',
            'pay_day_2' => 'nullable|integer|min:1|max:31',
        ]);

        try {
            if ($validated['repayment_method'] === 'Balloon') {
                $loanData = [
                    'amount' => $validated['amount'],
                    'interest_rate' => $validated['interest_rate'],
                    'duration_months' => $validated['duration_months'],
                    'start_date' => $validated['start_date'],
                ];
                // Generate interest-only balloon schedule by default
                $scheduleRaw = BalloonPaymentCalculator::generateSchedule(
                    $loanData,
                    'interest_only',
                    null,
                    $validated['pay_day_1'] ?? null,
                    $validated['admin_fee'] ?? 0,
                    $validated['admin_fee_type'] ?? 'one_time'
                );

                // Map to format expected by frontend
                $schedule = array_map(function ($item) {
                    return [
                        'period' => $item['payment_number'],
                        'date' => $item['payment_date'],
                        'principal' => $item['principal_amount'],
                        'interest' => $item['interest_amount'],
                        'payment' => $item['total_paid'],
                        'balance' => $item['remaining_balance'] ?? 0,
                        'is_balloon' => $item['is_balloon'] ?? false,
                    ];
                }, $scheduleRaw);
            } else {
                $scheduleAmount = (float) $validated['amount'];
                $feeType = $validated['admin_fee_type'] ?? 'one_time';
                if ($feeType === 'capitalized_upfront') {
                    $adminFeePercent = (float) ($validated['admin_fee'] ?? 0);
                    $adminFeeValue = ($scheduleAmount * $adminFeePercent) / 100;
                    $scheduleAmount += $adminFeeValue;
                }

                $schedule = $this->calculator->calculateLoanWithDates(
                    $scheduleAmount,
                    $validated['interest_rate'],
                    $validated['duration_months'],
                    // For negotiable, default to fixed_monthly as a starting point
                    $validated['repayment_method'] === 'negotiable' ? 'fixed_monthly' : $validated['repayment_method'],
                    $validated['start_date'],
                    $validated['currency'] ?? 'USD',
                    $validated['admin_fee'] ?? 0,
                    $feeType,
                    $validated['pay_day_1'] ?? null,
                    $validated['pay_day_2'] ?? null,
                );
            }

            return response()->json($schedule);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Loan $loan)
    {
        return response()->json($loan->load([
            'borrower',
            'coBorrower',
            'guarantor',
            'officer',
            'collaterals',
            'payments',
            'product',
            'paymentQr'
        ]));
    }

    public function update(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'borrower_id' => 'sometimes|required|exists:borrowers,id',
            'amount' => 'sometimes|required|numeric',
            'interest_rate' => 'sometimes|required|numeric',
            'duration_months' => 'sometimes|required|integer',
            'monthly_payment' => 'sometimes|required|numeric',
            'start_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,active,completed,paid_off',
            'purpose' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric',
            'admin_fee_type' => 'nullable|string|in:one_time,monthly,deducted_upfront,capitalized_upfront',
            'co_borrower_id' => 'nullable|exists:co_borrowers,id',
            'co_borrower_relationship' => 'nullable|string',
            'guarantor_id' => 'nullable|exists:guarantors,id',
            'guarantor_relationship' => 'nullable|string',
            'product_id' => 'nullable|exists:loan_products,id',
        ]);

        $loan->update($validated);
        return response()->json($loan->load(['borrower', 'coBorrower', 'guarantor']));
    }

    public function destroy(Loan $loan)
    {
        $loan->delete();
        return response()->json(null, 204);
    }
}
