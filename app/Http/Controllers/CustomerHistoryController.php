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
        $arr['payments'] = $loan->payments->sortBy('payment_number')->values()->map(fn ($p) => [
            'id' => $p->id,
            'payment_number' => $p->payment_number,
            'principal_amount' => (float) $p->principal_amount,
            'interest_amount' => (float) $p->interest_amount,
            'fee_amount' => 0.0,
            'penalty_amount' => (float) $p->penalty_amount,
            'total_paid' => (float) $p->total_paid,
            'payment_date' => $p->payment_date,
            'payment_method' => $p->payment_method,
            'updated_at' => $p->updated_at?->toIso8601String() ?? '',
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

        $borrowers = Borrower::where('first_name', 'like', "%$query%")
            ->orWhere('last_name', 'like', "%$query%")
            ->orWhere('phone', 'like', "%$query%")
            ->orWhere('customer_code', 'like', "%$query%")
            ->get()
            ->map(fn($item) => $this->formatSearchItem($item, 'Borrower'));

        $coBorrowers = CoBorrower::where('first_name', 'like', "%$query%")
            ->orWhere('last_name', 'like', "%$query%")
            ->orWhere('phone', 'like', "%$query%")
            ->orWhere('customer_code', 'like', "%$query%")
            ->get()
            ->map(fn($item) => $this->formatSearchItem($item, 'Co-Borrower'));

        $guarantors = Guarantor::where('first_name', 'like', "%$query%")
            ->orWhere('last_name', 'like', "%$query%")
            ->orWhere('phone', 'like', "%$query%")
            ->orWhere('customer_code', 'like', "%$query%")
            ->get()
            ->map(fn($item) => $this->formatSearchItem($item, 'Guarantor'));

        return response()->json($borrowers->concat($coBorrowers)->concat($guarantors));
    }

    private function formatSearchItem($item, $role)
    {
        return [
            'id' => $item->id,
            'name' => $item->last_name . ' ' . $item->first_name,
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
                    ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer'])
                    ->get();
                break;
            case 'coborrower':
                $customer = CoBorrower::find($id);
                $loans = Loan::where('co_borrower_id', $id)
                    ->with(['payments', 'collaterals', 'borrower', 'guarantor', 'officer'])
                    ->get();
                break;
            case 'guarantor':
                $customer = Guarantor::find($id);
                $loans = Loan::where('guarantor_id', $id)
                    ->with(['payments', 'collaterals', 'borrower', 'coBorrower', 'officer'])
                    ->get();
                break;
        }

        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $loansPayload = $loans->map(fn (Loan $loan) => $this->loanToHistoryArray($loan))->values()->all();

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
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer', 'borrower'])
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
            ->with(['payments', 'collaterals', 'coBorrower', 'guarantor', 'officer'])
            ->get();

        $loansPayload = $loans->map(fn (Loan $l) => $this->loanToHistoryArray($l))->values()->all();

        return response()->json([
            'customer' => $customer,
            'loans' => $loansPayload,
        ]);
    }
}
