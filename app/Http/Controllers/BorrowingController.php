<?php

namespace App\Http\Controllers;

use App\Models\Borrowing;
use App\Models\BorrowingRepayment;
use App\Models\Lender;
use App\Models\Investor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowingController extends Controller
{
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

    public function getBorrowings()
    {
        $borrowings = Borrowing::with('lender', 'repayments')
            ->orderBy('borrowing_date', 'desc')
            ->get();

        return response()->json($borrowings->map(function ($b) {
            $totalPrincipalPaid = (float) $b->repayments->sum('principal_paid');
            $totalInterestPaid = (float) $b->repayments->sum('interest_paid');
            $balance = round((float) $b->amount - $totalPrincipalPaid, 2);

            return [
                'id' => $b->id,
                'lender_id' => $b->lender_id,
                'lender_code' => $b->lender->lender_code,
                'lender_name' => $b->lender->name,
                'lender_type' => $b->lender->lender_type,
                'borrowing_date' => $b->borrowing_date,
                'transaction_no' => $b->transaction_no,
                'loan_account' => $b->loan_account,
                'account_no' => $b->account_no,
                'category' => $b->category,
                'contract_no' => $b->contract_no,
                'payment_method' => $b->payment_method,
                'int_pay_mode' => $b->int_pay_mode,
                'first_pay_date' => $b->first_pay_date,
                'currency' => $b->currency,
                'term_months' => $b->term_months,
                'amount' => $b->amount,
                'interest_rate' => $b->interest_rate,
                'fee' => $b->fee,
                'maturity_date' => $b->maturity_date,
                'sl_term' => $b->sl_term,
                'balance' => $balance,
                'total_paid' => $totalPrincipalPaid + $totalInterestPaid,
                'status' => $b->status,
                'late_principal' => $b->late_principal ?? 0,
                'loan_interest' => $b->loan_interest ?? 0,
            ];
        }));
    }

    public function getLenders()
    {
        return response()->json(Lender::orderBy('name')->get());
    }

    public function storeLender(Request $request)
    {
        $this->ensurePermission($request, 'ui:savings:create');

        $validated = $request->validate([
            'lender_code' => 'required|unique:lenders',
            'name' => 'required',
            'lender_type' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable',
        ]);

        $lender = Lender::create($validated);
        return response()->json($lender);
    }

    public function updateLender(Request $request, $id)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $lender = Lender::findOrFail($id);
        $validated = $request->validate([
            'lender_code' => 'required|unique:lenders,lender_code,' . $id,
            'name' => 'required',
            'lender_type' => 'required',
            'phone' => 'nullable',
            'address' => 'nullable',
        ]);

        $lender->update($validated);
        return response()->json($lender);
    }

    public function updateBorrowing(Request $request, $id)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $borrowing = Borrowing::with('repayments')->findOrFail($id);

        $validated = $request->validate([
            'lender_id' => 'required_without:investor_id|nullable|exists:lenders,id',
            'investor_id' => 'nullable|exists:investors,id',
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'required|in:Real Capital,Loan Capital',
            'borrowing_date' => 'required|date',
            'account_no' => 'nullable',
            'contract_no' => 'nullable|string',
            'payment_method' => 'required',
            'currency' => 'required',
            'term_months' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0',
            'interest_rate' => 'required|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric|min:0',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric|min:0',
            'loan_interest' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['lender_id']) && !empty($validated['investor_id'])) {
            $validated['lender_id'] = $this->getOrCreateLenderFromInvestor($validated['investor_id']);
        }

        $totalPrincipalPaid = (float) $borrowing->repayments->sum('principal_paid');
        $newAmount = (float) $validated['amount'];

        if ($newAmount + 0.001 < $totalPrincipalPaid) {
            return response()->json([
                'message' => 'Loan amount cannot be less than the principal already repaid.',
            ], 422);
        }

        $validated['status'] = $totalPrincipalPaid + 0.001 >= $newAmount ? 'completed' : 'active';

        $borrowing->update($validated);
        return response()->json($borrowing);
    }

    public function storeBorrowing(Request $request)
    {
        $this->ensurePermission($request, 'ui:savings:create');

        $validated = $request->validate([
            'lender_id' => 'required_without:investor_id|nullable|exists:lenders,id',
            'investor_id' => 'nullable|exists:investors,id',
            'transaction_no' => 'nullable|string',
            'loan_account' => 'nullable|string',
            'category' => 'required|in:Real Capital,Loan Capital',
            'borrowing_date' => 'required|date',
            'account_no' => 'nullable',
            'contract_no' => 'nullable|string',
            'payment_method' => 'required',
            'currency' => 'required',
            'term_months' => 'required|integer|min:1',
            'amount' => 'required|numeric|gt:0',
            'interest_rate' => 'required|numeric|min:0',
            'int_pay_mode' => 'nullable|string',
            'fee' => 'nullable|numeric|min:0',
            'first_pay_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'sl_term' => 'nullable',
            'late_principal' => 'nullable|numeric|min:0',
            'loan_interest' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['lender_id']) && !empty($validated['investor_id'])) {
            $validated['lender_id'] = $this->getOrCreateLenderFromInvestor($validated['investor_id']);
        }

        $validated['status'] = 'active';

        $borrowing = Borrowing::create($validated);
        return response()->json($borrowing);
    }

    private function getOrCreateLenderFromInvestor($investorId)
    {
        $investor = Investor::findOrFail($investorId);

        // Check if a lender already exists for this investor
        $lender = Lender::where('investor_id', $investor->id)->first();

        if (!$lender) {
            // Create a new lender record linked to this investor
            $lender = Lender::create([
                'investor_id' => $investor->id,
                'lender_code' => $investor->customer_code,
                'name' => trim($investor->first_name . ' ' . $investor->last_name),
                'lender_type' => 'Individual', // Default type for investors
                'phone' => $investor->phone,
                'address' => trim(($investor->village ?? '') . ' ' . ($investor->commune ?? '') . ' ' . ($investor->district ?? '') . ' ' . ($investor->province ?? '')),
            ]);
        }

        return $lender->id;
    }

    public function repayBorrowing(Request $request)
    {
        $this->ensurePermission($request, 'ui:savings:edit');

        $validated = $request->validate([
            'borrowing_id' => 'required|exists:borrowings,id',
            'payment_date' => 'required|date',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'penalty_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'required',
            'reference_no' => 'nullable',
            'remarks' => 'nullable',
        ]);

        $validated['penalty_paid'] = $validated['penalty_paid'] ?? 0;
        $validated['total_paid'] = $validated['principal_paid'] + $validated['interest_paid'] + $validated['penalty_paid'];

        return DB::transaction(function () use ($validated) {
            $borrowing = Borrowing::with('repayments')->findOrFail($validated['borrowing_id']);
            $alreadyPaid = (float) $borrowing->repayments->sum('principal_paid');
            $remainingBalance = round((float) $borrowing->amount - $alreadyPaid, 2);

            if ($remainingBalance <= 0.001) {
                return response()->json([
                    'message' => 'This borrowing is already fully repaid.',
                ], 422);
            }

            if ($validated['principal_paid'] > $remainingBalance + 0.001) {
                return response()->json([
                    'message' => "Principal paid ({$validated['principal_paid']}) exceeds remaining balance (" . number_format($remainingBalance, 2) . "). Please check the amount.",
                ], 422);
            }

            // Standard Fields
            $validated['balance_after_payment'] = round($remainingBalance - (float) $validated['principal_paid'], 2);
            $validated['received_by'] = auth()->id();
            
            // Generate Receipt No: BR-YYYYMMDD-COUNT
            $todayCount = BorrowingRepayment::whereDate('created_at', now())->count();
            $validated['receipt_no'] = 'BR-' . now()->format('Ymd') . '-' . str_pad($todayCount + 1, 3, '0', STR_PAD_LEFT);

            // Sync with schedule
            $schedules = $borrowing->schedules()->where('status', '!=', 'paid')->orderBy('installment_no')->get();
            $pLeft = (float) $validated['principal_paid'];
            $iLeft = (float) $validated['interest_paid'];
            $penLeft = (float) $validated['penalty_paid'];

            $firstScheduleId = null;

            foreach ($schedules as $sch) {
                if ($iLeft <= 0 && $pLeft <= 0 && $penLeft <= 0) break;

                if (!$firstScheduleId) $firstScheduleId = $sch->id;

                $schInterestDue = (float) $sch->interest_due - (float) $sch->interest_paid;
                $schPrincipalDue = (float) $sch->principal_due - (float) $sch->principal_paid;

                // 1. Allocate Penalty (if any) - We assume penalty pays off current row first
                if ($penLeft > 0) {
                    $sch->penalty_paid += $penLeft;
                    $penLeft = 0; // Simplified: apply all penalty to the first unpaid row encountered
                }

                // 2. Allocate Interest
                if ($iLeft > 0 && $schInterestDue > 0) {
                    $iAlloc = min($iLeft, $schInterestDue);
                    $sch->interest_paid += $iAlloc;
                    $iLeft -= $iAlloc;
                }

                // 3. Allocate Principal
                if ($pLeft > 0 && $schPrincipalDue > 0) {
                    $pAlloc = min($pLeft, $schPrincipalDue);
                    $sch->principal_paid += $pAlloc;
                    $pLeft -= $pAlloc;
                }

                // Update Status
                $totalDue = (float) $sch->total_due;
                $totalPaid = (float) $sch->principal_paid + (float) $sch->interest_paid;
                
                $sch->last_payment_date = $validated['payment_date'];

                if ($totalPaid >= $totalDue - 0.001) {
                    $sch->status = 'paid';
                    $sch->paid_date = $validated['payment_date'];
                } elseif ($totalPaid > 0.001) {
                    $sch->status = 'partially_paid';
                }

                $sch->save();
            }

            $validated['schedule_id'] = $firstScheduleId;
            $repayment = BorrowingRepayment::create($validated);

            $totalPrincipalPaid = $alreadyPaid + (float) $validated['principal_paid'];
            $borrowing->update([
                'status' => $totalPrincipalPaid + 0.001 >= (float) $borrowing->amount ? 'completed' : 'active',
            ]);

            return response()->json($repayment);
        });
    }

    public function getSchedule($id)
    {
        $borrowing = Borrowing::with(['schedules' => function ($q) {
            $q->orderBy('installment_no', 'asc');
        }])->findOrFail($id);

        $today = now()->startOfDay();

        $schedule = $borrowing->schedules->map(function ($sch) use ($today) {
            $dueDate = \Carbon\Carbon::parse($sch->due_date)->startOfDay();
            $daysLate = 0;

            if ($sch->status === 'paid' && $sch->paid_date) {
                $paidDate = \Carbon\Carbon::parse($sch->paid_date)->startOfDay();
                if ($paidDate->gt($dueDate)) {
                    $daysLate = $paidDate->diffInDays($dueDate);
                }
            } elseif ($sch->status !== 'paid' && $today->gt($dueDate)) {
                $daysLate = $today->diffInDays($dueDate);
            }

            $sch->days_late = $daysLate;
            return $sch;
        });

        return response()->json($schedule);
    }

    public function getRepayments($id)
    {
        $repayments = BorrowingRepayment::with('receivedByUser')
            ->where('borrowing_id', $id)
            ->orderBy('payment_date', 'desc')
            ->get();

        return response()->json($repayments);
    }
}
