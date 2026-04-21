<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RepaymentScheduleReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $officerId = $request->input('officer_id');
        $currency = $request->input('currency');

        $query = Payment::query()
            ->select(
                'payments.id',
                'payments.payment_number',
                'payments.payment_date',
                'payments.principal_amount',
                'payments.interest_amount',
                'payments.fee_amount',
                'payments.total_due',
                'payments.penalty_amount',
                'payments.total_paid',
                'loans.loan_code',
                'loans.currency',
                'borrowers.first_name',
                'borrowers.last_name',
                'borrowers.phone',
                'loan_officers.name as officer_name'
            )
            ->join('loans', 'loans.id', '=', 'payments.loan_id')
            ->join('borrowers', 'borrowers.id', '=', 'loans.borrower_id')
            ->leftJoin('loan_officers', 'loan_officers.id', '=', 'loans.loan_officer_id')
            // Only upcoming or unpaid parts
            ->whereRaw('payments.total_paid < payments.total_due')
            ->whereNull('payments.deleted_at')
            ->whereNull('loans.deleted_at');

        if ($startDate) {
            $query->where('payments.payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('payments.payment_date', '<=', $endDate);
        }
        if ($officerId && strtolower($officerId) !== 'all') {
            $query->where('loans.loan_officer_id', $officerId);
        }
        if ($currency && $currency !== 'All') {
            if (str_contains(strtoupper($currency), 'USD')) {
                $query->where('loans.currency', 'USD');
            } elseif (str_contains(strtoupper($currency), 'KHR')) {
                $query->where('loans.currency', 'KHR');
            }
        }

        $schedules = $query->orderBy('payments.payment_date', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }
}
