<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\CoBorrower;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;

class CustomerHistoryController extends Controller
{
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

        return response()->json([
            'customer' => $customer,
            'loans' => $loans
        ]);
    }
}
