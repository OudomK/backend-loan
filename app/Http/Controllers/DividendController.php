<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use App\Models\CapitalShareTransaction;
use App\Models\Dividend;
use App\Models\DividendTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DividendController extends Controller
{
    public function index()
    {
        return response()->json(Dividend::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'required|string|max:20',
            'total_amount' => 'nullable|numeric|min:0',
            'dividend_per_share' => 'nullable|numeric|min:0',
            'declared_date' => 'required|date',
        ]);

        if (empty($validated['total_amount']) && empty($validated['dividend_per_share'])) {
            return response()->json(['message' => 'Either total_amount or dividend_per_share is required'], 422);
        }

        return DB::transaction(function () use ($validated) {
            // Get only Real Capital shares (Loan Capital gets interest via repayment, not dividends)
            $shares = CapitalShare::where('currency', $validated['currency'])
                ->where('status', 'Active')
                ->where('category', 'Real Capital')
                ->get();

            $totalSharesCount = $shares->sum('share_qty');

            if ($totalSharesCount == 0) {
                return response()->json(['message' => 'No active shares found for this currency'], 422);
            }

            if (!empty($validated['total_amount'])) {
                $totalAmount = $validated['total_amount'];
                $perShare = $totalAmount / $totalSharesCount;
            } else {
                $perShare = $validated['dividend_per_share'];
                $totalAmount = $perShare * $totalSharesCount;
            }

            $dividend = Dividend::create([
                'total_amount' => $totalAmount,
                'dividend_per_share' => $perShare,
                'currency' => $validated['currency'],
                'total_shares_count' => $totalSharesCount,
                'declared_date' => $validated['declared_date'],
                'status' => 'Draft',
            ]);

            // Create pending transactions
            foreach ($shares as $share) {
                DividendTransaction::create([
                    'dividend_id' => $dividend->id,
                    'capital_share_id' => $share->id,
                    'amount' => $share->share_qty * $perShare,
                    'currency' => $validated['currency'],
                    'status' => 'Pending',
                ]);
            }

            return $dividend;
        });
    }

    public function distribute(Request $request, Dividend $dividend)
    {
        if ($dividend->status === 'Completed') {
            return response()->json(['message' => 'Dividend already distributed'], 400);
        }

        return DB::transaction(function () use ($dividend) {
            $dividend->update(['status' => 'Completed']);

            $transactions = $dividend->transactions()->where('status', 'Pending')->get();

            foreach ($transactions as $transaction) {
                // Update the transaction status
                $transaction->update([
                    'status' => 'Paid',
                    'paid_at' => now(),
                    'payment_method' => 'Cash',
                ]);

                // Increment the dividends in CapitalShare model
                $share = CapitalShare::find($transaction->capital_share_id);
                if ($share) {
                    $share->increment('dividends', $transaction->amount);

                    // Also record in CapitalShareTransaction
                    CapitalShareTransaction::create([
                        'capital_share_id' => $share->id,
                        'transaction_type' => 'Dividend',
                        'amount' => $transaction->amount,
                        'share_qty' => $share->share_qty,
                        'payment_method' => $transaction->payment_method ?? 'Cash',
                        'transaction_date' => now(),
                        'description' => 'Dividend distribution from declaration #' . $dividend->id,
                    ]);
                }
            }

            return response()->json(['message' => 'Dividend distributed successfully', 'dividend' => $dividend]);
        });
    }

    public function transactions(Dividend $dividend)
    {
        return response()->json($dividend->transactions()->with('share.borrower')->get());
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'currency' => 'required|string|max:20',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:total,per_share',
        ]);

        // Get only Real Capital shares for dividend preview
        $shares = CapitalShare::with('borrower')
            ->where('currency', $validated['currency'])
            ->where('status', 'Active')
            ->where('category', 'Real Capital')
            ->get();

        $totalSharesCount = $shares->sum('share_qty');

        if ($totalSharesCount == 0) {
            return response()->json([
                'shareholders_count' => 0,
                'total_shares' => 0,
                'per_share' => 0,
                'total_amount' => 0,
                'recipients' => []
            ]);
        }

        // Calculate amounts
        if ($validated['type'] === 'total') {
            $totalAmount = $validated['amount'];
            $perShare = $totalAmount / $totalSharesCount;
        } else {
            $perShare = $validated['amount'];
            $totalAmount = $perShare * $totalSharesCount;
        }

        // Build recipient list
        $recipients = $shares->map(function ($share) use ($perShare) {
            return [
                'holder_id' => $share->holder_id,
                'holder_name' => $share->borrower
                    ? $share->borrower->first_name . ' ' . $share->borrower->last_name
                    : 'Unknown',
                'share_qty' => $share->share_qty,
                'amount' => $share->share_qty * $perShare,
            ];
        });

        return response()->json([
            'shareholders_count' => $shares->count(),
            'total_shares' => $totalSharesCount,
            'per_share' => round($perShare, 4),
            'total_amount' => round($totalAmount, 2),
            'recipients' => $recipients
        ]);
    }

    public function getDividendReport()
    {
        $dividends = Dividend::with([
            'transactions.share' => function ($q) {
                $q->where('category', 'Real Capital');
            },
            'transactions.share.borrower'
        ])
            ->orderBy('declared_date', 'desc')
            ->get();

        $reportData = [];

        foreach ($dividends as $dividend) {
            foreach ($dividend->transactions as $transaction) {
                $share = $transaction->share;
                $borrower = $share->borrower;

                $reportData[] = [
                    // Dividend Declaration fields
                    'dividend_id' => $dividend->id,
                    'declared_date' => $dividend->declared_date,
                    'total_amount' => $dividend->total_amount,
                    'dividend_per_share' => $dividend->dividend_per_share,
                    'total_shares_count' => $dividend->total_shares_count,
                    'dividend_status' => $dividend->status,

                    // Shareholder Info
                    'holder_id' => $share->holder_id,
                    'holder_name' => $borrower
                        ? trim($borrower->first_name . ' ' . $borrower->last_name)
                        : 'Unknown',
                    'certificate_no' => $share->certificate_no,

                    // Share Details
                    'share_qty' => $share->share_qty,
                    'par_value' => $share->par_value,
                    'total_capital' => $share->total_capital,
                    'purchase_date' => $share->purchase_date,
                    'share_status' => $share->status,

                    // Transaction Details
                    'transaction_id' => $transaction->id,
                    'currency' => $transaction->currency,
                    'amount' => $transaction->amount,
                    'payment_method' => $transaction->payment_method,
                    'transaction_status' => $transaction->status,
                    'paid_at' => $transaction->paid_at,
                ];
            }
        }

        return response()->json($reportData);
    }
}
