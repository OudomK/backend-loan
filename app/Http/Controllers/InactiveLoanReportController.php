<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class InactiveLoanReportController extends Controller
{
    public function index(Request $request)
    {
        $officerId = $request->query('officer_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Definition of "Inactive": Completed or Paid Off
        // (If there are other statuses like 'written_off' added later, include them here)
        $statuses = ['completed', 'paid_off'];

        $query = Loan::with([
            'borrower',
            'officer',
            'disburseOfficer',
            'collaterals'
        ])
            ->whereIn('status', $statuses);

        if ($officerId && $officerId !== 'all') {
            $query->where('loan_officer_id', $officerId);
        }

        // Filter by "Inactive Date" (Last Transaction or End Date?)
        // Usually reports are filtered by when they became inactive.
        // For now, let's use the loan 'end_date' or last transaction date if stored, 
        // OR simply filter by Disburse Date if that's what the user wants (standard reports often filter by range)
        // But "Inactive Loan Listing Report by Period" usually means loans that BECAME inactive in that period.
        // Since we don't have an explicit 'inactive_date' column, we'll try to use 'updated_at' or 'completed_at' if it existed.
        // For now, I will NOT apply a date filter on the main query unless I'm sure which date to check. 
        // I will assume the user might want ALL inactive loans or filtered by Disburse Date.
        // Actually, let's check Last Repayment Date approx.

        // Use subqueries for aggregates
        $query->addSelect([
            'last_payment_date' => \App\Models\RepaymentTransaction::select('transaction_date')
                ->whereColumn('loan_id', 'loans.id')
                ->orderBy('transaction_date', 'desc')
                ->limit(1),

            'total_principal_paid' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, LEAST(principal_amount, total_paid - interest_amount)))')
                ->whereColumn('loan_id', 'loans.id'),

            'total_interest_paid' => \App\Models\Payment::selectRaw('SUM(LEAST(interest_amount, total_paid))')
                ->whereColumn('loan_id', 'loans.id'),
        ]);

        $loans = $query->get();

        // Filter by Date Range (if provided) using the calculated Last Payment Date (Inactive Date)
        if ($fromDate && $toDate) {
            $loans = $loans->filter(function ($loan) use ($fromDate, $toDate) {
                if (!$loan->last_payment_date)
                    return false;
                return $loan->last_payment_date >= $fromDate && $loan->last_payment_date <= $toDate;
            });
        }

        $data = $loans->map(function ($loan) {
            $borrower = $loan->borrower;
            $officer = $loan->officer;

            // Inactive Date is essentially the last payment date when it was closed
            $inactiveDate = $loan->last_payment_date;

            return [
                'disbursement_date' => $loan->start_date,
                'loan_code' => $loan->loan_code,
                'client_code' => $borrower->customer_code ?? '', // field might be id_number or code
                'client_name' => $borrower ? ($borrower->last_name . ' ' . $borrower->first_name) : '',
                'village_name' => $borrower->village ?? '',
                'commune_name' => $borrower->commune ?? '',
                'district_name' => $borrower->district ?? '',
                'province_name' => $borrower->province ?? '',

                'disbursement_amount' => $loan->amount,
                'currency_code' => $loan->currency,
                'interest_rate' => $loan->interest_rate,
                'monthly_interest_rate' => $loan->interest_rate / 12,
                'term' => $loan->duration_months,
                'tenor' => strtolower($loan->payment_frequency ?? '') === 'monthly' ? 'Months' : 'ដង',
                'payment_method' => $loan->repayment_method,
                'loan_cycle' => $loan->loan_cycle,
                'refinance_amount' => $loan->refinanced_amount ?? 0,
                'admin_fee' => $loan->admin_fee,
                'processing_fee' => 0,
                'refinance_fee' => $loan->refinance_fee,

                'collateral_type' => $loan->collaterals->isNotEmpty() ? $loan->collaterals->first()->type : '',
                'co_disburse' => $loan->disburseOfficer ? $loan->disburseOfficer->name : ($officer ? $officer->name : ''),
                'co_repay' => $officer ? $officer->name : '',

                'outstanding_amount' => 0, // Inactive means 0
                'principal_paid' => in_array($loan->status, ['completed', 'paid_off']) ? $loan->amount : ($loan->total_principal_paid ?? 0),
                'interest_paid' => $loan->total_interest_paid ?? 0,
                'inactive_date' => $inactiveDate,
                'write_off_amount' => $loan->write_off_balance ?? 0,
            ];
        });

        return response()->json($data->values()); // Re-index keys after filter
    }
}
