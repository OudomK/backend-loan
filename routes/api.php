<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    $user = $request->user();
    $perms = $user->getAllPermissions()->pluck('name');
    $roles = $user->roles->pluck('name');
    \Illuminate\Support\Facades\Log::info("USER CHECK - ID: {$user->id}, Roles: " . json_encode($roles) . ", Perms Count: " . count($perms));
    \Illuminate\Support\Facades\Log::info("USER PERMISSIONS: " . json_encode($perms));
    return response()->json([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $roles->first() ?? $user->role ?? 'Staff',
        'roles' => $roles,
        'permissions' => $perms,
    ]);
})->middleware('auth:sanctum');

// Footer when not logged in: config fallback. When logged in, frontend uses GET /user.
Route::get('/app/footer-user', function () {
    return response()->json([
        'display_name' => Config::get('app.footer_user_name', '—'),
        'profile' => Config::get('app.footer_user_profile', '—'),
    ]);
});

// App settings (company name for header & Excel). Backend is source of truth.
Route::get('/app/settings', function () {
    return response()->json([
        'company_name' => Config::get('app.company_name', 'Company Name'),
    ]);
});

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
Route::post('saving-accounts/post-interest', [SavingAccountController::class, 'postInterest']);
Route::get('saving-accounts/{account}/transactions', [SavingAccountController::class, 'getTransactions']);
Route::post('saving-accounts/{account}/close', [SavingAccountController::class, 'closeAccount']);
Route::get('saving-accounts-report', [SavingAccountController::class, 'getSavingReport']);
Route::post('capital-shares/preview-schedule', [CapitalShareController::class, 'previewSchedule']);
Route::apiResource('capital-shares', CapitalShareController::class);
Route::post('capital-shares/{share}/repay', [CapitalShareController::class, 'repay']);
Route::post('capital-shares/{share}/add-capital', [CapitalShareController::class, 'addCapital']);
Route::post('capital-shares/{share}/withdraw-capital', [CapitalShareController::class, 'withdrawCapital']);
Route::get('capital-shares/{id}/transactions', [CapitalShareController::class, 'getTransactions']);
Route::post('capital-shares/{share}/sell', [CapitalShareController::class, 'sellShare']); // Legacy or alias

// Excel Export
use App\Http\Controllers\ExportController;
Route::get('export/saving-report', [ExportController::class, 'exportSavingReport']);
Route::get('export/capital-report', [ExportController::class, 'exportCapitalReport']);

// Dividend Management
use App\Http\Controllers\DividendController;
Route::get('/dividends-preview', [DividendController::class, 'preview']);
Route::get('/dividends-report', [DividendController::class, 'getDividendReport']);
Route::apiResource('dividends', DividendController::class);
Route::post('dividends/{dividend}/distribute', [DividendController::class, 'distribute']);
Route::get('dividends/{dividend}/transactions', [DividendController::class, 'transactions']);


// Savers & Investors
Route::get('savers/next-code', [SaverController::class, 'getNextCode']);
Route::apiResource('savers', SaverController::class);

Route::get('investors/next-code', [InvestorController::class, 'getNextCode']);
Route::apiResource('investors', InvestorController::class);

// External Borrowing Management
Route::get('borrowings', [BorrowingController::class, 'getBorrowings']);
Route::get('lenders', [BorrowingController::class, 'getLenders']);
Route::post('lenders', [BorrowingController::class, 'storeLender']);
Route::post('borrowings', [BorrowingController::class, 'storeBorrowing']);
Route::put('borrowings/{id}', [BorrowingController::class, 'updateBorrowing']);
Route::post('borrowings/repay', [BorrowingController::class, 'repayBorrowing']);

// Reports
use App\Http\Controllers\LoanOutstandingParReportController;
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
Route::get('/reports/loan-outstanding-par', [LoanOutstandingParReportController::class, 'index']);
Route::get('/test/check-data', [App\Http\Controllers\TestDataController::class, 'checkData']);

use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\LeaveRequestController;


// ... existing routes ...

Route::apiResource('payments', PaymentController::class);

// HR & Payroll
Route::apiResource('positions', PositionController::class);
Route::apiResource('employees', EmployeeController::class);
Route::apiResource('payrolls', PayrollController::class);
Route::apiResource('leave-requests', LeaveRequestController::class);

// Financial Reports
use App\Http\Controllers\IncomeStatementController;
Route::get('/reports/income-statement', [IncomeStatementController::class, 'index']);

// Miscellaneous Transactions
use App\Http\Controllers\MiscellaneousTransactionController;
Route::get('/miscellaneous-transactions', [MiscellaneousTransactionController::class, 'index']);
Route::post('/miscellaneous-transactions', [MiscellaneousTransactionController::class, 'store']);
Route::delete('/miscellaneous-transactions/{id}', [MiscellaneousTransactionController::class, 'destroy']);


use App\Http\Controllers\DashboardController;


Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
