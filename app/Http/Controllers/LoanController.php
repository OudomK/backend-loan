<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use App\Services\LoanScheduleService;
use App\Support\CurrencyRounding;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function __construct(private readonly LoanScheduleService $scheduleService) {}

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
            $customerCode = 'L'.str_pad((string) $borrowerId, 3, '0', STR_PAD_LEFT);
        }
        $cycle = Loan::where('borrower_id', $borrowerId)
            ->where('status', '!=', 'rejected')
            ->count() + 1;
        $suggestedLoanCode = $customerCode.'-C'.$cycle;

        return response()->json([
            'cycle' => $cycle,
            'suggested_loan_code' => $suggestedLoanCode,
        ]);
    }

    public function store(Request $request)
    {
        $requirePurpose = \App\Models\Setting::where('key', 'require_loan_purpose')->value('value');
        $requirePurpose = $requirePurpose === null ? true : filter_var($requirePurpose, FILTER_VALIDATE_BOOLEAN);

        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'amount' => 'nullable|numeric',
            'interest_rate' => 'nullable|numeric',
            'duration_months' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'status' => 'required|in:pending,pending_check,pending_verify,pending_approval,active,completed,paid_off,rejected',
            'currency' => 'nullable|string',
            'repayment_method' => 'nullable|string',
            'purpose' => ($requirePurpose ? 'required' : 'nullable').'|string|max:255',
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
        ], [
            'borrower_id.required' => 'សូមជ្រើសរើសអតិថិជន។',
            'borrower_id.exists' => 'អតិថិជនមិនត្រឹមត្រូវ។',
            'loan_officer_id.exists' => 'សូមជ្រើសរើសមន្ត្រីឥណទានឱ្យបានត្រឹមត្រូវ។',
            'payment_qr_id.exists' => 'សូមជ្រើសរើស QR Code បង់ប្រាក់ឱ្យបានត្រឹមត្រូវ។',
            'co_borrower_id.exists' => 'អ្នកខ្ចីរួមមិនត្រឹមត្រូវ។',
            'guarantor_id.exists' => 'អ្នកធានាមិនត្រឹមត្រូវ។',
        ]);

        $validated['payment_frequency'] = LoanScheduleService::canonicalPaymentFrequency(
            (string) ($validated['repayment_method'] ?? ''),
            $validated['payment_frequency'] ?? null
        );

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
            $validated['amount'] = $this->scheduleService->calculateSchedulePrincipal(
                $requestedAmount,
                $adminFeePercent,
                $feeType
            );
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

        // Lock penalty rate based on current settings
        $currency = $validated['currency'] ?? 'USD';
        $settingKey = $currency === 'KHR' ? 'default_penalty_khr' : 'default_penalty_usd';
        $defaultRate = $currency === 'KHR' ? 10000 : 2.5;
        $validated['penalty_rate'] = \App\Models\Setting::where('key', $settingKey)->value('value') ?? $defaultRate;

        // Force legacy 'pending' status to the new 'pending_check' stage
        if (($validated['status'] ?? '') === 'pending') {
            $validated['status'] = \App\Models\LoanApproval::STATUS_PENDING_CHECK;
        }

        // Track who submitted this from the API (might be null if API has no auth context yet, fallback to officer id)
        $validated['submitted_by'] = \Illuminate\Support\Facades\Auth::id() ?? $validated['loan_officer_id'] ?? null;

        DB::beginTransaction();
        try {
            $loan = Loan::create($validated);

            if ($loan->status === \App\Models\LoanApproval::STATUS_PENDING_CHECK) {
                $loan->approvals()->create([
                    'user_id' => $validated['submitted_by'] ?: 1, // Fallback to a valid user ID if none
                    'action' => \App\Models\LoanApproval::ACTION_SUBMITTED,
                    'from_status' => null,
                    'to_status' => $loan->status,
                    'comments' => 'Initial loan submission via Mobile App',
                ]);
            }

            // Save collaterals if any
            if (isset($validated['collaterals'])) {
                foreach ($validated['collaterals'] as $collateralData) {
                    // Only save if type or value is provided
                    if (! empty($collateralData['type']) || ! empty($collateralData['value'])) {
                        $loan->collaterals()->create($collateralData);
                    }
                }
            }

            $requiresSchedule =
                ! empty($validated['amount']) &&
                ! empty($validated['interest_rate']) &&
                ! empty($validated['duration_months']) &&
                ! empty($validated['repayment_method']) &&
                ! empty($validated['start_date']);

            // Calculate schedule if essential data is provided
            if ($requiresSchedule) {
                // Handle Negotiable (Custom Schedule)
                if ($validated['repayment_method'] === 'negotiable' && ! empty($validated['custom_schedule'])) {
                    $schedule = $validated['custom_schedule'];

                    // Use the first payment as monthly reference (though it varies)
                    $loan->update(['monthly_payment' => $schedule[0]['payment'] ?? 0]);

                    foreach ($schedule as $item) {
                        $loan->payments()->create([
                            'payment_number' => $item['period'],
                            'principal_amount' => $item['principal'],
                            'interest_amount' => $item['interest'],
                            'fee_amount' => (float) ($item['fee'] ?? 0),
                            'outstanding_balance' => isset($item['balance']) ? (float) $item['balance'] : (isset($item['remaining_balance']) ? (float) $item['remaining_balance'] : (isset($item['outstanding_balance']) ? (float) $item['outstanding_balance'] : null)),
                            'penalty_amount' => 0,
                            'total_paid' => 0,
                            'payment_date' => $this->normalizeScheduleDate($item['date']),
                            'payment_method' => 'Cash',
                        ]);
                    }
                } else {
                    $schedule = $this->scheduleService->generate($validated);

                    if (! empty($schedule)) {
                        $loan->update(['monthly_payment' => $schedule[0]['payment'] ?? 0]);

                        foreach ($schedule as $item) {
                            $loan->payments()->create([
                                'payment_number' => $item['period'],
                                'principal_amount' => $item['principal'],
                                'interest_amount' => $item['interest'],
                                'fee_amount' => (float) ($item['fee'] ?? 0),
                                'outstanding_balance' => isset($item['balance']) ? (float) $item['balance'] : (isset($item['remaining_balance']) ? (float) $item['remaining_balance'] : (isset($item['outstanding_balance']) ? (float) $item['outstanding_balance'] : null)),
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
            Log::error('Loan create failed: '.$e->getMessage());

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
            $feeType = (string) ($validated['admin_fee_type'] ?? 'one_time');
            $scheduleAmount = $this->scheduleService->calculateSchedulePrincipal(
                (float) $validated['amount'],
                (float) ($validated['admin_fee'] ?? 0),
                $feeType
            );

            $schedule = $this->scheduleService->generate([
                ...$validated,
                'amount' => $scheduleAmount,
                'admin_fee_type' => $feeType,
            ]);

            $printSchedule = \App\Services\LoanCalculator::formatScheduleForPrint(
                $schedule,
                $validated['repayment_method'],
                $scheduleAmount
            );

            return response()->json([
                'schedule' => $schedule,
                'print_schedule' => $printSchedule,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Recalculate a Negotiable schedule on the server side.
     *
     * Accepts the current schedule rows (with locked flags), the chosen
     * calculation type (annuity / linear / flat), and loan parameters.
     * Returns the recalculated schedule with proper currency-aware rounding.
     */
    public function recalculateNegotiableSchedule(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'calc_type' => 'required|string|in:annuity,linear,flat',
            'start_date' => 'nullable|date',
            'schedule' => 'required|array|min:1',
            'schedule.*.period' => 'required|integer',
            'schedule.*.date' => 'required|string',
            'schedule.*.principal' => 'required|numeric',
            'schedule.*.interest' => 'required|numeric',
            'schedule.*.fee' => 'nullable|numeric',
            'schedule.*.payment' => 'required|numeric',
            'schedule.*.locked' => 'required|boolean',
        ]);

        $principal = (float) $validated['amount'];
        $rate = (float) $validated['interest_rate'];
        $currency = $validated['currency'] ?? 'USD';
        $calcType = $validated['calc_type'];
        $rows = $validated['schedule'];
        $startDate = ! empty($validated['start_date'])
            ? new \DateTime($validated['start_date'])
            : null;

        // Only interest rounds upward; principal/payment keep exact 2 decimals.
        $roundFn = fn ($amount) => round((float) $amount, 2);
        $roundInterest = fn ($amount) => CurrencyRounding::up((float) $amount, $currency);
        $roundScheduledPrincipal = $roundFn;

        // ------ Parse first payment date for pro-rata interest ------
        $firstPaymentDate = null;
        $daysFromStart = null;
        if ($startDate && ! empty($rows[0]['date'])) {
            $rawDate = $rows[0]['date'];
            // Support both dd/MM/yyyy and yyyy-MM-dd formats
            if (preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $rawDate)) {
                $firstPaymentDate = \DateTime::createFromFormat('d/m/Y', $rawDate);
            } else {
                $firstPaymentDate = new \DateTime($rawDate);
            }
            if ($firstPaymentDate) {
                $daysFromStart = $startDate->diff($firstPaymentDate)->days + 1; // +1 inclusive
            }
        }

        // ------ Gather locked / unlocked info ------
        $sumLockedPrincipal = 0;
        $unlockedCount = 0;
        $lastUnlockedIndex = -1;

        foreach ($rows as $i => $row) {
            if (! empty($row['locked'])) {
                $sumLockedPrincipal += (float) $row['principal'];
            } else {
                $unlockedCount++;
                $lastUnlockedIndex = $i;
            }
        }

        $remainingPrincipal = max(0, $principal - $sumLockedPrincipal);
        $monthlyRate = $rate / 100;

        // ------ Compute PMT / per-period principal ------
        $pmt = 0;
        $unlockedPrincipalPer = 0;

        if ($unlockedCount > 0) {
            if ($calcType === 'annuity') {
                if ($monthlyRate > 0) {
                    $factor = pow(1 + $monthlyRate, $unlockedCount);
                    $pmt = $remainingPrincipal * ($monthlyRate * $factor) / ($factor - 1);
                } else {
                    $pmt = $remainingPrincipal / $unlockedCount;
                }
            } else {
                $unlockedPrincipalPer = $remainingPrincipal / $unlockedCount;
            }
        }

        // ------ Build result schedule ------
        $outstanding = $principal;
        $result = [];

        foreach ($rows as $i => $row) {
            $newPrincipal = (float) $row['principal'];
            $fee = (float) ($row['fee'] ?? 0);

            // Interest — first period uses pro-rata based on actual days
            if ($i === 0 && $daysFromStart !== null) {
                // Pro-rata first period interest: rate/30 * daysFromStart
                if ($calcType === 'flat') {
                    $newInterest = $roundInterest($principal * ($monthlyRate / 30) * $daysFromStart);
                } else {
                    $newInterest = $roundInterest($outstanding * ($monthlyRate / 30) * $daysFromStart);
                }
            } else {
                if ($calcType === 'flat') {
                    $newInterest = $roundInterest($principal * $monthlyRate);
                } else {
                    $newInterest = $roundInterest($outstanding * $monthlyRate);
                }
            }

            // Principal (only recalculate unlocked rows)
            if (empty($row['locked'])) {
                if ($i === $lastUnlockedIndex) {
                    $newPrincipal = $outstanding; // zero-out
                } elseif ($calcType === 'annuity') {
                    // For first period with pro-rata, use standard PMT for principal split
                    $roundedPmt = $roundFn($pmt);
                    if ($i === 0 && $daysFromStart !== null) {
                        // Use standard monthly interest (not pro-rata) for principal calculation
                        $standardInterest = $roundInterest($outstanding * $monthlyRate);
                        $newPrincipal = $roundedPmt - $standardInterest;
                    } else {
                        $newPrincipal = $roundedPmt - $newInterest;
                    }
                    if ($newPrincipal < 0) {
                        $newPrincipal = 0;
                    }
                } else {
                    $newPrincipal = $roundScheduledPrincipal($unlockedPrincipalPer);
                }
            }

            $payment = $roundFn($newPrincipal + $newInterest + $fee);

            $outstanding -= $newPrincipal;
            if ($outstanding < 0.01) {
                $outstanding = 0;
            }

            $result[] = [
                'period' => $row['period'],
                'date' => $row['date'],
                'principal' => $newPrincipal,
                'interest' => $newInterest,
                'fee' => $fee,
                'payment' => $payment,
                'balance' => $outstanding,
            ];
        }

        return response()->json(['schedule' => $result]);
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
            'paymentQr',
        ]));
    }

    public function update(Request $request, Loan $loan)
    {
        $this->assertCycleIsEditable($loan);
        $requirePurpose = \App\Models\Setting::where('key', 'require_loan_purpose')->value('value');
        $requirePurpose = $requirePurpose === null ? true : filter_var($requirePurpose, FILTER_VALIDATE_BOOLEAN);

        $validated = $request->validate([
            'borrower_id' => 'sometimes|required|exists:borrowers,id',
            'amount' => 'sometimes|required|numeric',
            'interest_rate' => 'sometimes|required|numeric',
            'duration_months' => 'sometimes|required|integer',
            'monthly_payment' => 'sometimes|required|numeric',
            'start_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:pending,active,completed,paid_off',
            'purpose' => 'sometimes|'.($requirePurpose ? 'required' : 'nullable').'|string|max:255',
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
        $this->assertCycleIsEditable($loan);
        $loan->delete();

        return response()->json(null, 204);
    }

    public function writeOff(Request $request, int $id)
    {
        $loan = Loan::findOrFail($id);
        $this->assertCycleIsEditable($loan);

        $validated = $request->validate([
            'written_off_at' => 'required|date',
            'write_off_balance' => 'required|numeric',
            'write_off_reason' => 'nullable|string',
            'recovery_amount' => 'nullable|numeric',
            'maturity_date' => 'nullable|date',
            'classify_wo' => 'nullable|string',
        ]);

        $loan->written_off_at = $validated['written_off_at'];
        $loan->write_off_balance = $validated['write_off_balance'];
        $loan->write_off_reason = $validated['write_off_reason'] ?? null;
        $loan->recovery_amount = $validated['recovery_amount'] ?? 0;

        if (array_key_exists('maturity_date', $validated)) {
            $loan->maturity_date = $validated['maturity_date'];
        }
        if (array_key_exists('classify_wo', $validated)) {
            $loan->classify_wo = $validated['classify_wo'];
        }

        $loan->status = 'written_off';

        $loan->save();

        return response()->json(['message' => 'Loan successfully written off.', 'loan' => $loan]);
    }

    private function applyRounding(float $amount, string $currency): float
    {
        return round($amount, 2);
    }

    private function assertCycleIsEditable(Loan $loan): void
    {
        if (in_array($loan->status, ['rescheduled', 'refinanced'], true)) {
            throw ValidationException::withMessages([
                'loan' => 'A closed rescheduled/refinanced cycle is read-only.',
            ]);
        }
    }

    public function updateSchedule(Request $request, int $id)
    {
        $loan = Loan::findOrFail($id);
        if ($loan->status !== 'active') {
            throw ValidationException::withMessages([
                'loan' => 'Only an active loan cycle can have its schedule edited.',
            ]);
        }

        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*' => 'required|array',
            'payments.*.id' => [
                'required',
                'integer',
                Rule::exists('payments', 'id')->where(
                    fn ($query) => $query->where('loan_id', $loan->id)
                ),
            ],
            'payments.*.payment_date' => 'sometimes|required|date',
            'payments.*.principal_amount' => 'sometimes|required|numeric|min:0',
            'payments.*.interest_amount' => 'sometimes|required|numeric|min:0',
            'payments.*.fee_amount' => 'sometimes|required|numeric|min:0',
            'payments.*.outstanding_balance' => 'sometimes|required|numeric|min:0',
            'payments.*.balance' => 'sometimes|required|numeric|min:0',
            'payments.*.remaining_balance' => 'sometimes|required|numeric|min:0',
        ]);

        $editableFields = [
            'payment_date',
            'principal_amount',
            'interest_amount',
            'fee_amount',
            'outstanding_balance',
            'balance',
            'remaining_balance',
        ];
        foreach ($validated['payments'] as $index => $paymentData) {
            if (array_intersect($editableFields, array_keys($paymentData)) === []) {
                throw ValidationException::withMessages([
                    "payments.{$index}" => 'At least one schedule field must be provided.',
                ]);
            }
        }

        $principalWasEdited = collect($validated['payments'])
            ->contains(fn (array $paymentData): bool => array_key_exists('principal_amount', $paymentData));

        DB::transaction(function () use ($loan, $validated, $principalWasEdited) {
            foreach ($validated['payments'] as $paymentData) {
                $payment = $loan->payments()->findOrFail($paymentData['id']);
                $currency = $loan->currency ?? 'USD';
                $updates = [];

                if (array_key_exists('payment_date', $paymentData)) {
                    $updates['payment_date'] = $paymentData['payment_date'];
                }
                if (array_key_exists('principal_amount', $paymentData)) {
                    $updates['principal_amount'] = $this->applyRounding(
                        (float) $paymentData['principal_amount'],
                        $currency
                    );
                }
                if (array_key_exists('interest_amount', $paymentData)) {
                    $updates['interest_amount'] = CurrencyRounding::up(
                        (float) $paymentData['interest_amount'],
                        $currency
                    );
                }
                if (array_key_exists('fee_amount', $paymentData)) {
                    $updates['fee_amount'] = $this->applyRounding(
                        (float) $paymentData['fee_amount'],
                        $currency
                    );
                }

                foreach (['outstanding_balance', 'balance', 'remaining_balance'] as $balanceKey) {
                    if (array_key_exists($balanceKey, $paymentData)) {
                        $updates['outstanding_balance'] = $this->applyRounding(
                            (float) $paymentData[$balanceKey],
                            $currency
                        );
                        break;
                    }
                }

                if ($updates !== []) {
                    $payment->update($updates);
                }
            }

            if ($principalWasEdited) {
                $contractPrincipal = round((float) $loan->payments()->sum('principal_amount'), 2);
                $principalMovement = (float) $loan->transactions()
                    ->selectRaw(
                        'COALESCE(SUM(COALESCE(principal_paid, 0) + COALESCE(prepayment_paid, 0) '
                        .' + COALESCE(paid_off_amount, 0) - COALESCE(withdrawn_prepayment, 0)), 0) AS aggregate'
                    )
                    ->value('aggregate');

                if ($contractPrincipal + 0.001 < $principalMovement) {
                    throw ValidationException::withMessages([
                        'payments' => 'Schedule principal cannot be lower than principal already paid.',
                    ]);
                }

                $loan->update([
                    'amount' => $contractPrincipal,
                    'monthly_interest' => round(
                        $contractPrincipal * (float) $loan->interest_rate / 100,
                        2
                    ),
                ]);
            }
        });

        return response()->json(['message' => 'Schedule successfully updated.']);
    }
}
