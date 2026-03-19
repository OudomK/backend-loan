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
        $currency = $request->query('currency');
        $toDateStr = $request->query('to_date');
        $referenceDate = $toDateStr ? Carbon::parse($toDateStr) : Carbon::today();
        $refDateStr = $referenceDate->toDateString();
        $toDateStr = $refDateStr; // Use standardized YYYY-MM-DD for mapping too

        // Main Query
        $query = Loan::with([
            'borrower',
            'coBorrower',
            'guarantor',
            'officer',
            'collaterals'
        ])
            ->select([
                'id',
                'loan_code',
                'amount',
                'currency',
                'interest_rate',
                'duration_months',
                'start_date',
                'maturity_date',
                'status',
                'recovery_amount',
                'written_off_at',
                'borrower_id',
                'co_borrower_id',
                'guarantor_id',
                'loan_officer_id',
                'aging',
            ]);

        if ($toDateStr) {
            $query->where('start_date', '<=', $toDateStr);
        }

        // Only include loans that were not closed/canceled before the report date
        $query->whereIn('status', ['active', 'written_off']);

        if ($currency && $currency !== 'all') {
            $query->where('currency', $currency);
        }

        // Add Eloquent Subqueries (Now safer without joins)
        $query->addSelect([
            'earliest_arrear_date' => \App\Models\Payment::select('payment_date')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
                ->orderBy('payment_date', 'asc')
                ->limit(1),

            'arrear_principal' => \App\Models\Payment::selectRaw('SUM(principal_amount - GREATEST(0, total_paid - interest_amount))')
                ->whereColumn('loan_id', 'loans.id')
                ->where('payment_date', '<', $refDateStr)
                ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)'),

            'total_principal_paid' => \App\Models\Payment::selectRaw('SUM(GREATEST(0, total_paid - interest_amount))')
                ->whereColumn('loan_id', 'loans.id'),
        ]);

        $loans = $query->get()->map(function ($loan) use ($toDateStr) {
            $borrower = $loan->borrower;
            $borrowerName = $borrower ? ($borrower->last_name . ' ' . $borrower->first_name) : '';
            
            $co = $loan->coBorrower;
            $coName = $co ? ($co->last_name . ' ' . $co->first_name) : '';
            
            $gua = $loan->guarantor;
            $guaName = $gua ? ($gua->last_name . ' ' . $gua->first_name) : '';

            // Calculate DPD (Days Past Due)
            $aging = 0;
            $isToday = Carbon::parse($toDateStr)->isToday();

            if ($isToday) {
                // Use the persistent field for today's report
                $aging = (int) ($loan->aging ?? 0);
            } else {
                // Fallback to dynamic calculation for historical reports
                $refDate = Carbon::parse($toDateStr)->startOfDay();
                if ($loan->earliest_arrear_date) {
                    $earliest = Carbon::parse($loan->earliest_arrear_date)->startOfDay();
                    $aging = (int) abs($refDate->diffInDays($earliest, false));
                }
            }

            // Secondary fallback: If there is arrear principal, aging MUST be at least 1
            if ($aging <= 0 && ($loan->arrear_principal ?? 0) > 0) {
                $aging = 1; 
            }

            // Classification Logic (NBC Standard: 30, 90, 180, 360)
            $classification = "Standard Loan";
            if ($loan->written_off_at) {
                $classification = "Loss Loan";
            } else if ($aging >= 360) {
                $classification = "Loss Loan";
            } else if ($aging >= 180) {
                $classification = "Doubtful Loan";
            } else if ($aging >= 90) {
                $classification = "Substandard Loan";
            } else if ($aging >= 30) {
                $classification = "Special Mention Loan";
            }

            // Outstanding balance vs Arrear amount
            $principalPaid = $loan->total_principal_paid ?? 0;
            $outstanding = max(0, $loan->amount - $principalPaid);
            $arrearPrincipal = $loan->arrear_principal ?? 0;

            // First collateral type
            $collateralType = $loan->collaterals->first()?->type ?? '';

            return [
                'disb_date' => $loan->start_date,
                'loan_code' => $loan->loan_code,
                'customer_code' => $loan->borrower_id,
                'borrower_name' => $borrowerName,
                'phone_number' => $borrower->phone ?? '',
                'co_borrower' => $coName,
                'guarantor' => $guaName,
                'village' => $borrower->village ?? '',
                'commune' => $borrower->commune ?? '',
                'district' => $borrower->district ?? '',
                'province' => $borrower->province ?? '',
                'collateral_type' => $collateralType,
                'co_repay' => $loan->officer->name ?? '',
                'maturity_date' => $loan->maturity_date,
                'currency' => $loan->currency,
                'term' => $loan->duration_months,
                'amount' => $loan->amount,
                'amount_default' => $arrearPrincipal,
                'default_balance' => $outstanding,
                'recovery_amount' => $loan->recovery_amount ?? 0,
                'aging' => $aging,
                'classification' => $classification,
            ];
        });


        // Group by classification
        $grouped = $loans->groupBy('classification');

        // Ensure all categories exist
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
