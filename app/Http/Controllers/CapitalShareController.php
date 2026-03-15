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
    public function index()
    {
        $shares = CapitalShare::with(['lender', 'investor'])->get();
        return $shares->map(function ($s) {
            $name = 'N/A';
            $code = '-';
            $type = 'Individual';

            if ($s->investor) {
                $name = $s->investor->last_name . ' ' . $s->investor->first_name;
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
                'created_at' => $s->created_at->toIso8601String(),

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
        });
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::info("Capital Share Store Request: " . json_encode($request->all()));

        $category = $request->input('category');
        $lenderTable = $category === 'Real Capital' ? 'investors' : 'lenders';

        $validated = $request->validate([
            'lender_id' => "required|exists:$lenderTable,id",
            'category' => 'required|string',
            'par_value' => 'nullable|numeric',
            'share_qty' => 'nullable|integer',
            'currency' => 'required|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'balance' => 'nullable|numeric',
            'dividends' => 'nullable|numeric',
            'total_dividend_paid' => 'nullable|numeric',
            'last_dividend_date' => 'nullable|date',
            'repayment_schedule' => 'nullable|array',
            'holder_id' => 'nullable|integer',
            'certificate_no' => 'nullable|string',
            'purchase_date' => 'nullable|date',
        ]);

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
        $share->total_capital = $share->amount;
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
            'amount' => $share->total_capital,
            'share_qty' => $share->share_qty,
            'payment_method' => $share->payment_method,
            'transaction_date' => $share->borrowing_date ?? now(),
            'description' => 'Initial capital purchase',
            'performed_by' => Auth::id(),
        ]);

        return response()->json($share, 201);
    }

    public function update(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $category = $request->input('category', $share->category);
        $lenderTable = $category === 'Real Capital' ? 'investors' : 'lenders';

        $validated = $request->validate([
            'lender_id' => "nullable|exists:$lenderTable,id",
            'category' => 'nullable|string',
            'status' => 'nullable|string',
            'share_qty' => 'nullable|integer',
            'par_value' => 'nullable|numeric',
            'currency' => 'nullable|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'balance' => 'nullable|numeric',
            'dividends' => 'nullable|numeric',
            'total_dividend_paid' => 'nullable|numeric',
            'last_dividend_date' => 'nullable|date',
            'repayment_schedule' => 'nullable|array',
            'holder_id' => 'nullable|integer',
            'certificate_no' => 'nullable|string',
            'purchase_date' => 'nullable|date',
        ]);

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

        if (isset($validated['amount'])) {
            $share->total_capital = $validated['amount'];
            $share->balance = $validated['amount'];
        }

        $share->fill($validated);
        $share->save();

        // Ensure balance is saved if it was updated above but not in $validated
        if ($share->isDirty('balance')) {
            $share->save();
        }

        return response()->json($share);
    }

    public function addCapital(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'share_qty' => 'nullable|integer|min:1',
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($share, $validated) {
            $parValue = $share->par_value > 0 ? (float) $share->par_value : 1;

            // Calculate based on what was provided
            if (!empty($validated['amount'])) {
                $addAmount = (float) $validated['amount'];
                $addShares = (int) floor($addAmount / $parValue);
            } else {
                $addShares = (int) $validated['share_qty'];
                $addAmount = $addShares * $parValue;
            }

            $newShareQty = (int) $share->share_qty + $addShares;
            $newTotalCapital = $newShareQty * $parValue;

            // Use direct DB update instead of Eloquent save() to avoid dirty-checking issues
            DB::table('capital_shares')->where('id', $share->id)->update([
                'share_qty' => $newShareQty,
                'total_capital' => $newTotalCapital,
                'amount' => $newTotalCapital,
                'balance' => $newTotalCapital,
                'updated_at' => now(),
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

    public function withdrawCapital(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'share_qty' => 'nullable|integer|min:1',
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $parValue = $share->par_value > 0 ? (float) $share->par_value : 1;

        // Calculate based on what was provided
        if (!empty($validated['amount'])) {
            $withdrawAmount = (float) $validated['amount'];
            $withdrawShares = (int) floor($withdrawAmount / $parValue);
        } else {
            $withdrawShares = (int) $validated['share_qty'];
            $withdrawAmount = $withdrawShares * $parValue;
        }

        if ((int) $share->share_qty < $withdrawShares) {
            return response()->json(['message' => 'Insufficient share quantity'], 400);
        }

        return DB::transaction(function () use ($share, $validated, $withdrawShares, $withdrawAmount, $parValue) {
            $newShareQty = (int) $share->share_qty - $withdrawShares;
            $newTotalCapital = $newShareQty * $parValue;

            DB::table('capital_shares')->where('id', $share->id)->update([
                'share_qty' => $newShareQty,
                'total_capital' => $newTotalCapital,
                'amount' => $newTotalCapital,
                'balance' => $newTotalCapital,
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

    public function getTransactions($id)
    {
        $transactions = CapitalShareTransaction::where('capital_share_id', $id)
            ->orderBy('transaction_date', 'desc')
            ->get();
        return response()->json($transactions);
    }

    public function repay(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'period' => 'required|integer',
            'principal_paid' => 'required|numeric',
            'interest_paid' => 'required|numeric',
            'payment_method' => 'required|string',
            'transaction_date' => 'required|date',
            'description' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($share, $validated) {
            $schedule = $share->repayment_schedule ?? [];
            $found = false;
            foreach ($schedule as &$item) {
                if ($item['period'] == $validated['period']) {
                    $item['status'] = 'paid';
                    $item['paid_date'] = $validated['transaction_date'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return response()->json(['message' => 'Period not found in schedule'], 400);
            }

            $share->repayment_schedule = $schedule;

            // Update balance if principal was paid
            if ($validated['principal_paid'] > 0) {
                $share->balance = max(0, $share->balance - $validated['principal_paid']);
            }

            // Check if all periods are paid to complete the account
            $allPaid = true;
            foreach ($schedule as $item) {
                if (($item['status'] ?? '') !== 'paid') {
                    $allPaid = false;
                    break;
                }
            }
            if ($allPaid) {
                $share->status = 'Completed';
            }

            $share->save();

            // Create transaction log
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
