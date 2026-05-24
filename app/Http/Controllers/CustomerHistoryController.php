<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;

class CustomerHistoryController extends Controller
{
    /** Build payments array for frontend from payments table (schedule + total_paid per installment). */
    private function loanToHistoryArray(Loan $loan): array
    {
        $arr = $loan->toArray();

        // Pre-load all repayment transactions for this loan in ONE query (fix N+1)
        $txIds = $loan->payments->pluck('repayment_transaction_id')->filter()->unique();
        $txMap = \App\Models\RepaymentTransaction::whereIn('id', $txIds)
            ->pluck('repayment_type', 'id');

        $arr['payments'] = $loan->payments->sortBy('payment_number')->values()->map(fn($p) => [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'principal_amount' => (float) $p->principal_amount,
            'interest_amount' => (float) $p->interest_amount,
            'fee_amount' => (float) ($p->fee_amount ?? 0),
            'penalty_amount' => (float) $p->penalty_amount,
            'total_paid' => (float) $p->total_paid,
            'total_due' => (float) ($p->total_due ?? 0),
            'payment_date' => $p->payment_date,
            'payment_method' => $p->payment_method,
            'updated_at' => ($p->total_paid > 0 && $p->updated_at) ? $p->updated_at->toIso8601String() : '',
            'prepayment' => (float) ($p->prepayment ?? 0),
            'repayment_transaction_id' => $p->repayment_transaction_id,
            'repayment_type' => $p->repayment_transaction_id ? ($txMap[$p->repayment_transaction_id] ?? null) : null,
        ])->all();

        // Add modifications to the history
        $arr['modifications'] = \App\Models\LoanModification::where('loan_id', $loan->id)
            ->latest()
            ->get()
            ->map(fn($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'old_data' => $m->old_data,
                'new_data' => $m->new_data,
                'notes' => $m->notes,
                'created_at' => $m->created_at->toIso8601String(),
            ])->all();
        return $arr;
    }
    /**
     * Search for customers across all roles.
     */
    public function search(Request $request)
    {
        $query = $request->query('query');
        if (!$query) {
            return response()->json([]);
        }

        $borrowers = Borrower::where(function ($q) use ($query) {
            $q->where('first_name', 'like', "%$query%")
                ->orWhere('last_name', 'like', "%$query%")
                ->orWhere('phone', 'like', "%$query%")
                ->orWhere('customer_code', 'like', "%$query%")
                ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(last_name, ' ', first_name)"), 'like', "%$query%")
                ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%$query%");
        })
        ->get()
        ->map(fn($item) => $this->formatSearchItem($item, 'Borrower'));

        return response()->json($borrowers);
    }

    private function formatSearchItem($item, $role)
    {
        return [
            'id' => $item->id,
            'name' => $item->first_name . ' ' . $item->last_name,
            'code' => $item->customer_code,
            'phone' => $item->phone,
            'role' => $role,
            'type' => strtolower(str_replace('-', '', $role)) // borrower, coborrower, guarantor
        ];
    }

    /**
     * Get detailed history for a specific customer.
     */
    public function getHistory(Request $request)
    {
        $id = $request->query('id');
        $type = $request->query('type'); // borrower, coborrower, guarantor

        if (!$id || !$type) {
            return response()->json(['error' => 'ID and Type are required'], 400);
        }

        $customer = null;
        $loans = [];

        switch ($type) {
            case 'borrower':
                $customer = Borrower::find($id);
                $loans = Loan::where('borrower_id', $id)
                    ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'coborrower':
                $customer = CoBorrower::find($id);
                $loans = Loan::where('co_borrower_id', $id)
                    ->with(['payments', 'collaterals', 'borrower', 'guarantor', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
            case 'guarantor':
                $customer = Guarantor::find($id);
                $loans = Loan::where('guarantor_id', $id)
                    ->with(['payments', 'collaterals', 'borrower', 'coBorrower', 'officer', 'product', 'paymentQr'])
                    ->get();
                break;
        }

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $loansPayload = $loans->map(fn(Loan $loan) => $this->loanToHistoryArray($loan))->values()->all();

        return response()->json([
            'customer' => $customer,
            'loans' => $loansPayload
        ]);
    }

    /**
     * Get customer history by contract / loan code.
     */
    public function getHistoryByContract(Request $request)
    {
        $contractNo = $request->query('contract_no');
        if (!$contractNo || !is_string($contractNo)) {
            return response()->json(['error' => 'contract_no is required'], 400);
        }

        $contractNo = trim($contractNo);
        $loan = Loan::where('loan_code', $contractNo)
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'borrower', 'product', 'paymentQr'])
            ->first();

        if (!$loan) {
            return response()->json(['error' => 'Contract not found'], 404);
        }

        $borrowerId = $loan->borrower_id;
        $customer = Borrower::find($borrowerId);
        if (!$customer) {
            return response()->json(['error' => 'Borrower not found'], 404);
        }

        $loans = Loan::where('borrower_id', $borrowerId)
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'product', 'paymentQr'])
            ->get();

        $loansPayload = $loans->map(fn(Loan $l) => $this->loanToHistoryArray($l))->values()->all();

        return response()->json([
            'customer' => $customer,
            'loans' => $loansPayload,
        ]);
    }
}
