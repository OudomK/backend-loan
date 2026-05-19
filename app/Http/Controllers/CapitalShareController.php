<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use App\Models\Investor;
use App\Models\CapitalShareTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\LoanCalculator;
use App\Services\BalloonPaymentCalculator;

/**
 * Handles CRUD operations for Capital Shares.
 * 
 * Logic Note:
 * - 'Real Capital' entries are linked to 'investors' via 'investor_id'.
 * - 'Loan Capital' entries are linked to 'lenders' via 'lender_id'.
 */
class CapitalShareController extends Controller
{
    private function ensurePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthenticated.');

        if ($user->can($permission)) {
            return;
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    private function hasCapitalMovements(CapitalShare $share): bool
    {
        return $share->transactions()
            ->whereIn('transaction_type', ['Deposit', 'Withdrawal', 'Repayment'])
            ->exists();
    }

    private function transformShare(CapitalShare $s): array
    {
        $name = 'N/A';
        $code = '-';
        $type = 'Individual';

        if ($s->investor) {
            $name = $s->investor->first_name . ' ' . $s->investor->last_name;
            $code = $s->investor->customer_code;
            $type = $s->investor->customer_type ?? 'Individual';
        } elseif ($s->lender) {
            $name = $s->lender->name;
            $code = $s->lender->lender_code ?? $s->lender->code ?? '-';
            $type = $s->lender->lender_type ?? 'Individual';
        }

        return [
            'id' => $s->id,
            'lender_id' => $s->investor_id ?? $s->lender_id, // For frontend compatibility
            'lender_name' => $name,
            'lender_code' => $code,
            'investor_name' => $name,
            'lender_type' => $type,
            'category' => $s->category,
            'share_qty' => $s->share_qty,
            'par_value' => $s->par_value,
            'total_capital' => $s->total_capital,
            'currency' => $s->currency,
            'status' => $s->status,
            'created_at' => optional($s->created_at)->toIso8601String(),

            // Borrowing/Legacy Fields
            'transaction_no' => $s->transaction_no,
            'loan_account' => $s->loan_account,
            'borrowing_date' => $s->borrowing_date,
            'account_no' => $s->account_no,
            'contract_no' => $s->contract_no,
            'amount' => $s->amount,
            'int_pay_mode' => $s->int_pay_mode,
            'balance' => $s->balance,
            'dividends' => $s->dividends,
            'total_dividend_paid' => $s->total_dividend_paid,
            'last_dividend_date' => $s->last_dividend_date,
            'repayment_schedule' => $s->repayment_schedule,
        ];
    }

    private function deriveCapitalValues(array $validated, ?CapitalShare $existing = null): array
    {
        $shareQty = array_key_exists('share_qty', $validated)
            ? (int) $validated['share_qty']
            : (int) ($existing?->share_qty ?? 0);

        $amount = array_key_exists('amount', $validated)
            ? round((float) $validated['amount'], 2)
            : round((float) ($existing?->amount ?? 0), 2);

        $parValue = array_key_exists('par_value', $validated)
            ? (float) $validated['par_value']
            : (float) ($existing?->par_value ?? 0);

        if ($parValue <= 0) {
            if ($shareQty > 0 && $amount > 0) {
                $parValue = round($amount / $shareQty, 8);
            } else {
                $parValue = (float) ($existing?->par_value ?? 1);
                if ($parValue <= 0) {
                    $parValue = 1.0;
                }
            }
        }

        if ($amount <= 0 && $shareQty > 0) {
            $amount = round($shareQty * $parValue, 2);
        }

        return [
            'share_qty' => $shareQty,
            'par_value' => $parValue,
            'amount' => $amount,
            'total_capital' => $amount,
        ];
    }

    public function index()
    {
        $shares = CapitalShare::with(['lender', 'investor'])->get();
        return $shares->map(fn(CapitalShare $s) => $this->transformShare($s));
    }

    public function show(string|int $id)
    {
        $share = CapitalShare::with(['lender', 'investor'])->findOrFail($id);
        return response()->json($this->transformShare($share));
    }

    public function destroy(Request $request, string|int $id)
    {
        $this->ensurePermission($request, 'ui:capital_share:delete');

        $share = CapitalShare::findOrFail($id);
        $share->delete();

        return response()->json(['message' => 'Capital/share record deleted.']);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'ui:capital_share:create');

        \Illuminate\Support\Facades\Log::info("Capital Share Store Request: " . json_encode($request->all()));

        $category = $request->input('category');
        $lenderTable = $category === 'Real Capital' ? 'investors' : 'lenders';

        $validated = $request->validate([
            'lender_id' => "required|exists:$lenderTable,id",
            'category' => 'required|string',
            'par_value' => 'nullable|numeric|min:0',
            'share_qty' => 'nullable|integer|min:0',
            'currency' => 'required|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'balance' => 'nullable|numeric|min:0',
            'dividends' => 'nullable|numeric|min:0',
            'total_dividend_paid' => 'nullable|numeric|min:0',
            'last_dividend_date' => 'nullable|date',
            'repayment_schedule' => 'nullable|array',
            'holder_id' => 'nullable|integer',
            'certificate_no' => 'nullable|string',
            'purchase_date' => 'nullable|date',
        ]);

        if ($category === 'Real Capital') {
            $capitalValues = $this->deriveCapitalValues($validated);

            if ($capitalValues['amount'] <= 0) {
                return response()->json([
                    'message' => 'Amount must be greater than 0 for Real Capital.',
                ], 422);
            }

            if ($capitalValues['share_qty'] <= 0) {
                return response()->json([
                    'message' => 'Share quantity must be greater than 0 for Real Capital.',
                ], 422);
            }

            $validated = array_merge($validated, $capitalValues);
            $validated['balance'] = $capitalValues['amount'];
        } else {
            $validated['amount'] = round((float) ($validated['amount'] ?? 0), 2);

            if ($validated['amount'] <= 0) {
                return response()->json([
                    'message' => 'Amount must be greater than 0 for Loan Capital.',
                ], 422);
            }

            $validated['total_capital'] = $validated['amount'];
            $validated['balance'] = $validated['amount'];
        }

        $share = new CapitalShare();

        if ($category === 'Real Capital') {
            $share->investor_id = $validated['lender_id'];
        } else {
            $share->lender_id = $validated['lender_id'];
        }

        $share->account_no = $validated['account_no'] ?? $this->generateAccountNo();
        $share->category = $validated['category'];
        $share->par_value = $validated['par_value'] ?? 1.0;
        $share->share_qty = $validated['share_qty'] ?? 0;
        $share->amount = $validated['amount'] ?? 0;
        $share->total_capital = $validated['total_capital'] ?? $share->amount;
        $share->balance = $validated['balance'] ?? $share->amount;
        $share->currency = $validated['currency'];
        $share->status = 'Active';

        // Additional fields assignment
        $share->transaction_no = $validated['transaction_no'] ?? null;
        $share->loan_account = $validated['loan_account'] ?? null;
        $share->borrowing_date = $validated['borrowing_date'] ?? null;
        $share->contract_no = $validated['contract_no'] ?? null;
        $share->int_pay_mode = $validated['int_pay_mode'] ?? null;
        $share->dividends = $validated['dividends'] ?? 0;
        $share->total_dividend_paid = $validated['total_dividend_paid'] ?? 0;
        $share->last_dividend_date = $validated['last_dividend_date'] ?? null;
        $share->repayment_schedule = $validated['repayment_schedule'] ?? null;

        $share->holder_id = $validated['holder_id'] ?? null;
        $share->certificate_no = $validated['certificate_no'] ?? null;
        $share->purchase_date = $validated['purchase_date'] ?? null;

        $share->save();

        // Create initial transaction
        CapitalShareTransaction::create([
            'capital_share_id' => $share->id,
            'transaction_type' => 'Initial',
            'amount' => $share->amount,
            'share_qty' => $share->share_qty,
            'payment_method' => null,
            'transaction_date' => $share->borrowing_date ?? $share->purchase_date ?? now(),
            'description' => $category === 'Real Capital'
                ? 'Initial capital purchase'
                : 'Initial loan capital setup',
            'performed_by' => Auth::id(),
        ]);

        return response()->json($share, 201);
    }

    public function update(Request $request, string|int $id)
    {
        $this->ensurePermission($request, 'ui:capital_share:edit');

        $share = CapitalShare::findOrFail($id);
        $category = $request->input('category', $share->category);
        $lenderTable = $category === 'Real Capital' ? 'investors' : 'lenders';

        $validated = $request->validate([
            'lender_id' => "nullable|exists:$lenderTable,id",
            'category' => 'nullable|string',
            'status' => 'nullable|string',
            'share_qty' => 'nullable|integer|min:0',
            'par_value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'balance' => 'nullable|numeric|min:0',
            'dividends' => 'nullable|numeric|min:0',
            'total_dividend_paid' => 'nullable|numeric|min:0',
            'last_dividend_date' => 'nullable|date',
            'repayment_schedule' => 'nullable|array',
            'holder_id' => 'nullable|integer',
            'certificate_no' => 'nullable|string',
            'purchase_date' => 'nullable|date',
        ]);

        if ($category !== $share->category) {
            return response()->json([
                'message' => 'Changing category on an existing capital/share record is not supported. Please create a new record instead.',
            ], 422);
        }

        if (isset($validated['lender_id'])) {
            if ($category === 'Real Capital') {
                $share->investor_id = $validated['lender_id'];
                $share->lender_id = null;
            } else {
                $share->lender_id = $validated['lender_id'];
                $share->investor_id = null;
            }
            unset($validated['lender_id']);
        }

        unset($validated['balance']);

        if ($category === 'Real Capital') {
            $financialFieldsChanged = array_key_exists('amount', $validated)
                || array_key_exists('share_qty', $validated)
                || array_key_exists('par_value', $validated);

            if ($financialFieldsChanged && $this->hasCapitalMovements($share)) {
                return response()->json([
                    'message' => 'This capital account already has transactions. Please use Add Capital or Withdraw Capital instead of editing the invested amount directly.',
                ], 422);
            }

            if ($financialFieldsChanged) {
                $capitalValues = $this->deriveCapitalValues($validated, $share);

                if ($capitalValues['amount'] <= 0) {
                    return response()->json([
                        'message' => 'Amount must be greater than 0 for Real Capital.',
                    ], 422);
                }

                if ($capitalValues['share_qty'] <= 0) {
                    return response()->json([
                        'message' => 'Share quantity must be greater than 0 for Real Capital.',
                    ], 422);
                }

                $validated = array_merge($validated, $capitalValues);
                $validated['balance'] = $capitalValues['amount'];
            }
        } elseif (array_key_exists('amount', $validated)) {
            $newAmount = round((float) $validated['amount'], 2);
            $repaidPrincipal = round(max(0, (float) $share->amount - (float) $share->balance), 2);

            if ($newAmount <= 0) {
                return response()->json([
                    'message' => 'Amount must be greater than 0 for Loan Capital.',
                ], 422);
            }

            if ($newAmount + 0.001 < $repaidPrincipal) {
                return response()->json([
                    'message' => 'Amount cannot be less than the principal already repaid.',
                ], 422);
            }

            $validated['amount'] = $newAmount;
            $validated['total_capital'] = $newAmount;
            $validated['balance'] = round(max(0, $newAmount - $repaidPrincipal), 2);
        }

        $share->fill($validated);
        $share->save();

        return response()->json($share);
    }

    public function addCapital(Request $request, string|int $id)
    {
        $share = CapitalShare::findOrFail($id);

        if ($share->category !== 'Real Capital') {
            return response()->json([
                'message' => 'Add Capital is only available for Real Capital accounts.',
            ], 422);
        }

        $validated = $request->validate([
            'share_qty' => 'nullable|integer|min:1',
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['share_qty']) && empty($validated['amount'])) {
            return response()->json([
                'message' => 'Please provide a share quantity or amount to add.',
            ], 422);
        }

        return DB::transaction(function () use ($share, $validated) {
            $parValue = $share->par_value > 0 ? (float) $share->par_value : 1;

            // Calculate based on what was provided
            if (!empty($validated['amount'])) {
                $inputAmount = round((float) $validated['amount'], 2);
                $remainder = fmod($inputAmount, $parValue);
                if ($remainder > 0.001 && ($parValue - $remainder) > 0.001) {
                    return response()->json([
                        'message' => 'Amount must match share multiples based on par value.',
                    ], 422);
                }

                $addShares = (int) floor($inputAmount / $parValue);
                $addAmount = round($addShares * $parValue, 2);
            } else {
                $addShares = (int) $validated['share_qty'];
                $addAmount = round($addShares * $parValue, 2);
            }

            if ($addShares <= 0) {
                return response()->json([
                    'message' => 'Amount is below one share value. Please enter a valid share quantity or amount.',
                ], 422);
            }

            $newShareQty = (int) $share->share_qty + $addShares;
            $newTotalCapital = round($newShareQty * $parValue, 2);

            // Accumulate balance (do NOT reset to newTotalCapital - that can reduce balance)
            $newBalance = round((float) $share->balance + $addAmount, 2);
            DB::table('capital_shares')->where('id', $share->id)->whereNull('deleted_at')->update([
                'share_qty'     => $newShareQty,
                'total_capital' => $newTotalCapital,
                'amount'        => $newTotalCapital,
                'balance'       => $newBalance,
                'updated_at'    => now(),
            ]);

            CapitalShareTransaction::create([
                'capital_share_id' => $share->id,
                'transaction_type' => 'Deposit',
                'amount' => $addAmount,
                'share_qty' => $addShares,
                'payment_method' => $validated['payment_method'] ?? null,
                'transaction_date' => $validated['transaction_date'] ?? now(),
                'description' => $validated['description'] ?? 'Added capital',
                'performed_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Capital added successfully',
                'data' => $share->fresh(),
            ]);
        });
    }

    public function withdrawCapital(Request $request, string|int $id)
    {
        $share = CapitalShare::findOrFail($id);

        if ($share->category !== 'Real Capital') {
            return response()->json([
                'message' => 'Withdraw Capital is only available for Real Capital accounts.',
            ], 422);
        }

        $validated = $request->validate([
            'share_qty' => 'nullable|integer|min:1',
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['share_qty']) && empty($validated['amount'])) {
            return response()->json([
                'message' => 'Please provide a share quantity or amount to withdraw.',
            ], 422);
        }

        $parValue = $share->par_value > 0 ? (float) $share->par_value : 1;

        // Calculate based on what was provided
        if (!empty($validated['amount'])) {
            $inputAmount = round((float) $validated['amount'], 2);
            $remainder = fmod($inputAmount, $parValue);
            if ($remainder > 0.001 && ($parValue - $remainder) > 0.001) {
                return response()->json([
                    'message' => 'Amount must match share multiples based on par value.',
                ], 422);
            }

            $withdrawShares = (int) floor($inputAmount / $parValue);
            $withdrawAmount = round($withdrawShares * $parValue, 2);
        } else {
            $withdrawShares = (int) $validated['share_qty'];
            $withdrawAmount = round($withdrawShares * $parValue, 2);
        }

        if ($withdrawShares <= 0) {
            return response()->json([
                'message' => 'Amount is below one share value. Please enter a valid share quantity or amount.',
            ], 422);
        }

        if ((int) $share->share_qty < $withdrawShares) {
            return response()->json(['message' => 'Insufficient share quantity'], 400);
        }

        if ($withdrawAmount > (float) $share->balance + 0.001) {
            return response()->json(['message' => 'Insufficient capital balance'], 422);
        }

        return DB::transaction(function () use ($share, $validated, $withdrawShares, $withdrawAmount, $parValue) {
            $newShareQty = (int) $share->share_qty - $withdrawShares;
            $newTotalCapital = round($newShareQty * $parValue, 2);
            $newBalance = round(max(0, (float) $share->balance - $withdrawAmount), 2);

            DB::table('capital_shares')->where('id', $share->id)->whereNull('deleted_at')->update([
                'share_qty' => $newShareQty,
                'total_capital' => $newTotalCapital,
                'amount' => $newTotalCapital,
                'balance' => $newBalance,
                'status' => $newShareQty <= 0 || $newBalance <= 0.001 ? 'Withdrawn' : 'Active',
                'updated_at' => now(),
            ]);

            CapitalShareTransaction::create([
                'capital_share_id' => $share->id,
                'transaction_type' => 'Withdrawal',
                'amount' => $withdrawAmount,
                'share_qty' => $withdrawShares,
                'payment_method' => $validated['payment_method'] ?? null,
                'transaction_date' => $validated['transaction_date'] ?? now(),
                'description' => $validated['description'] ?? 'Withdrawn capital',
                'performed_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Capital withdrawn successfully',
                'data' => $share->fresh(),
            ]);
        });
    }

    public function getTransactions(string|int $id)
    {
        $transactions = CapitalShareTransaction::where('capital_share_id', $id)
            ->orderBy('transaction_date', 'desc')
            ->get();
        return response()->json($transactions);
    }

    public function repay(Request $request, string|int $id)
    {
        $share = CapitalShare::findOrFail($id);

        if ($share->category !== 'Loan Capital') {
            return response()->json([
                'message' => 'Repayment is only available for Loan Capital accounts.',
            ], 422);
        }

        $validated = $request->validate([
            'period' => 'required|integer',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($share, $validated) {
            if (empty($share->repayment_schedule)) {
                return response()->json([
                    'message' => 'This loan capital account has no repayment schedule.',
                ], 422);
            }

            $paymentTotal = (float) $validated['principal_paid'] + (float) $validated['interest_paid'];
            if ($paymentTotal <= 0.001) {
                return response()->json([
                    'message' => 'Principal or interest payment must be greater than zero.',
                ], 422);
            }

            if ($validated['principal_paid'] > $share->balance + 0.001) {
                return response()->json([
                    'message' => "Principal paid ({$validated['principal_paid']}) exceeds remaining balance (" . number_format($share->balance, 2) . "). Please check the amount."
                ], 422);
            }

            $schedule = $share->repayment_schedule ?? [];
            $foundIndex = -1;
            foreach ($schedule as $idx => $item) {
                if ((int) ($item['period'] ?? 0) === (int) $validated['period']) {
                    $foundIndex = $idx;
                    break;
                }
            }

            if ($foundIndex < 0) {
                return response()->json(['message' => 'Period not found in schedule'], 422);
            }

            $item = $schedule[$foundIndex];

            if (($item['status'] ?? '') === 'paid') {
                return response()->json(['message' => 'This period is already fully paid.'], 422);
            }

            $principalDue = (float) ($item['principal_due'] ?? $item['principal'] ?? 0);
            $interestDue = (float) ($item['interest_due'] ?? $item['interest'] ?? 0);
            $principalPaidSoFar = (float) ($item['principal_paid'] ?? 0);
            $interestPaidSoFar = (float) ($item['interest_paid'] ?? 0);

            $principalOutstanding = round(max(0, $principalDue - $principalPaidSoFar), 2);
            $interestOutstanding = round(max(0, $interestDue - $interestPaidSoFar), 2);

            if ((float) $validated['principal_paid'] > $principalOutstanding + 0.001) {
                return response()->json([
                    'message' => "Principal paid exceeds outstanding principal for period {$validated['period']}.",
                ], 422);
            }

            if ((float) $validated['interest_paid'] > $interestOutstanding + 0.001) {
                return response()->json([
                    'message' => "Interest paid exceeds outstanding interest for period {$validated['period']}.",
                ], 422);
            }

            $item['principal_paid'] = round($principalPaidSoFar + (float) $validated['principal_paid'], 2);
            $item['interest_paid'] = round($interestPaidSoFar + (float) $validated['interest_paid'], 2);
            $item['last_payment_date'] = $validated['transaction_date'];

            $principalRemaining = round(max(0, $principalDue - (float) $item['principal_paid']), 2);
            $interestRemaining = round(max(0, $interestDue - (float) $item['interest_paid']), 2);

            if ($principalRemaining <= 0.001 && $interestRemaining <= 0.001) {
                $item['status'] = 'paid';
                $item['paid_date'] = $validated['transaction_date'];
            } else {
                $item['status'] = 'partially_paid';
            }

            $schedule[$foundIndex] = $item;
            $share->repayment_schedule = $schedule;

            if ($validated['principal_paid'] > 0) {
                $share->balance = round(max(0, $share->balance - $validated['principal_paid']), 2);
            }

            $allPaid = true;
            foreach ($schedule as $item) {
                if (($item['status'] ?? '') !== 'paid') {
                    $allPaid = false;
                    break;
                }
            }

            if ($allPaid) {
                $share->status = 'Withdrawn';
            }

            $share->save();

            CapitalShareTransaction::create([
                'capital_share_id' => $share->id,
                'transaction_type' => 'Repayment',
                'amount' => $validated['principal_paid'] + $validated['interest_paid'],
                'share_qty' => 0,
                'payment_method' => $validated['payment_method'],
                'transaction_date' => $validated['transaction_date'],
                'description' => $validated['description'] ?? "Repayment for Period " . $validated['period'],
                'performed_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Repayment successful',
                'data' => $share->fresh(),
            ]);
        });
    }

    public function sellShare(Request $request, string|int $id)
    {
        $this->ensurePermission($request, 'ui:capital_share:edit');

        $share = CapitalShare::findOrFail($id);

        if ($share->category !== 'Real Capital') {
            return response()->json([
                'message' => 'Sell Share is only available for Real Capital accounts.',
            ], 422);
        }

        if ((int) $share->share_qty <= 0 || (float) $share->balance <= 0.001) {
            return response()->json([
                'message' => 'No active shares available to sell.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($share, $validated) {
            $soldShares = (int) $share->share_qty;
            $withdrawAmount = round((float) $share->balance, 2);

            $share->update([
                'share_qty' => 0,
                'amount' => 0,
                'total_capital' => 0,
                'balance' => 0,
                'status' => 'Withdrawn',
            ]);

            CapitalShareTransaction::create([
                'capital_share_id' => $share->id,
                'transaction_type' => 'Withdrawal',
                'amount' => $withdrawAmount,
                'share_qty' => $soldShares,
                'payment_method' => $validated['payment_method'] ?? 'Cash',
                'transaction_date' => $validated['transaction_date'] ?? now(),
                'description' => $validated['description'] ?? 'Sell share and close account',
                'performed_by' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Share sold successfully.',
                'data' => $share->fresh(),
            ]);
        });
    }

    public function previewSchedule(Request $request, LoanCalculator $calculator)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'interest_rate' => 'required|numeric',
            'term_months' => 'required|integer',
            'payment_method' => 'required|string',
            'borrowing_date' => 'required|date',
            'currency' => 'nullable|string',
        ]);

        try {
            \Illuminate\Support\Facades\Log::info("Capital Preview Request: " . json_encode($validated));

            $method = $validated['payment_method'];
            // Map frontend friendly names to backend keys
            if ($method === 'Amortization')
                $method = 'annuity_monthly';
            if ($method === 'Interest Only')
                $method = 'Balloon';

            if ($method === 'Balloon') {
                $loanData = [
                    'amount' => $validated['amount'],
                    'interest_rate' => $validated['interest_rate'],
                    'duration_months' => $validated['term_months'],
                    'start_date' => $validated['borrowing_date'],
                ];
                $scheduleRaw = BalloonPaymentCalculator::generateSchedule($loanData, 'interest_only');

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
                $schedule = $calculator->calculateLoanWithDates(
                    $validated['amount'],
                    $validated['interest_rate'],
                    $validated['term_months'],
                    $method === 'negotiable' ? 'fixed_monthly' : $method,
                    $validated['borrowing_date'],
                    $validated['currency'] ?? 'USD'
                );
            }

            return response()->json($schedule);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function generateAccountNo()
    {
        return 'CSA-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}


