<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use Illuminate\Http\Request;
use App\Services\LoanCalculator;
use App\Services\BalloonPaymentCalculator;

class CapitalShareController extends Controller
{
    public function index()
    {
        $shares = CapitalShare::with('lender')->get();
        return $shares->map(function ($s) {
            return [
                'id' => $s->id,
                'lender_id' => $s->lender_id,
                'lender_name' => $s->lender ? $s->lender->name : 'N/A',
                'lender_code' => $s->lender ? $s->lender->code : '-',
                'investor_name' => $s->lender ? $s->lender->name : 'N/A',
                'lender_type' => $s->lender ? ($s->lender->customer_type ?? 'Individual') : 'Individual',
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
                'payment_method' => $s->payment_method,
                'first_pay_date' => $s->first_pay_date,
                'term_months' => $s->term_months,
                'amount' => $s->amount,
                'interest_rate' => $s->interest_rate,
                'int_pay_mode' => $s->int_pay_mode,
                'fee' => $s->fee,
                'maturity_date' => $s->maturity_date,
                'sl_term' => $s->sl_term,
                'balance' => $s->balance,
                'dividends' => $s->dividends,
                'repayment_schedule' => $s->repayment_schedule,
            ];
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lender_id' => 'required|exists:lenders,id',
            'category' => 'required|string',
            'par_value' => 'nullable|numeric',
            'share_qty' => 'nullable|integer',
            'currency' => 'required|string',
            'payment_method' => 'nullable|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'first_pay_date' => 'nullable|date',
            'term_months' => 'nullable|integer',
            'amount' => 'nullable|numeric',
            'interest_rate' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable|string',
            'balance' => 'nullable|numeric',
            'dividends' => 'nullable|numeric',
            'repayment_schedule' => 'nullable|array',
        ]);

        $share = new CapitalShare();
        $share->lender_id = $validated['lender_id'];
        $share->account_no = $validated['account_no'] ?? $this->generateAccountNo();
        $share->category = $validated['category'];
        $share->par_value = $validated['par_value'] ?? 0;
        $share->share_qty = $validated['share_qty'] ?? 0;
        $share->total_capital = ($validated['par_value'] ?? 0) * ($validated['share_qty'] ?? 0);
        $share->currency = $validated['currency'];
        $share->status = 'Active';

        // Additional fields assignment
        $share->transaction_no = $validated['transaction_no'] ?? null;
        $share->loan_account = $validated['loan_account'] ?? null;
        $share->borrowing_date = $validated['borrowing_date'] ?? null;
        $share->contract_no = $validated['contract_no'] ?? null;
        $share->payment_method = $validated['payment_method'] ?? null;
        $share->first_pay_date = $validated['first_pay_date'] ?? null;
        $share->term_months = $validated['term_months'] ?? 0;
        $share->amount = $validated['amount'] ?? 0;
        $share->interest_rate = $validated['interest_rate'] ?? 0;
        $share->int_pay_mode = $validated['int_pay_mode'] ?? null;
        $share->fee = $validated['fee'] ?? 0;
        $share->maturity_date = $validated['maturity_date'] ?? null;
        $share->sl_term = $validated['sl_term'] ?? null;
        $share->balance = $validated['balance'] ?? 0;
        $share->dividends = $validated['dividends'] ?? 0;
        $share->repayment_schedule = $validated['repayment_schedule'] ?? null;

        $share->save();

        return response()->json($share, 201);
    }

    public function update(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'lender_id' => 'nullable|exists:lenders,id',
            'category' => 'nullable|string',
            'status' => 'nullable|string',
            'share_qty' => 'nullable|integer',
            'par_value' => 'nullable|numeric',
            'currency' => 'nullable|string',
            'payment_method' => 'nullable|string',

            // New Borrowing/Share Fields
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'borrowing_date' => 'nullable|date',
            'account_no' => 'nullable|string',
            'contract_no' => 'nullable|string',
            'first_pay_date' => 'nullable|date',
            'term_months' => 'nullable|integer',
            'amount' => 'nullable|numeric',
            'interest_rate' => 'nullable|numeric',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable|string',
            'balance' => 'nullable|numeric',
            'dividends' => 'nullable|numeric',
            'repayment_schedule' => 'nullable|array',
        ]);

        if (isset($validated['share_qty']) || isset($validated['par_value'])) {
            $qty = $validated['share_qty'] ?? $share->share_qty;
            $par = $validated['par_value'] ?? $share->par_value;
            $share->total_capital = $qty * $par;
        }

        $share->update($validated);
        return response()->json($share);
    }

    public function addCapital(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'share_qty' => 'required|integer|min:1',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $share->share_qty += $validated['share_qty'];
        $share->total_capital = $share->share_qty * $share->par_value;
        $share->save();

        // Optional: Create a transaction record if the model exists
        // CapitalShareTransaction::create([...]);

        return response()->json(['message' => 'Capital added successfully', 'data' => $share]);
    }

    public function withdrawCapital(Request $request, $id)
    {
        $share = CapitalShare::findOrFail($id);
        $validated = $request->validate([
            'share_qty' => 'required|integer|min:1',
            'payment_method' => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        if ($share->share_qty < $validated['share_qty']) {
            return response()->json(['message' => 'Insufficient share quantity'], 400);
        }

        $share->share_qty -= $validated['share_qty'];
        $share->total_capital = $share->share_qty * $share->par_value;
        $share->save();

        return response()->json(['message' => 'Capital withdrawn successfully', 'data' => $share]);
    }

    public function getTransactions($id)
    {
        // Placeholder or actual implementation if transactions table exists
        return response()->json([]);
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
            if ($validated['payment_method'] === 'Balloon') {
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
                    $validated['payment_method'] === 'negotiable' ? 'fixed_monthly' : $validated['payment_method'],
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
