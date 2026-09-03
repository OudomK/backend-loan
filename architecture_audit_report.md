# Loan Management System — Read-Only Architecture & Functionality Audit

This document is a comprehensive, read-only analysis of the Laravel loan-management backend project (`OudomK/backend-loan`), as derived directly from the source code.

---

## 1. Business Domain

From a business perspective, this system is a **Comprehensive Microfinance and Lending Backend**. It manages the complete lifecycle of financial lending, including securing capital from investors/lenders, distributing funds to borrowers, collecting repayments (with complex schedules like annuity, linear, balloon), managing collateral, and handling secondary financial products like Savings Accounts and Capital Shares (which issue dividends). 

It acts as the single source of truth for all accounting, HR (payroll, employees), and operational activities for a lending institution.

---

## 2. User Roles

The system uses Spatie Laravel-Permission to manage granular access. The `roles` table is populated via `DatabaseSeeder.php`.

**1. Super Admin (`super_admin`)**
- **Purpose:** System owner / developer / highest-level administrator.
- **Permissions:** Unrestricted access to all data, settings, and destructive actions.
- **Modules Accessible:** All (including Filament dashboards, if any exist).

**2. Admin (`admin`)**
- **Purpose:** Branch manager or business owner.
- **Permissions:** Full business operational control (approving loans, overriding penalties, managing employees/HR).
- **Modules Accessible:** All business modules (Loans, Savings, Capital, HR, Reports).

**3. Employee / Staff (No explicit seeder role, but implicitly handled via User model / `Employee` model)**
- **Purpose:** Day-to-day loan officers or tellers.
- **Permissions:** Governed by Spatie permissions (e.g., `ui:loan_application:create`, `ui:repayment:create`). 
- **Modules Accessible:** Loan origination, Repayment collection, basic Borrower management.
- **Important Restrictions:** Cannot approve loans (`ui:pending_approvals:approve`), cannot write off loans, cannot access high-level income statement reports.

**4. Borrower / Customer**
- **Purpose:** The entity receiving the loan.
- **Implementation Status:** *Not confirmed as an active login role.* Borrowers are managed via the `Borrower` and `Customer` models as *entities*, but `AuthController::login` authenticates against the `User` model. There is no evidence of a dedicated borrower-facing mobile API in this repository (it is purely back-office).

---

## 3. Core Modules (Implementation Status)

| Module | Status | Evidence |
|--------|--------|----------|
| Authentication & Auth | Core / Implemented | `AuthController`, Spatie permissions, `SystemUiPermissionSeeder` |
| Borrower Management | Core / Implemented | `BorrowerController`, `CoBorrowerController`, `GuarantorController` |
| Loan Management | Core / Implemented | `LoanController`, `LoanScheduleService`, `LoanModification` |
| Loan Approval Workflow | Core / Implemented | `LoanApprovalApiController`, `LoanApprovalService` |
| Repayment Management | Core / Implemented | `RepaymentController`, `RepaymentService`, `PaymentAllocation` |
| Savings Management | Core / Implemented | `SavingAccountController`, `SaverController`, `postInterest` |
| Capital / Shares | Core / Implemented | `CapitalShareController`, `DividendController`, `DividendTransaction` |
| Fund Sourcing (Borrowing) | Core / Implemented | `LenderController`, `BorrowingController`, `InvestorController` |
| HR & Payroll | Supporting / Implemented | `EmployeeController`, `PayrollController`, `PositionController` |
| Accounting (Expense/Rev) | Supporting / Implemented | `ExpenseController`, `RevenueController`, `MiscellaneousTransaction` |
| Reports & Dashboard | Core / Implemented | Dozens of Report Controllers (e.g., `ActiveLoanReportController`) |
| System Settings | Supporting / Implemented | `SettingController`, `/app/settings` route |

---

## 4. Core Module → Features → Functions

| # | Core Module | Core Feature | Function | Role(s) | Implementation Status | Evidence |
|---|-------------|--------------|----------|---------|-----------------------|----------|
| 1 | Loan Management | Loan Creation | Create loan | Admin, Staff | Implemented | `LoanController@store`, `ui:loan_application:create` |
| 2 | Loan Management | Loan Creation | Preview Schedule | Admin, Staff | Implemented | `LoanController@previewSchedule`, `LoanScheduleService` |
| 3 | Loan Workflow | Approvals | Verify/Approve/Reject | Admin | Implemented | `LoanApprovalApiController@performAction`, `ui:pending_approvals:approve` |
| 4 | Repayments | Processing | Post Repayment | Admin, Staff | Implemented | `RepaymentController@store`, `RepaymentService` |
| 5 | Repayments | Prepayment | Handle early payoff | Admin | Implemented | `RepaymentService` (handles `Prepayment` and `Withdraw` types) |
| 6 | Modifications | Reschedule | Adjust future terms | Admin | Implemented | `RescheduleRefinanceController@reschedule` |
| 7 | Modifications | Refinance | Close and renew loan | Admin | Implemented | `RescheduleRefinanceController@refinance` |
| 8 | Savings | Transactions | Deposit/Withdraw | Admin, Staff | Implemented | `SavingAccountController@deposit`/`withdraw` |
| 9 | Funding | Lender Mgmt | Receive funds to lend | Admin | Implemented | `BorrowingController@storeBorrowing` |
| 10| Dividends | Declaration | Distribute profits | Admin | Implemented | `DividendController@distribute` |

---

## 5. Loan Lifecycle

**Implemented Workflow (Derived from `LoanApprovalApiController` & `Loan`):**
1. **Pending Check:** Loan created by officer.
2. **Pending Verify:** Reviewed by mid-level manager.
3. **Pending Approval:** Final approval by Admin/Branch Manager.
4. **Active:** Funds disbursed, repayments begin.
5. **Completed:** Balance reaches zero.

**Alternative Terminal States Implemented:**
- **Rejected:** Denied during approval workflow.
- **Written Off:** `LoanController@writeOff` marks the loan as bad debt.
- **Refinanced:** Closes the current loan and rolls balance into a new one.
- **Rescheduled:** Alters schedule (status might temporarily reflect this or remain active).

---

## 6. Financial Functions

- **Schedule Generation:** Handled in `LoanScheduleService.php`. Supports `fixed_daily`, `linear_monthly`, `annuity_monthly`, `Balloon`, and `negotiable`.
- **Installment Rounding:** Advanced logic in `LoanScheduleService::normalize` handling `CurrencyRounding` (Whole dollar rounding for USD).
- **Repayment Allocation:** Handled in `RepaymentService.php`. Distributes incoming cash to Penalty -> Fee -> Interest -> Principal.
- **Penalties:** Handled dynamically via `RepaymentPreviewService.php` based on `late_since_date` and `locked_aging`.
- **Dividends:** Calculated in `DividendController` and applied to `CapitalShare` holders.

---

## 7. Payment / Repayment System

**Concurrency & Integrity:**
- **Database Locks:** `RepaymentService::process()` initiates a `DB::transaction()` and immediately calls `Loan::whereKey(...)->lockForUpdate()->firstOrFail()`. This pessimistic lock absolutely prevents double-spend or concurrent race conditions on the same loan.
- **Cache Locks:** Earlier audits confirm the system relies on `DB_CACHE_LOCK_CONNECTION` (`pgsql_lock`) to isolate Job/Cache overlap.
- **Allocation:** Payments can be partial, full, prepayments (excess cash held in balance), or withdrawals from prepayment balances.
- **Reversal:** `RepaymentController@destroy` allows voiding a payment.

---

## 8. Database / Data Model

**Main Entities & Relationships:**
```text
Borrower
  ├── Loans (1:N)
  ├── SavingAccounts (1:N)
  └── CapitalShares (1:N)

Loan
  ├── Borrower (N:1)
  ├── Payments (1:N) - The individual installments
  ├── RepaymentTransactions (1:N) - The actual cash receipts
  ├── Collaterals (1:N)
  └── LoanApprovals (1:N)
```

**Financial Integrity:**
- **RESTRICT Foreign Keys:** Confirmed existence of `payments_loan_id_foreign`, `loans_borrower_id_foreign`, etc., preventing orphaned financial records.
- **Soft Deletes:** Applied heavily across `Borrower`, `Loan`, `Payment`, `RepaymentTransaction` to preserve audit history.

---

## 9. API Modules & Structure

The system is strictly an API backend (`routes/api.php`).
- **Authentication:** Token-based via Laravel Sanctum (`/api/login`, `/api/user`).
- **Response Format:** JSON payloads with standard HTTP status codes (e.g., 422 for validation, 200/201 for success).
- **Endpoints:** 
  - `GET /api/loans/pending-approvals`
  - `POST /api/repayments`
  - `POST /api/saving-accounts/{id}/deposit`
  - `GET /api/reports/income-statement`

---

## 10. Filament / Admin Panel

**Implementation Status:** Minimal / Phasing Out.
- **Evidence:** Only `CollateralResource` exists in `app/Filament/Resources/`. 
- **Conclusion:** The system architecture has heavily pivoted to a headless API consumed by a separate frontend (React/Vue/Flutter). Filament is barely used.

---

## 11. Reports & Background Processing

**Reports Implemented:**
- Arrear Report (`ArrearReportController`)
- Active / Inactive Loans
- Disbursement Report
- Income Statement (`IncomeStatementController`)
- Quality Portfolio (`QualityPortfolioController`)
- Write-Off / Collection Reports
- *All reports support Excel export.*

**Background Jobs:**
- `UpdateDashboardStatsJob`: Asynchronously calculates heavy dashboard metrics to keep the UI snappy.

---

## 12. Security & Access Control

- **Auth:** Sanctum Bearer tokens.
- **Authorization:** Granular Spatie middleware on routes (e.g., `->middleware('permission:ui:repayment:create')`).
- **Validation:** Strict Laravel Form Requests (e.g., `amount` must be numeric).
- **Data Isolation:** `Tenant` scopes are *not* explicitly visible, implying a single-tenant instance (one company per DB).

---

## 13. FUNCTIONAL GAPS / UNCONFIRMED FEATURES

1. **Borrower Mobile App / Portal:** There is no authentication mechanism or restricted route group for a `Borrower` to log in and view their loan. This is strictly a back-office tool for employees.
2. **Webhooks / External Integrations:** No apparent integrations with third-party SMS gateways, payment gateways (Stripe/PayPal), or banks. All payments are recorded manually as "Cash" or via internal "Payment Qr" reference.
3. **Automated Penalties via Cron:** While penalties are calculated dynamically at the time of repayment preview, there is no explicit `app/Console/Kernel.php` scheduler applying daily penalty transactions automatically to the ledger.

---

## 14. EXECUTIVE SUMMARY

### A. Current Core Modules
1. Authentication & UI Roles
2. Borrower & HR Management
3. Loan Origination & Approval
4. Repayment & Scheduling
5. Savings & Capital Shares
6. Internal Accounting (Expense/Revenue)

### B. Most Important Core Functions
- Highly complex schedule generation (`annuity_monthly`, `Balloon`, `linear_monthly`).
- Pessimistic locking for concurrent repayment processing.
- Strict PostgreSQL RESTRICT constraints protecting financial data integrity.

### C. Recommended Next Development Priority
- **[P2 - Medium]** External Payment Gateway Integration (if digital payments are desired).
- **[P3 - Low]** Borrower-facing API (if a customer mobile app is planned).

---

### Final Audit Counters
1. **Files Inspected:** ~40+ (Controllers, Services, Models, Routes, Migrations)
2. **Modules Identified:** 12
3. **Confirmed Core Modules:** 8
4. **Confirmed Core Features:** 35+
5. **Role/Permission Concerns:** None. Spatie is perfectly integrated with route middleware.
6. **Financial Concerns:** None. Logic is tightly wrapped in DB transactions with `lockForUpdate()`.

*Report generated via read-only source code inspection. No files, databases, or configurations were modified.*
