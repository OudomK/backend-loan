<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Loan;
use Illuminate\Support\Facades\Log;

class TestDataController extends Controller
{
    public function checkData()
    {
        $results = [];

        // 1. Count loans
        $loanCount = DB::table('loans')->count();
        $results['total_loans'] = $loanCount;

        // 2. Sample loans
        $sampleLoans = DB::table('loans')
            ->select('id', 'loan_code', 'start_date', 'currency', 'amount')
            ->limit(5)
            ->get();
        $results['sample_loans'] = $sampleLoans;

        // 3. Count transactions
        $txnCount = DB::table('repayment_transactions')->count();
        $results['total_transactions'] = $txnCount;

        // 4. Sample transactions
        $sampleTxns = DB::table('repayment_transactions')
            ->select('id', 'loan_id', 'transaction_date', 'interest_paid')
            ->limit(5)
            ->get();
        $results['sample_transactions'] = $sampleTxns;

        // 5. Date ranges
        $loanDates = DB::table('loans')
            ->selectRaw('MIN(start_date) as min_date, MAX(start_date) as max_date')
            ->first();
        $results['loan_date_range'] = $loanDates;

        if ($txnCount > 0) {
            $txnDates = DB::table('repayment_transactions')
                ->selectRaw('MIN(transaction_date) as min_date, MAX(transaction_date) as max_date')
                ->first();
            $results['transaction_date_range'] = $txnDates;
        }

        // 6. Test the query with all data
        $testQuery = Loan::query()
            ->leftJoin('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->select([
                'loans.id',
                'loans.loan_code',
                'loans.start_date',
                'borrowers.first_name',
                'borrowers.last_name'
            ])
            ->limit(10)
            ->get();
        $results['test_query_results'] = $testQuery;

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }
}
