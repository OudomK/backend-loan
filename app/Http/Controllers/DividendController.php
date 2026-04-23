<?php

namespace App\Http\Controllers;

use App\Models\CapitalShare;
use App\Models\CapitalShareTransaction;
use App\Models\Dividend;
use App\Models\DividendSchedule;
use App\Models\DividendTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DividendController extends Controller
{
    private function getBoolSetting(string $key, bool $default = false): bool
    {
        $raw = Setting::where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $normalized = strtolower(trim((string) $raw));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function isDividendTaxEnabled(): bool
    {
        return $this->getBoolSetting('enable_dividend_tax', false);
    }

    private function isAutoDividendTaxEnabled(): bool
    {
        return $this->getBoolSetting('auto_dividend_tax', false);
    }

    private function getDividendTaxRate(): float
    {
        $raw = Setting::where('key', 'dividend_tax_rate')->value('value');
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $rate = (float) $raw;
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate > 100) {
            return 100.0;
        }

        return $rate;
    }

    private function transformDividend(Dividend $dividend): array
    {
        $arr = $dividend->toArray();
        $arr['declared_by_name'] = $dividend->declarer->name ?? null;

        return $arr;
    }

    private function ensurePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Unauthenticated.');

        $role = strtolower((string) ($user->roles()->pluck('name')->first() ?? $user->role ?? ''));
        if (in_array($role, ['admin', 'super_admin'], true) || $user->can($permission)) {
            return;
        }

        abort(403, 'You do not have permission to perform this action.');
    }

    private function resolveHolderName(?CapitalShare $share): string
    {
        if (!$share) {
            return 'Unknown';
        }

        if ($share->investor) {
            return trim(($share->investor->last_name ?? '') . ' ' . ($share->investor->first_name ?? ''));
        }

        if ($share->lender) {
            return (string) ($share->lender->name ?? 'Unknown');
        }

        if ($share->borrower) {
            return trim(($share->borrower->last_name ?? '') . ' ' . ($share->borrower->first_name ?? ''));
        }

        return 'Unknown';
    }

    public function index(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');
        $dividends = Dividend::with('declarer:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(Dividend $d) => $this->transformDividend($d));

        return response()->json($dividends);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');

        $validated = $request->validate([
            'currency' => 'required|string|max:20',
            'total_amount' => 'nullable|numeric|min:0',
            'dividend_per_share' => 'nullable|numeric|min:0',
            'distribution_basis' => 'required|in:total,per_share',
            'declared_date' => 'required|date',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'tax_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validated['distribution_basis'] === 'total' && !isset($validated['total_amount'])) {
            return response()->json(['message' => 'Total amount is required when distribution basis is Total Amount.'], 422);
        }

        if ($validated['distribution_basis'] === 'per_share' && !isset($validated['dividend_per_share'])) {
            return response()->json(['message' => 'Dividend per share is required when distribution basis is Per Share.'], 422);
        }

        return DB::transaction(function () use ($validated, $request) {
            // Get only Real Capital shares (Loan Capital gets interest via repayment, not dividends)
            $shares = CapitalShare::where('currency', $validated['currency'])
                ->where('status', 'Active')
                ->where('category', 'Real Capital')
                ->get();

            $totalSharesCount = $shares->sum('share_qty');

            if ($totalSharesCount == 0) {
                return response()->json(['message' => 'No active shares found for this currency'], 422);
            }

            if ($validated['distribution_basis'] === 'total') {
                $totalAmount = (float) $validated['total_amount'];
                $perShare = $totalAmount / $totalSharesCount;
            } else {
                $perShare = (float) $validated['dividend_per_share'];
                $totalAmount = $perShare * $totalSharesCount;
            }

            $totalAmount = round($totalAmount, 2);
            $perShare = round($perShare, 4);
            $taxAmount = 0.0;
            $isTaxEnabled = $this->isDividendTaxEnabled();
            $isAutoTaxEnabled = $this->isAutoDividendTaxEnabled();
            if ($isTaxEnabled && $isAutoTaxEnabled) {
                $rate = $this->getDividendTaxRate();
                $taxAmount = round($totalAmount * ($rate / 100), 2);
            } elseif ($isTaxEnabled) {
                $taxAmount = round((float) ($validated['tax_amount'] ?? 0), 2);
            }

            if ($taxAmount > $totalAmount + 0.001) {
                return response()->json([
                    'message' => 'Tax amount cannot be greater than total amount.',
                ], 422);
            }

            $netAmount = round($totalAmount - $taxAmount, 2);

            $dividend = Dividend::create([
                'total_amount' => $totalAmount,
                'dividend_per_share' => $perShare,
                'currency' => $validated['currency'],
                'distribution_basis' => $validated['distribution_basis'],
                'total_shares_count' => $totalSharesCount,
                'declared_date' => $validated['declared_date'],
                'payment_date' => $validated['payment_date'],
                'declared_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
                'tax_amount' => $taxAmount,
                'net_amount' => $netAmount,
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

            return response()->json($this->transformDividend($dividend->load('declarer:id,name')), 201);
        });
    }

    public function distribute(Request $request, Dividend $dividend)
    {
        $this->ensurePermission($request, 'ui:dividend');

        if ($dividend->status === 'Completed') {
            return response()->json(['message' => 'Dividend already distributed'], 400);
        }

        return DB::transaction(function () use ($dividend) {
            $dividend->update(['status' => 'Completed']);
            $paidAt = $dividend->payment_date
                ? \Carbon\Carbon::parse($dividend->payment_date)->endOfDay()
                : now();

            $transactions = $dividend->transactions()->where('status', 'Pending')->get();

            foreach ($transactions as $transaction) {
                // Update the transaction status
                $transaction->update([
                    'status' => 'Paid',
                    'paid_at' => $paidAt,
                    'payment_method' => 'Cash',
                ]);

                // Increment the dividends in CapitalShare model
                $share = CapitalShare::find($transaction->capital_share_id);
                if ($share) {
                    $share->increment('dividends', $transaction->amount);
                    $share->increment('total_dividend_paid', $transaction->amount);
                    $share->update(['last_dividend_date' => now()]);

                    // Also record in CapitalShareTransaction
                    CapitalShareTransaction::create([
                        'capital_share_id' => $share->id,
                        'transaction_type' => 'Dividend',
                        'amount' => $transaction->amount,
                        'share_qty' => $share->share_qty,
                        'payment_method' => $transaction->payment_method ?? 'Cash',
                        'transaction_date' => $dividend->payment_date ?? now(),
                        'description' => 'Dividend distribution from declaration #' . $dividend->id,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Dividend distributed successfully',
                'dividend' => $this->transformDividend($dividend->fresh()->load('declarer:id,name')),
            ]);
        });
    }

    public function transactions(Request $request, Dividend $dividend)
    {
        $this->ensurePermission($request, 'ui:dividend');
        return response()->json($dividend->transactions()->with(['share.investor', 'share.lender', 'share.borrower'])->get());
    }

    public function preview(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');

        $validated = $request->validate([
            'currency' => 'required|string|max:20',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:total,per_share',
        ]);

        // Get only Real Capital shares for dividend preview
        $shares = CapitalShare::with(['investor', 'lender', 'borrower'])
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
                'holder_name' => $this->resolveHolderName($share),
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

    // ─── Dividend Schedule (Semi-Auto) ───────────────────────────────────

    public function scheduleIndex(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');
        return response()->json(DividendSchedule::orderBy('id')->get());
    }

    public function scheduleStore(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');

        $validated = $request->validate([
            'currency' => 'required|string|max:20',
            'type' => 'required|in:per_share,total',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:monthly,quarterly,yearly',
            'day_of_month' => 'required|integer|min:1|max:28',
            'is_active' => 'sometimes|boolean',
        ]);

        // Upsert by currency (one schedule per currency)
        $schedule = DividendSchedule::updateOrCreate(
            ['currency' => $validated['currency']],
            $validated
        );

        return response()->json($schedule, 200);
    }

    public function scheduleToggle(Request $request, DividendSchedule $schedule)
    {
        $this->ensurePermission($request, 'ui:dividend');
        $schedule->update(['is_active' => !$schedule->is_active]);
        return response()->json($schedule);
    }

    // ────────────────────────────────────────────────────────────────────

    public function getDividendReport(Request $request)
    {
        $this->ensurePermission($request, 'ui:dividend');

        $dividends = Dividend::with([
            'transactions.share' => function ($q) {
                $q->where('category', 'Real Capital');
            },
            'transactions.share.investor',
            'transactions.share.lender',
            'transactions.share.borrower',
            'declarer:id,name',
        ])
            ->orderBy('declared_date', 'desc')
            ->get();

        $reportData = [];

        foreach ($dividends as $dividend) {
            foreach ($dividend->transactions as $transaction) {
                $share = $transaction->share;
                if (!$share) {
                    continue;
                }

                $reportData[] = [
                    // Dividend Declaration fields
                    'dividend_id' => $dividend->id,
                    'declared_date' => $dividend->declared_date,
                    'total_amount' => $dividend->total_amount,
                    'dividend_per_share' => $dividend->dividend_per_share,
                    'total_shares_count' => $dividend->total_shares_count,
                    'distribution_basis' => $dividend->distribution_basis,
                    'dividend_status' => $dividend->status,
                    'payment_date' => $dividend->payment_date,
                    'declared_by' => $dividend->declared_by,
                    'declared_by_name' => $dividend->declarer->name ?? null,
                    'notes' => $dividend->notes,
                    'tax_amount' => $dividend->tax_amount,
                    'net_amount' => $dividend->net_amount,

                    // Shareholder Info
                    'holder_id' => $share->holder_id,
                    'holder_name' => $this->resolveHolderName($share),
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
