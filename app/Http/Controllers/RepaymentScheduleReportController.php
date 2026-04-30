<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
                'loans.amount as loan_amount',
                'loans.duration_months',
                'borrowers.first_name',
                'borrowers.last_name',
                'borrowers.phone',
                'borrowers.village',
                'borrowers.commune',
                'borrowers.district',
                'borrowers.province',
                'loan_officers.name as officer_name',
                DB::raw('(payments.total_due - payments.total_paid) as remaining'),
                DB::raw('(SELECT COALESCE(SUM(p2.principal_amount), 0) FROM payments p2 WHERE p2.loan_id = payments.loan_id AND p2.payment_number <= payments.payment_number AND p2.deleted_at IS NULL) as cumulative_principal'),
                DB::raw('CASE WHEN payments.payment_date < CURDATE() AND payments.total_paid < payments.total_due THEN DATEDIFF(CURDATE(), payments.payment_date) ELSE 0 END as days_overdue')
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

        // Calculate outstanding balance (loan_amount - cumulative principal paid up to this installment)
        $schedules->transform(function ($item) {
            $item->outstanding_balance = round($item->loan_amount - $item->cumulative_principal, 2);
            if ($item->outstanding_balance < 0) {
                $item->outstanding_balance = 0;
            }
            // Format installment as "X/Y"
            $item->installment_display = $item->payment_number . '/' . $item->duration_months;
            
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }
}
