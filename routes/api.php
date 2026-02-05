<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\CoBorrowerController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanOfficerController;
use App\Http\Controllers\LoanOperationController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RepaymentController;
use App\Http\Controllers\RescheduleRefinanceController;
use App\Http\Controllers\SavingAccountController;
use App\Http\Controllers\CapitalShareController;
use App\Http\Controllers\SaverController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\RepaymentReportController;
use App\Http\Controllers\ArrearReportController;
use App\Http\Controllers\DisbursementReportController;
use App\Http\Controllers\ActiveLoanReportController;
use App\Http\Controllers\InactiveLoanReportController;
use App\Http\Controllers\QualityPortfolioController;
use App\Http\Controllers\WriteOffReportController;
use App\Http\Controllers\WriteOffCollectionReportController;
use App\Http\Controllers\LoanCollectionReportController;
use App\Http\Controllers\InterestIncomeReportController;
Route::get('borrowers/next-code', [BorrowerController::class, 'getNextCode']);
Route::apiResource('borrowers', BorrowerController::class);

Route::get('co-borrowers/next-code', [CoBorrowerController::class, 'getNextCode']);
Route::apiResource('co-borrowers', CoBorrowerController::class);

Route::get('guarantors/next-code', [GuarantorController::class, 'getNextCode']);
Route::apiResource('guarantors', GuarantorController::class);

Route::get('/loan-officers', [LoanOfficerController::class, 'index']);
Route::post('loans/preview-schedule', [LoanController::class, 'previewSchedule']);
Route::apiResource('loans', LoanController::class);

// Repayments
Route::get('/repayments/due-list', [RepaymentController::class, 'getDueList']);
Route::get('/repayments/search', [RepaymentController::class, 'search']);
Route::get('/repayments/installments/{loan_id}', [RepaymentController::class, 'getInstallments']);
Route::post('/repayments', [RepaymentController::class, 'store']);

// Loan Operation
Route::get('/loan-operation/stats', [LoanOperationController::class, 'getStats']);
Route::get('/loan-operation/activity', [LoanOperationController::class, 'getRecentActivity']);

// Reschedule & Refinance
Route::get('/loan-modification/search', [RescheduleRefinanceController::class, 'searchActiveLoans']);
Route::post('/loan-modification/reschedule', [RescheduleRefinanceController::class, 'reschedule']);
Route::post('/loan-modification/refinance', [RescheduleRefinanceController::class, 'refinance']);

// Customer History
Route::get('customer-history/search', [CustomerHistoryController::class, 'search']);
Route::get('customer-history/details', [CustomerHistoryController::class, 'getHistory']);

// Fund Management
Route::apiResource('saving-accounts', SavingAccountController::class);
Route::post('saving-accounts/{account}/deposit', [SavingAccountController::class, 'deposit']);
Route::post('saving-accounts/{account}/withdraw', [SavingAccountController::class, 'withdraw']);
Route::apiResource('capital-shares', CapitalShareController::class);

// Savers & Investors
Route::get('savers/next-code', [SaverController::class, 'nextCode']);
Route::apiResource('savers', SaverController::class);

Route::get('investors/next-code', [InvestorController::class, 'nextCode']);
Route::apiResource('investors', InvestorController::class);

// External Borrowing Management
Route::get('borrowings', [BorrowingController::class, 'getBorrowings']);
Route::get('lenders', [BorrowingController::class, 'getLenders']);
Route::post('lenders', [BorrowingController::class, 'storeLender']);
Route::post('borrowings', [BorrowingController::class, 'storeBorrowing']);
Route::put('borrowings/{id}', [BorrowingController::class, 'updateBorrowing']);
Route::post('borrowings/repay', [BorrowingController::class, 'repayBorrowing']);

// Reports
Route::get('reports/repayment', [RepaymentReportController::class, 'index']);
Route::get('reports/arrear-all', [ArrearReportController::class, 'index']);
Route::get('reports/arrear-under-30', [ArrearReportController::class, 'index']);
Route::get('reports/disbursement', [DisbursementReportController::class, 'index']);
Route::get('reports/active-loan', [ActiveLoanReportController::class, 'index']);
Route::get('reports/inactive-loan', [InactiveLoanReportController::class, 'index']);
Route::get('reports/quality-portfolio', [QualityPortfolioController::class, 'index']);
Route::get('/reports/write-off', [WriteOffReportController::class, 'index']);
Route::get('/reports/write-off-collection', [WriteOffCollectionReportController::class, 'index']);
Route::get('/reports/loan-collection', [LoanCollectionReportController::class, 'index']);
Route::get('/reports/interest-income', [InterestIncomeReportController::class, 'index']);
Route::get('/test/check-data', [App\Http\Controllers\TestDataController::class, 'checkData']);

use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;

// ... existing routes ...

Route::apiResource('payments', PaymentController::class);

// HR & Payroll
Route::apiResource('positions', PositionController::class);
Route::apiResource('employees', EmployeeController::class);
Route::apiResource('payrolls', PayrollController::class);

Route::get('/dashboard/stats', function () {
    return response()->json([
        'total_customers' => \App\Models\Customer::count(),
        'total_loans' => \App\Models\Loan::count(),
        'active_loans' => \App\Models\Loan::where('status', 'active')->count(),
        'total_payments' => \App\Models\Payment::sum('total_paid'),
    ]);
});
