<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ActiveLoanReportController;
use App\Http\Controllers\ArrearReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\CapitalShareController;
use App\Http\Controllers\CoBorrowerController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerExportController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisbursementReportController;
use App\Http\Controllers\DividendController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GuarantorController;
use App\Http\Controllers\IncomeStatementController;
use App\Http\Controllers\InactiveLoanReportController;
use App\Http\Controllers\InterestIncomeReportController;
use App\Http\Controllers\InvestorController;
use App\Http\Controllers\LoanCollectionReportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanOfficerController;
use App\Http\Controllers\LoanOperationController;
use App\Http\Controllers\LoanOutstandingParReportController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\MiscellaneousTransactionController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\QualityPortfolioController;
use App\Http\Controllers\RepaymentController;
use App\Http\Controllers\RepaymentReportController;
use App\Http\Controllers\RepaymentScheduleReportController;
use App\Http\Controllers\RescheduleRefinanceController;
use App\Http\Controllers\RevenueCategoryController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SavingAccountController;
use App\Http\Controllers\SaverController;
use App\Http\Controllers\WriteOffCollectionReportController;
use App\Http\Controllers\TestDataController;
use App\Http\Controllers\WriteOffReportController;

// ——— Public routes (no auth) ———
Route::post('/login', [AuthController::class, 'login']);

Route::get('/app/footer-user', function () {
    return response()->json([
        'display_name' => \App\Models\Setting::where('key', 'company_name')->value('value') ?? Config::get('app.footer_user_name', '—'),
        'profile' => Config::get('app.footer_user_profile', '—'),
    ]);
});

Route::get('/app/settings', function () {
    $dbSettings = \App\Models\Setting::pluck('value', 'key')->toArray();
    $toBool = static function (mixed $value, bool $fallback = false): bool {
        if ($value === null) {
            return $fallback;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }

        return $fallback;
    };
    return response()->json([
        'company_name' => $dbSettings['company_name'] ?? Config::get('app.company_name', 'Company Name'),
        'company_logo' => isset($dbSettings['company_logo']) ? asset('storage/' . $dbSettings['company_logo']) : null,
        'default_language' => $dbSettings['default_language'] ?? 'EN',
        'frontend_font_family' => $dbSettings['frontend_font_family'] ?? 'battambang',
        'excel_export_font' => $dbSettings['excel_export_font'] ?? 'Khmer OS Siemreap',
        'copyright_text' => $dbSettings['copyright_text'] ?? ('© ' . date('Y') . ' ' . Config::get('app.company_name')),
        'exchange_rate' => $dbSettings['exchange_rate_khr_to_usd'] ?? $dbSettings['exchange_rate'] ?? 4000,
        'default_interest_rate' => $dbSettings['default_interest_rate'] ?? 1.5,
        'default_penalty_usd' => $dbSettings['default_penalty_usd'] ?? 2.5,
        'default_penalty_khr' => $dbSettings['default_penalty_khr'] ?? 10000,
        'enable_dividend_tax' => $toBool($dbSettings['enable_dividend_tax'] ?? false, false),
        'auto_dividend_tax' => $toBool($dbSettings['auto_dividend_tax'] ?? false, false),
        'dividend_tax_rate' => (float) ($dbSettings['dividend_tax_rate'] ?? 0),
        'default_payment_qr_id' => $dbSettings['default_payment_qr_id'] ?? null,
        'co_phone_display_mode' => $dbSettings['co_phone_display_mode'] ?? 'one_line',
        'co_phone_display_count' => $dbSettings['co_phone_display_count'] ?? '3',
    ]);
});

// ——— Protected routes (auth:sanctum) ———
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/admin/sso', [AuthController::class, 'getSsoUrl']);

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $perms = $user->getAllPermissions()->pluck('name');
        $roles = $user->roles->pluck('name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $roles->first() ?? $user->role ?? 'Staff',
            'roles' => $roles,
            'permissions' => $perms,
        ]);
    });

    Route::post('/customers/import', [CustomerImportController::class, 'import']);
    Route::get('/customers/import/template', [CustomerImportController::class, 'downloadTemplate']);
    Route::get('/customers/export', [CustomerExportController::class, 'export']);

    Route::get('borrowers/next-code', [BorrowerController::class, 'getNextCode']);
    Route::apiResource('borrowers', BorrowerController::class);

    Route::get('co-borrowers/next-code', [CoBorrowerController::class, 'getNextCode']);
    Route::apiResource('co-borrowers', CoBorrowerController::class);

    Route::get('guarantors/next-code', [GuarantorController::class, 'getNextCode']);
    Route::apiResource('guarantors', GuarantorController::class);

    Route::apiResource('loan-products', LoanProductController::class);
    Route::apiResource('loan-officers', LoanOfficerController::class);
    Route::get('payment-qrs', [LoanController::class, 'getPaymentQrs']);
    Route::post('loans/preview-schedule', [LoanController::class, 'previewSchedule']);
    Route::get('loans/suggest-code', [LoanController::class, 'suggestCode']);
    Route::apiResource('loans', LoanController::class);

    Route::get('/repayments/due-list', [RepaymentController::class, 'getDueList'])->middleware('permission:ui:repayment:view');
    Route::get('/repayments/search', [RepaymentController::class, 'search'])->middleware('permission:ui:repayment:view');
    Route::get('/repayments/installments/{loan_id}', [RepaymentController::class, 'getInstallments'])->middleware('permission:ui:repayment:view');
    Route::post('/repayments', [RepaymentController::class, 'store'])->middleware('permission:ui:repayment:create');
    Route::delete('/repayments/{id}/void', [RepaymentController::class, 'destroy'])->middleware('permission:ui:repayment:delete');

    Route::get('/loan-operation/stats', [LoanOperationController::class, 'getStats']);
    Route::get('/loan-operation/activity', [LoanOperationController::class, 'getRecentActivity']);

    Route::get('/loan-modification/search', [RescheduleRefinanceController::class, 'searchActiveLoans']);
    Route::post('/loan-modification/reschedule', [RescheduleRefinanceController::class, 'reschedule']);
    Route::post('/loan-modification/refinance', [RescheduleRefinanceController::class, 'refinance']);
    Route::post('/loan-modification/preview', [RescheduleRefinanceController::class, 'previewModification']);

    Route::get('customer-history/search', [CustomerHistoryController::class, 'search']);
    Route::get('customer-history/details', [CustomerHistoryController::class, 'getHistory']);
    Route::get('customer-history/by-contract', [CustomerHistoryController::class, 'getHistoryByContract']);

    Route::apiResource('saving-accounts', SavingAccountController::class);
    Route::post('saving-accounts/{account}/deposit', [SavingAccountController::class, 'deposit']);
    Route::post('saving-accounts/{account}/withdraw', [SavingAccountController::class, 'withdraw']);
    Route::post('saving-accounts/post-interest', [SavingAccountController::class, 'postInterest']);
    Route::get('saving-accounts/{account}/transactions', [SavingAccountController::class, 'getTransactions']);
    Route::post('saving-accounts/{account}/close', [SavingAccountController::class, 'closeAccount']);
    Route::post('capital-shares/preview-schedule', [CapitalShareController::class, 'previewSchedule']);
    Route::apiResource('capital-shares', CapitalShareController::class);
    Route::post('capital-shares/{share}/repay', [CapitalShareController::class, 'repay'])->middleware('permission:Update:CapitalShareTransaction');
    Route::post('capital-shares/{share}/add-capital', [CapitalShareController::class, 'addCapital'])->middleware('permission:Create:CapitalShareTransaction');
    Route::post('capital-shares/{share}/withdraw-capital', [CapitalShareController::class, 'withdrawCapital'])->middleware('permission:Delete:CapitalShareTransaction');
    Route::get('capital-shares/{id}/transactions', [CapitalShareController::class, 'getTransactions'])->middleware('permission:ViewAny:CapitalShareTransaction');
    Route::post('capital-shares/{share}/sell', [CapitalShareController::class, 'sellShare'])->middleware('permission:Update:CapitalShare');

    Route::get('export/saving-report', [ExportController::class, 'exportSavingReport']);
    Route::get('export/capital-report', [ExportController::class, 'exportCapitalReport']);

    Route::get('/dividends-preview', [DividendController::class, 'preview']);
    Route::apiResource('dividends', DividendController::class);
    Route::post('dividends/{dividend}/distribute', [DividendController::class, 'distribute']);
    Route::get('dividends/{dividend}/transactions', [DividendController::class, 'transactions']);

    Route::get('/dividend-schedules', [DividendController::class, 'scheduleIndex']);
    Route::post('/dividend-schedules', [DividendController::class, 'scheduleStore']);
    Route::patch('/dividend-schedules/{schedule}/toggle', [DividendController::class, 'scheduleToggle']);

    Route::get('savers/next-code', [SaverController::class, 'getNextCode']);
    Route::apiResource('savers', SaverController::class);

    Route::get('investors/next-code', [InvestorController::class, 'getNextCode']);
    Route::apiResource('investors', InvestorController::class);

    Route::get('borrowings', [BorrowingController::class, 'getBorrowings']);
    Route::get('lenders', [BorrowingController::class, 'getLenders']);
    Route::post('lenders', [BorrowingController::class, 'storeLender']);
    Route::put('lenders/{id}', [BorrowingController::class, 'updateLender']);
    Route::post('borrowings', [BorrowingController::class, 'storeBorrowing']);
    Route::put('borrowings/{id}', [BorrowingController::class, 'updateBorrowing']);
    Route::post('borrowings/repay', [BorrowingController::class, 'repayBorrowing']);
    Route::get('borrowings/{id}/repayments', [BorrowingController::class, 'getRepayments']);
    Route::get('borrowings/{id}/schedule', [BorrowingController::class, 'getSchedule']);

    Route::middleware('json.unescaped_unicode')->group(function () {
        Route::get('saving-accounts-report', [SavingAccountController::class, 'getSavingReport']);
        Route::get('/dividends-report', [DividendController::class, 'getDividendReport']);

        Route::get('reports/repayment', [RepaymentReportController::class, 'index']);
        Route::get('reports/repayment-schedule', [RepaymentScheduleReportController::class, 'index']);
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
        Route::get('/reports/income-statement', [IncomeStatementController::class, 'index']);
    });


    Route::apiResource('payments', PaymentController::class);

    Route::apiResource('positions', PositionController::class);
    Route::post('employees/upload-photo', [EmployeeController::class, 'uploadPhoto']);
    Route::apiResource('employees', EmployeeController::class);
    Route::apiResource('payrolls', PayrollController::class);

    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->middleware('permission:ui:hr_miscellaneous:view');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->middleware('permission:ui:hr_miscellaneous:create');
    Route::put('/expense-categories/{id}', [ExpenseCategoryController::class, 'update'])->middleware('permission:ui:hr_miscellaneous:edit');
    Route::delete('/expense-categories/{id}', [ExpenseCategoryController::class, 'destroy'])->middleware('permission:ui:hr_miscellaneous:delete');

    Route::get('/miscellaneous-transactions', [MiscellaneousTransactionController::class, 'index'])->middleware('permission:ui:hr_miscellaneous:view');
    Route::post('/miscellaneous-transactions', [MiscellaneousTransactionController::class, 'store'])->middleware('permission:ui:hr_miscellaneous:create');
    Route::delete('/miscellaneous-transactions/{id}', [MiscellaneousTransactionController::class, 'destroy'])->middleware('permission:ui:hr_miscellaneous:delete');

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:ui:general_expenses:view');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:ui:general_expenses:create');
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy'])->middleware('permission:ui:general_expenses:delete');

    Route::get('/revenue-categories', [RevenueCategoryController::class, 'index'])->middleware('permission:ui:general_revenue:view');
    Route::post('/revenue-categories', [RevenueCategoryController::class, 'store'])->middleware('permission:ui:general_revenue:create');
    Route::put('/revenue-categories/{id}', [RevenueCategoryController::class, 'update'])->middleware('permission:ui:general_revenue:edit');
    Route::delete('/revenue-categories/{id}', [RevenueCategoryController::class, 'destroy'])->middleware('permission:ui:general_revenue:delete');

    Route::get('/revenues', [RevenueController::class, 'index'])->middleware('permission:ui:general_revenue:view');
    Route::post('/revenues', [RevenueController::class, 'store'])->middleware('permission:ui:general_revenue:create');
    Route::delete('/revenues/{id}', [RevenueController::class, 'destroy'])->middleware('permission:ui:general_revenue:delete');

    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
});
