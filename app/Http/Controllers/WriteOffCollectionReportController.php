<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WriteOffCollectionReportController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $currency = $request->query('currency');

        // Main Query
        $query = Loan::query()
            ->select([
                'loans.id',
                'loans.loan_code',
                'loans.amount',
                'loans.currency',
                'loans.interest_rate',
                'loans.duration_months',
                'loans.start_date',
                'loans.maturity_date',
                'loans.status',
                'loans.recovery_amount',
                'loans.borrower_id',
                'loans.co_borrower_id',
                'loans.guarantor_id',
                'loans.loan_officer_id',
                'borrowers.first_name as borrower_first',
                'borrowers.last_name as borrower_last',
                'borrowers.phone as borrower_phone',
                'borrowers.village',
                'borrowers.commune',
                'borrowers.district',
                'borrowers.province',
                'co_borrowers.first_name as co_first',
                'co_borrowers.last_name as co_last',
                'guarantors.first_name as gua_first',
                'guarantors.last_name as gua_last',
                'loan_officers.name as officer_name',
                'loans.start_date as disbursement_date',
                DB::raw("(SELECT type FROM collaterals WHERE collaterals.loan_id = loans.id ORDER BY collaterals.id ASC LIMIT 1) as collateral_type"),
                DB::raw("(SELECT SUM(GREATEST(0, total_paid - interest_amount)) FROM payments WHERE payments.loan_id = loans.id) as total_principal_paid"),
                DB::raw("DATEDIFF(NOW(), loans.start_date) as aging"),
            ])
            ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->leftJoin('co_borrowers', 'loans.co_borrower_id', '=', 'co_borrowers.id')
            ->leftJoin('guarantors', 'loans.guarantor_id', '=', 'guarantors.id')
            ->leftJoin('loan_officers', 'loans.loan_officer_id', '=', 'loan_officers.id');

        if ($fromDate && $toDate) {
            $query->whereBetween('loans.start_date', [$fromDate, $toDate]);
        }

        if ($currency) {
            $query->where('loans.currency', $currency);
        }

        $loans = $query->get()->map(function ($loan) {
            // Calculate Aging (Days Overdue)
            // In a real system, this would look at the last unpaid installment
            // For this report, we'll mock it based on start_date for demonstration
            // unless we have an installments table to check.

            $borrowerName = $loan->borrower_last . ' ' . $loan->borrower_first;
            $coName = $loan->co_last ? ($loan->co_last . ' ' . $loan->co_first) : '';
            $guaName = $loan->gua_last ? ($loan->gua_last . ' ' . $loan->gua_first) : '';

            $aging = $loan->aging ?? 0;

            // Classification Logic (Standard, SM, Sub, Doubtful, Loss)
            $classification = "Standard Loan";
            if ($aging > 360)
                $classification = "Loss Loan";
            else if ($aging > 180)
                $classification = "Doubtful Loan";
            else if ($aging > 90)
                $classification = "Substandard Loan";
            else if ($aging > 30)
                $classification = "Special Mention Loan";

            // Calculate outstanding and arrear dynamically
            $principalPaid = $loan->total_principal_paid ?? 0;
            $outstanding = max(0, $loan->amount - $principalPaid);

            return [
                'disb_date' => $loan->disbursement_date,
                'loan_code' => $loan->loan_code,
                'customer_code' => $loan->borrower_id,
                'borrower_name' => $borrowerName,
                'phone_number' => $loan->borrower_phone,
                'co_borrower' => $coName,
                'guarantor' => $guaName,
                'village' => $loan->village,
                'commune' => $loan->commune,
                'district' => $loan->district,
                'province' => $loan->province,
                'collateral_type' => $loan->collateral_type ?? '',
                'co_repay' => $loan->officer_name,
                'maturity_date' => $loan->maturity_date,
                'currency' => $loan->currency,
                'term' => $loan->duration_months,
                'amount' => $loan->amount,
                'amount_default' => $outstanding,
                'default_balance' => $outstanding,
                'recovery_amount' => $loan->recovery_amount ?? 0,
                'aging' => $aging,
                'classification' => $classification,
            ];
        });


        // Group by classification (Excel export expects this format!)
        $grouped = $loans->groupBy('classification');

        // Ensure all categories exist (even if empty)
        $categories = [
            'Standard Loan' => [],
            'Special Mention Loan' => [],
            'Substandard Loan' => [],
            'Doubtful Loan' => [],
            'Loss Loan' => [],
        ];

        foreach ($grouped as $classification => $items) {
            $categories[$classification] = $items->values()->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
