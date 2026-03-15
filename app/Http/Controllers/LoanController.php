<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\BalloonPaymentCalculator;

class LoanController extends Controller
{
    protected $calculator;

    public function __construct(\App\Services\LoanCalculator $calculator)
    {
        $this->calculator = $calculator;
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
        return response()->json(Loan::with(['borrower', 'coBorrower', 'guarantor', 'officer', 'collaterals', 'product'])->get());
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
            'admin_fee_type' => 'nullable|string|in:one_time,monthly',
            'co_borrower_id' => 'nullable|exists:co_borrowers,id',
            'co_borrower_relationship' => 'nullable|string',
            'guarantor_id' => 'nullable|exists:co_borrowers,id', // Note: borrower relationship table
            'guarantor_relationship' => 'nullable|string',
            'product_id' => 'nullable|exists:loan_products,id',
            'collaterals' => 'nullable|array',
            'collaterals.*.type' => 'nullable|string',
            'collaterals.*.owner_name' => 'nullable|string',
            'collaterals.*.value' => 'nullable|numeric',
            'collaterals.*.currency' => 'nullable|string',
            'collaterals.*.description' => 'nullable|string',
            'custom_schedule' => 'nullable|array', // For negotiable loans
        ]);

        // Ensure admin_fee is always set from request (fee %)
        $validated['admin_fee'] = (float) ($request->input('admin_fee') ?? $validated['admin_fee'] ?? 0);
        $validated['admin_fee_type'] = $request->input('admin_fee_type') ?: ($validated['admin_fee_type'] ?? 'one_time');

        // Calculate Cycle
        $cycle = Loan::where('borrower_id', $validated['borrower_id'])->count() + 1;
        $validated['loan_cycle'] = $cycle;

        if (isset($validated['loan_officer_id'])) {
            $validated['disbursed_by_officer_id'] = $validated['loan_officer_id'];
        }

        $loan = Loan::create($validated);

        // Save collaterals if any
        if (isset($validated['collaterals'])) {
            foreach ($validated['collaterals'] as $collateralData) {
                // Only save if type or value is provided
                if (!empty($collateralData['type']) || !empty($collateralData['value'])) {
                    $loan->collaterals()->create($collateralData);
                }
            }
        }

        // Calculate schedule if essential data is provided
        if (
            !empty($validated['amount']) &&
            !empty($validated['interest_rate']) &&
            !empty($validated['duration_months']) &&
            !empty($validated['repayment_method']) &&
            !empty($validated['start_date'])
        ) {
            try {
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
                    $schedule = BalloonPaymentCalculator::generateSchedule($loanData, 'interest_only');

                    if (!empty($schedule)) {
                        // Update monthly payment reference (first payment)
                        $loan->update(['monthly_payment' => $schedule[0]['total_paid'] ?? 0]);

                        // Save schedule as payments
                        foreach ($schedule as $payment) {
                            $loan->payments()->create([
                                'payment_number' => $payment['payment_number'],
                                'principal_amount' => $payment['principal_amount'],
                                'interest_amount' => $payment['interest_amount'],
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
                        $validated['currency'] ?? 'USD'
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
                                'penalty_amount' => 0,
                                'total_paid' => 0,
                                'payment_date' => $this->normalizeScheduleDate($item['date']),
                                'payment_method' => 'Cash',
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently skip schedule if calculation fails (optional fields might cause math errors)
                Log::error("Loan schedule calculation failed: " . $e->getMessage());
            }
        }

        return response()->json($loan->load(['borrower', 'coBorrower', 'guarantor', 'officer', 'collaterals', 'payments', 'product']), 201);
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
                $scheduleRaw = BalloonPaymentCalculator::generateSchedule($loanData, 'interest_only');

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
                $schedule = $this->calculator->calculateLoanWithDates(
                    $validated['amount'],
                    $validated['interest_rate'],
                    $validated['duration_months'],
                    // For negotiable, default to fixed_monthly as a starting point
                    $validated['repayment_method'] === 'negotiable' ? 'fixed_monthly' : $validated['repayment_method'],
                    $validated['start_date'],
                    $validated['currency'] ?? 'USD'
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
            'borrower', 'coBorrower', 'guarantor', 'officer', 'collaterals',
            'payments', 'product'
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
            'admin_fee_type' => 'nullable|string|in:one_time,monthly',
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
