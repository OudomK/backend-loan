<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanCollectionReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currency = $request->query('currency');

        $query = Payment::query()
            ->select([
                'payments.payment_date',
                'payments.principal_amount',
                'payments.interest_amount',
                'payments.total_paid', // To calculate if it's fully paid or partial, but report asks for "Schedule" usually means due amount.
                // However, "Collection" might mean what was collected. 
                // But the columns overlap with "Schedule".
                // Let's assume it lists the SCHEDULED amounts due in that period.
                'loans.loan_code',
                'loans.currency',
                'borrowers.id as borrower_id',
                'borrowers.first_name as borrower_first',
                'borrowers.last_name as borrower_last',
                'borrowers.phone',
                'borrowers.village',
                'borrowers.commune',
                'loan_officers.name as officer_name',
            ])
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->leftJoin('loan_officers', 'loans.loan_officer_id', '=', 'loan_officers.id');

        if ($fromDate && $toDate) {
            $query->whereBetween('payments.payment_date', [$fromDate, $toDate]);
        }

        if ($currency) {
            $query->where('loans.currency', $currency);
        }

        // Order by Currency then Date then Loan Code
        $query->orderBy('loans.currency')->orderBy('payments.payment_date')->orderBy('loans.loan_code');

        $results = $query->get()->map(function ($row) {
            return [
                'date' => $row->payment_date,
                'loan_code' => $row->loan_code,
                'cid' => $row->borrower_id,
                'name' => $row->borrower_last . ' ' . $row->borrower_first,
                'phone' => $row->phone,
                'co_name' => $row->officer_name,
                'village' => $row->village,
                'commune' => $row->commune,
                'principal' => $row->principal_amount,
                'interest' => $row->interest_amount,
                'fee' => 0, // Mock fee as 0 since we don't have it in payments table yet
                'total' => $row->principal_amount + $row->interest_amount,
                'currency' => $row->currency,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }
}
