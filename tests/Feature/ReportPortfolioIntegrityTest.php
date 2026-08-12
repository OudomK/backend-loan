<?php

namespace Tests\Feature;

use App\Http\Controllers\ActiveLoanReportController;
use App\Http\Controllers\ArrearReportController;
use App\Http\Controllers\DisbursementReportController;
use App\Http\Controllers\InactiveLoanReportController;
use App\Http\Controllers\InterestIncomeReportController;
use App\Http\Controllers\LoanCollectionReportController;
use App\Http\Controllers\LoanOutstandingParReportController;
use App\Http\Controllers\QualityPortfolioController;
use App\Http\Controllers\RepaymentReportController;
use App\Http\Controllers\RepaymentScheduleReportController;
use App\Http\Controllers\WriteOffReportController;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportPortfolioIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loan::unsetEventDispatcher();
        activity()->disableLogging();
        Schema::dropAllTables();
        $this->createTestSchema();

        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('GREATEST', fn (...$values) => max($values));
        $pdo->sqliteCreateFunction('LEAST', fn (...$values) => min($values));
        Carbon::setTestNow('2026-08-09 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_written_off_loan_without_a_repayment_uses_write_off_date_as_inactive_date(): void
    {
        $this->createLoan([
            'loan_code' => 'WO-NO-TX',
            'status' => 'written_off',
            'written_off_at' => '2026-07-07',
            'write_off_balance' => 1000,
        ]);

        $response = app(InactiveLoanReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-08-09',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertSame('WO-NO-TX', $payload['data'][0]['loan_code']);
        $this->assertSame('2026-07-07', $payload['data'][0]['inactive_date']);
    }

    public function test_quality_and_par_exclude_unapproved_loans_and_use_contract_principal(): void
    {
        $officerId = $this->createOfficer();
        $productId = $this->createProduct();
        $borrowerId = $this->createBorrower();
        $activeLoanId = $this->createLoan([
            'loan_code' => 'ACTIVE-PORTFOLIO',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'product_id' => $productId,
            'amount' => 1000,
            'status' => 'active',
        ]);
        $pendingLoanId = $this->createLoan([
            'loan_code' => 'PENDING-PORTFOLIO',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'product_id' => $productId,
            'amount' => 500,
            'status' => 'pending_check',
        ]);

        $this->createSchedule($activeLoanId, 600, 600);
        $this->createSchedule($pendingLoanId, 250, 250);

        $qualityResponse = app(QualityPortfolioController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-08-09',
        ]));
        $quality = json_decode($qualityResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $quality);
        $this->assertSame(1, $quality[0]['no_disb_total']);
        $this->assertEquals(1000, $quality[0]['loan_os']);

        $parResponse = app(LoanOutstandingParReportController::class)->index(Request::create('/', 'GET', [
            'date' => '2026-08-09',
        ]));
        $par = json_decode($parResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $par);
        $this->assertSame(1, $par[0]['active_loan_count']);
        $this->assertEquals(1000, $par[0]['total_loan_os']);
    }

    public function test_interest_income_excludes_pending_and_rejected_loans(): void
    {
        $borrowerId = $this->createBorrower();
        $productId = $this->createProduct();
        $this->createLoan([
            'loan_code' => 'ACTIVE-INCOME',
            'borrower_id' => $borrowerId,
            'product_id' => $productId,
            'status' => 'active',
            'admin_fee' => 10,
        ]);
        $this->createLoan([
            'loan_code' => 'PENDING-INCOME',
            'borrower_id' => $borrowerId,
            'product_id' => $productId,
            'status' => 'pending_approval',
            'admin_fee' => 10,
        ]);
        $this->createLoan([
            'loan_code' => 'REJECTED-INCOME',
            'borrower_id' => $borrowerId,
            'product_id' => $productId,
            'status' => 'rejected',
            'admin_fee' => 10,
        ]);

        $response = app(InterestIncomeReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('ACTIVE-INCOME', $payload['data'][0]['loan_code']);
    }

    public function test_repayment_footer_counts_loan_amount_once_per_loan(): void
    {
        $borrowerId = $this->createBorrower();
        $firstLoanId = $this->createLoan([
            'loan_code' => 'REPAY-FIRST',
            'borrower_id' => $borrowerId,
            'amount' => 1000,
        ]);
        $secondLoanId = $this->createLoan([
            'loan_code' => 'REPAY-SECOND',
            'borrower_id' => $borrowerId,
            'amount' => 2000,
        ]);

        $this->createTransaction($firstLoanId, 100, '2026-07-01');
        $this->createTransaction($firstLoanId, 150, '2026-07-15');
        $this->createTransaction($secondLoanId, 200, '2026-07-20');

        $response = app(RepaymentReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertEquals(3000, $payload['meta']['grand_totals']['USD']['disb_amount']);
        $this->assertEquals(450, $payload['meta']['grand_totals']['USD']['principal_paid']);
        $this->assertEquals(450, $payload['meta']['grand_totals']['USD']['total_paid']);
    }

    public function test_schedule_status_and_dpd_use_report_end_date(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $borrowerId = $this->createBorrower();
        $officerId = $this->createOfficer();
        $loanId = $this->createLoan([
            'loan_code' => 'SCHEDULE-AS-OF',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
        ]);

        DB::table('payments')->insert([
            [
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 900,
                'fee_amount' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-07-30',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'loan_id' => $loanId,
                'payment_number' => 2,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 800,
                'fee_amount' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-09',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = app(RepaymentScheduleReportController::class)->index(Request::create('/', 'GET', [
            'start_date' => '2026-07-01',
            'end_date' => '2026-08-09',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Overdue', $payload['data'][0]['payment_status']);
        $this->assertSame(10, $payload['data'][0]['days_overdue']);
        $this->assertSame('Due', $payload['data'][1]['payment_status']);
        $this->assertSame(0, $payload['data'][1]['days_overdue']);
    }

    public function test_schedule_uses_database_pagination_and_keeps_full_filter_totals(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');
        $borrowerId = $this->createBorrower();
        $officerId = $this->createOfficer();
        $loanId = $this->createLoan([
            'loan_code' => 'SCHEDULE-PAGED',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'amount' => 1000,
            'currency' => 'USD',
        ]);

        foreach ([1 => 900, 2 => 800, 3 => 700] as $number => $outstanding) {
            DB::table('payments')->insert([
                'loan_id' => $loanId,
                'payment_number' => $number,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => $outstanding,
                'fee_amount' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = app(RepaymentScheduleReportController::class)->index(Request::create('/', 'GET', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-09',
            'page' => 2,
            'limit' => 1,
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $payload['meta']['current_page']);
        $this->assertSame(3, $payload['meta']['last_page']);
        $this->assertSame(3, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame(2, $payload['data'][0]['payment_number']);
        $this->assertSame(3, $payload['meta']['grand_totals']['USD']['item_count']);
        $this->assertEquals(300, $payload['meta']['grand_totals']['USD']['principal_amount']);
        $this->assertEquals(2400, $payload['meta']['grand_totals']['USD']['outstanding_balance']);
        $this->assertEquals(330, $payload['meta']['grand_totals']['USD']['remaining']);
    }

    public function test_disbursement_excludes_unapproved_loans_and_uses_contract_os_and_fee_due(): void
    {
        $officerId = $this->createOfficer();
        $borrowerId = $this->createBorrower();
        $activeLoanId = $this->createLoan([
            'loan_code' => 'DISB-ACTIVE',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'amount' => 1000,
            'status' => 'active',
        ]);
        $this->createLoan([
            'loan_code' => 'DISB-PENDING',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'amount' => 500,
            'status' => 'pending_verify',
        ]);

        DB::table('payments')->insert([
            [
                'loan_id' => $activeLoanId,
                'payment_number' => 1,
                'principal_amount' => 600,
                'interest_amount' => 0,
                'outstanding_balance' => 600,
                'fee_amount' => 25,
                'total_due' => 625,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'loan_id' => $activeLoanId,
                'payment_number' => 2,
                'principal_amount' => 600,
                'interest_amount' => 0,
                'outstanding_balance' => 0,
                'fee_amount' => 0,
                'total_due' => 600,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-09-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = app(DisbursementReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-01-01',
            'to_date' => '2026-08-09',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $payload);
        $this->assertSame(1, $payload[0]['no_disb_total']);
        $this->assertEquals(1000, $payload[0]['loan_os']);
        $this->assertEquals(25, $payload[0]['fee_due']);
    }

    public function test_loan_collection_uses_customer_code_and_includes_fee_in_total(): void
    {
        $borrowerId = $this->createBorrower();
        $loanId = $this->createLoan([
            'loan_code' => 'COLLECTION-FEE',
            'borrower_id' => $borrowerId,
        ]);
        $this->createTransaction($loanId, 80, '2026-07-15', [
            'amount_paid' => 100,
            'interest_paid' => 20,
            'penalty_paid' => 5,
            'fee_paid' => 10,
        ]);

        $response = app(LoanCollectionReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
            'paginate' => 'false',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('CID-001', $payload['data'][0]['cid']);
        $this->assertEquals(10, $payload['data'][0]['fee']);
        $this->assertEquals(115, $payload['data'][0]['total']);
        $this->assertEquals(115, $payload['meta']['grand_totals']['USD']['total']);
    }

    public function test_arrear_all_keeps_due_today_at_zero_aging_and_reports_remaining_monthly_fee(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $borrowerId = $this->createBorrower();
        $loanId = $this->createLoan([
            'loan_code' => 'ARREAR-DUE-TODAY',
            'borrower_id' => $borrowerId,
            'admin_fee_type' => 'monthly',
        ]);

        DB::table('payments')->insert([
            'loan_id' => $loanId,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 20,
            'outstanding_balance' => 0,
            'fee_amount' => 10,
            'fee_paid' => 4,
            'total_due' => 130,
            'penalty_amount' => 0,
            'total_paid' => 124,
            'payment_date' => '2026-08-09',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
            'report_type' => 'all',
            'to_date' => '2026-08-09',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertSame('ARREAR-DUE-TODAY', $payload['data'][0]['loan_no']);
        $this->assertSame(0, $payload['data'][0]['aging']);
        $this->assertEquals(0, $payload['data'][0]['arrear_amount']);
        $this->assertEquals(0, $payload['data'][0]['arrear_interest']);
        $this->assertEquals(6, $payload['data'][0]['arrear_fee']);
        $this->assertEquals(6, $payload['meta']['grand_totals']['USD']['arrear_fee']);
    }

    public function test_arrear_reports_show_zero_aging_loans_first(): void
    {
        $borrowerId = $this->createBorrower();
        $schedule = [
            ['ARREAR-AGING-0', '2026-08-09', '2026-08-09'],
            ['ARREAR-AGING-2', '2026-08-07', '2026-08-07'],
            ['ARREAR-AGING-10', '2026-07-30', '2026-07-30'],
        ];

        foreach ($schedule as [$loanCode, $paymentDate, $startDate]) {
            $loanId = $this->createLoan([
                'loan_code' => $loanCode,
                'borrower_id' => $borrowerId,
                'start_date' => $startDate,
                'late_since_date' => $paymentDate,
                'penalty_rate' => 0,
            ]);

            DB::table('payments')->insert([
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 100,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $paymentDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['all', 'under30'] as $reportType) {
            $rows = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
                'report_type' => $reportType,
                'from_date' => '2026-07-01',
                'to_date' => '2026-08-09',
                'paginate' => 'false',
            ]));

            $this->assertSame([0, 2, 10], array_column($rows, 'aging'), $reportType);
            $this->assertSame(
                ['ARREAR-AGING-0', 'ARREAR-AGING-2', 'ARREAR-AGING-10'],
                array_column($rows, 'loan_no'),
                $reportType
            );
        }
    }

    public function test_arrear_reports_search_all_pages_by_code_name_location_and_amount(): void
    {
        $firstBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $firstBorrowerId)->update([
            'first_name' => 'Dara',
            'last_name' => 'Arrear',
            'village' => 'Village Alpha',
            'commune' => 'Commune Alpha',
        ]);
        $firstLoanId = $this->createLoan([
            'loan_code' => 'ARREAR-SEARCH-ALPHA',
            'borrower_id' => $firstBorrowerId,
            'amount' => 2500,
        ]);

        $secondBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $secondBorrowerId)->update([
            'customer_code' => 'CID-002',
            'first_name' => 'Sokha',
            'last_name' => 'Other',
            'village' => 'Village Beta',
            'commune' => 'Commune Beta',
        ]);
        $secondLoanId = $this->createLoan([
            'loan_code' => 'ARREAR-SEARCH-BETA',
            'borrower_id' => $secondBorrowerId,
            'amount' => 999,
        ]);

        $thirdBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $thirdBorrowerId)->update([
            'customer_code' => 'CID-003',
            'first_name' => 'Maly',
            'last_name' => 'Penalty',
        ]);
        $this->createLoan([
            'loan_code' => 'ARREAR-STATUS-OK',
            'borrower_id' => $thirdBorrowerId,
            'amount' => 777,
            'late_since_date' => '2026-08-04',
            'accumulated_penalty' => 10,
            'penalty_rate' => 0,
        ]);

        foreach ([$firstLoanId, $secondLoanId] as $loanId) {
            DB::table('payments')->insert([
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 100,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('payments')->where('loan_id', $secondLoanId)->update(['total_paid' => 10]);
        $this->createTransaction($secondLoanId, 0, '2026-08-03');

        foreach (['all', 'under30'] as $reportType) {
            foreach (['arrear-search-alpha', 'dara arrear', 'village alpha', 'commune alpha', '2,500.00'] as $search) {
                $response = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
                    'report_type' => $reportType,
                    'from_date' => '2026-08-01',
                    'to_date' => '2026-08-09',
                    'search' => $search,
                    'page' => 1,
                    'limit' => 1,
                ]));
                $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

                $message = "{$reportType} search failed for: {$search}";
                $this->assertSame(1, $payload['meta']['total'], $message);
                $this->assertSame('ARREAR-SEARCH-ALPHA', $payload['data'][0]['loan_no'], $message);
            }

            foreach ([
                'Active' => 'ARREAR-SEARCH-ALPHA',
                'Partial' => 'ARREAR-SEARCH-BETA',
            ] as $status => $loanCode) {
                $response = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
                    'report_type' => $reportType,
                    'from_date' => '2026-08-01',
                    'to_date' => '2026-08-09',
                    'status' => $status,
                ]));
                $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

                $message = "{$reportType} status filter failed for: {$status}";
                $this->assertSame(1, $payload['meta']['total'], $message);
                $this->assertSame($loanCode, $payload['data'][0]['loan_no'], $message);
                $this->assertSame($status, $payload['data'][0]['status'], $message);
            }

            $okResponse = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
                'report_type' => $reportType,
                'from_date' => '2026-08-01',
                'to_date' => '2026-08-09',
                'status' => 'OK',
            ]));
            $okPayload = json_decode($okResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(0, $okPayload['meta']['total'], "{$reportType} must not retain penalty-only rows");
        }
    }

    public function test_arrear_reports_combine_installments_by_loan_and_remove_settled_amounts(): void
    {
        $borrowerId = $this->createBorrower();
        $loanId = $this->createLoan([
            'loan_code' => 'ARREAR-INSTALLMENTS',
            'borrower_id' => $borrowerId,
            'amount' => 1000,
            'accumulated_penalty' => 75,
            'penalty_rate' => 0,
        ]);

        DB::table('payments')->insert([
            [
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 900,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'loan_id' => $loanId,
                'payment_number' => 2,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 800,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-08-05',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach (['all', 'under30'] as $reportType) {
            $rows = $this->arrearRows($reportType);
            $this->assertCount(1, $rows, $reportType);
            $this->assertArrayNotHasKey('installment_no', $rows[0], $reportType);
            $this->assertSame('2026-08-01', $rows[0]['arrear_date'], $reportType);
            $this->assertSame(8, $rows[0]['aging'], $reportType);
            $this->assertEquals(220, $rows[0]['arrear_amount'], $reportType);
            $this->assertEquals(20, $rows[0]['arrear_interest'], $reportType);
            $this->assertEquals(75, $rows[0]['penalty_due'], $reportType);
        }

        DB::table('payments')
            ->where('loan_id', $loanId)
            ->where('payment_number', 1)
            ->update(['total_paid' => 110]);

        foreach (['all', 'under30'] as $reportType) {
            $rows = $this->arrearRows($reportType);
            $this->assertCount(1, $rows, $reportType);
            $this->assertArrayNotHasKey('installment_no', $rows[0], $reportType);
            $this->assertEquals(110, $rows[0]['arrear_amount'], $reportType);
            $this->assertEquals(10, $rows[0]['arrear_interest'], $reportType);
            $this->assertEquals(75, $rows[0]['penalty_due'], $reportType);
        }

        DB::table('payments')
            ->where('loan_id', $loanId)
            ->where('payment_number', 2)
            ->update(['total_paid' => 110]);

        foreach (['all', 'under30'] as $reportType) {
            $this->assertSame([], $this->arrearRows($reportType), $reportType);
        }
    }

    public function test_arrear_amount_combines_remaining_principal_and_interest_after_partial_payments(): void
    {
        $borrowerId = $this->createBorrower();
        $loanId = $this->createLoan([
            'loan_code' => 'ARREAR-PARTIAL-TOTAL',
            'borrower_id' => $borrowerId,
            'amount' => 1000,
            'admin_fee_type' => 'monthly',
            'penalty_rate' => 0,
        ]);

        DB::table('payments')->insert([
            [
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 20,
                'outstanding_balance' => 900,
                'fee_amount' => 10,
                'fee_paid' => 10,
                // 10 fee + 20 interest + 50 principal have been paid.
                'total_due' => 130,
                'penalty_amount' => 0,
                'total_paid' => 80,
                'payment_date' => '2026-08-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'loan_id' => $loanId,
                'payment_number' => 2,
                'principal_amount' => 200,
                'interest_amount' => 30,
                'outstanding_balance' => 700,
                'fee_amount' => 10,
                'fee_paid' => 4,
                // 4 fee + 10 interest have been paid; principal is untouched.
                'total_due' => 240,
                'penalty_amount' => 0,
                'total_paid' => 14,
                'payment_date' => '2026-08-05',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach (['all', 'under30'] as $reportType) {
            $rows = $this->arrearRows($reportType);

            $this->assertCount(1, $rows, $reportType);
            $this->assertSame('Partial', $rows[0]['status'], $reportType);
            $this->assertEquals(270, $rows[0]['arrear_amount'], $reportType);
            $this->assertEquals(20, $rows[0]['arrear_interest'], $reportType);
            $this->assertEquals(6, $rows[0]['arrear_fee'], $reportType);
        }
    }

    public function test_arrear_period_applies_from_date_but_keeps_due_today(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');
        $borrowerId = $this->createBorrower();
        $oldLoanId = $this->createLoan([
            'loan_code' => 'ARREAR-BEFORE-FROM',
            'borrower_id' => $borrowerId,
        ]);
        $dueTodayLoanId = $this->createLoan([
            'loan_code' => 'ARREAR-PERIOD-DUE-TODAY',
            'borrower_id' => $borrowerId,
        ]);

        foreach ([
            [$oldLoanId, '2026-08-01'],
            [$dueTodayLoanId, '2026-08-09'],
        ] as [$loanId, $paymentDate]) {
            DB::table('payments')->insert([
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'outstanding_balance' => 100,
                'fee_amount' => 0,
                'fee_paid' => 0,
                'total_due' => 110,
                'penalty_amount' => 0,
                'total_paid' => 0,
                'payment_date' => $paymentDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $payload = app(ArrearReportController::class)->index(Request::create('/', 'GET', [
            'report_type' => 'under30',
            'from_date' => '2026-08-05',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));

        $this->assertCount(1, $payload);
        $this->assertSame('ARREAR-PERIOD-DUE-TODAY', $payload[0]['loan_no']);
        $this->assertSame(0, $payload[0]['aging']);
    }

    public function test_active_loan_applies_from_date_and_includes_monthly_fee_in_overdue(): void
    {
        $borrowerId = $this->createBorrower();
        $this->createLoan([
            'loan_code' => 'ACTIVE-BEFORE-FROM',
            'borrower_id' => $borrowerId,
            'start_date' => '2026-07-31',
        ]);
        $loanId = $this->createLoan([
            'loan_code' => 'ACTIVE-IN-PERIOD',
            'borrower_id' => $borrowerId,
            'start_date' => '2026-08-01',
            'admin_fee' => 2,
            'admin_fee_type' => 'monthly',
        ]);
        $this->createLoan([
            'loan_code' => 'ACTIVE-UPFRONT-FEE',
            'borrower_id' => $borrowerId,
            'start_date' => '2026-08-02',
            'admin_fee' => 2,
            'admin_fee_type' => 'one_time',
        ]);

        DB::table('payments')->insert([
            'loan_id' => $loanId,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 20,
            'outstanding_balance' => 1000,
            'fee_amount' => 10,
            'fee_paid' => 10,
            'total_due' => 130,
            'penalty_amount' => 0,
            'total_paid' => 10,
            'payment_date' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createTransaction($loanId, 0, '2026-08-01', [
            'amount_paid' => 0,
            'principal_paid' => 0,
            'fee_paid' => 10,
        ]);

        $response = app(ActiveLoanReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $rows = collect($payload)->keyBy('loan_code');

        $this->assertCount(2, $payload);
        $this->assertFalse($rows->has('ACTIVE-BEFORE-FROM'));
        $this->assertEquals(120, $rows['ACTIVE-IN-PERIOD']['overdue_amount']);
        $this->assertEquals(0, $rows['ACTIVE-IN-PERIOD']['processing_fee']);
        $this->assertEquals(20, $rows['ACTIVE-UPFRONT-FEE']['processing_fee']);
    }

    public function test_active_loan_searches_all_pages_by_code_name_location_and_amount(): void
    {
        $firstBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $firstBorrowerId)->update([
            'first_name' => 'Dara',
            'last_name' => 'Search',
            'village' => 'Village Alpha',
            'commune' => 'Commune Alpha',
            'district' => 'District Alpha',
            'province' => 'Province Alpha',
        ]);
        $this->createLoan([
            'loan_code' => 'SEARCH-ALPHA',
            'borrower_id' => $firstBorrowerId,
            'amount' => 2500,
        ]);

        $secondBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $secondBorrowerId)->update([
            'customer_code' => 'CID-002',
            'first_name' => 'Sokha',
            'last_name' => 'Other',
            'village' => 'Village Beta',
            'commune' => 'Commune Beta',
            'district' => 'District Beta',
            'province' => 'Province Beta',
        ]);
        $this->createLoan([
            'loan_code' => 'SEARCH-BETA',
            'borrower_id' => $secondBorrowerId,
            'amount' => 999,
        ]);

        foreach (['search-alpha', 'dara search', 'village alpha', 'commune alpha', 'district alpha', 'province alpha', '2,500.00'] as $search) {
            $response = app(ActiveLoanReportController::class)->index(Request::create('/', 'GET', [
                'to_date' => '2026-08-09',
                'search' => $search,
                'page' => 1,
                'limit' => 1,
            ]));
            $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(1, $payload['meta']['total'], "Search failed for: {$search}");
            $this->assertSame('SEARCH-ALPHA', $payload['data'][0]['loan_code'], "Search failed for: {$search}");
        }
    }

    public function test_inactive_loan_searches_all_pages_by_code_name_location_and_amount(): void
    {
        $firstBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $firstBorrowerId)->update([
            'first_name' => 'Dara',
            'last_name' => 'Inactive',
            'village' => 'Village Alpha',
            'commune' => 'Commune Alpha',
            'district' => 'District Alpha',
            'province' => 'Province Alpha',
        ]);
        $this->createLoan([
            'loan_code' => 'INACTIVE-SEARCH-ALPHA',
            'borrower_id' => $firstBorrowerId,
            'amount' => 2500,
            'status' => 'written_off',
            'written_off_at' => '2026-08-05',
            'write_off_balance' => 2500,
        ]);

        $secondBorrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $secondBorrowerId)->update([
            'customer_code' => 'CID-002',
            'first_name' => 'Sokha',
            'last_name' => 'Other',
            'village' => 'Village Beta',
            'commune' => 'Commune Beta',
            'district' => 'District Beta',
            'province' => 'Province Beta',
        ]);
        $this->createLoan([
            'loan_code' => 'INACTIVE-SEARCH-BETA',
            'borrower_id' => $secondBorrowerId,
            'amount' => 999,
            'status' => 'written_off',
            'written_off_at' => '2026-08-05',
            'write_off_balance' => 999,
        ]);

        foreach (['inactive-search-alpha', 'dara inactive', 'village alpha', 'commune alpha', 'district alpha', 'province alpha', '2,500.00'] as $search) {
            $response = app(InactiveLoanReportController::class)->index(Request::create('/', 'GET', [
                'from_date' => '2026-08-01',
                'to_date' => '2026-08-09',
                'search' => $search,
                'page' => 1,
                'limit' => 1,
            ]));
            $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(1, $payload['meta']['total'], "Search failed for: {$search}");
            $this->assertSame('INACTIVE-SEARCH-ALPHA', $payload['data'][0]['loan_code'], "Search failed for: {$search}");
        }
    }

    public function test_write_off_report_keeps_soft_deleted_borrower_details(): void
    {
        $borrowerId = $this->createBorrower();
        DB::table('borrowers')->where('id', $borrowerId)->update([
            'deleted_at' => '2026-08-02 10:00:00',
        ]);
        $this->createLoan([
            'loan_code' => 'WRITE-OFF-DELETED-BORROWER',
            'borrower_id' => $borrowerId,
            'status' => 'written_off',
            'written_off_at' => '2026-08-05',
            'write_off_balance' => 500,
        ]);

        $response = app(WriteOffReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('CID-001', $payload['data'][0]['customer_code']);
        $this->assertSame('Borrower Test', $payload['data'][0]['customer_name']);
    }

    public function test_inactive_loan_excludes_monthly_fee_before_allocating_interest_and_principal(): void
    {
        $borrowerId = $this->createBorrower();
        $loanId = $this->createLoan([
            'loan_code' => 'INACTIVE-MONTHLY-FEE',
            'borrower_id' => $borrowerId,
            'status' => 'written_off',
            'written_off_at' => '2026-08-05',
            'write_off_balance' => 1000,
            'admin_fee_type' => 'monthly',
        ]);

        DB::table('payments')->insert([
            'loan_id' => $loanId,
            'payment_number' => 1,
            'principal_amount' => 100,
            'interest_amount' => 20,
            'outstanding_balance' => 1000,
            'fee_amount' => 10,
            'fee_paid' => 10,
            'total_due' => 130,
            'penalty_amount' => 0,
            'total_paid' => 30,
            'payment_date' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(InactiveLoanReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $payload);
        $this->assertEquals(0, $payload[0]['principal_paid']);
        $this->assertEquals(20, $payload[0]['interest_paid']);
    }

    public function test_historical_reports_keep_soft_deleted_officer_product_and_collector_names(): void
    {
        $borrowerId = $this->createBorrower();
        $officerId = $this->createOfficer();
        $productId = $this->createProduct();
        $activeLoanId = $this->createLoan([
            'loan_code' => 'HISTORY-ACTIVE',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'disbursed_by_officer_id' => $officerId,
            'product_id' => $productId,
            'start_date' => '2026-08-01',
        ]);
        $inactiveLoanId = $this->createLoan([
            'loan_code' => 'HISTORY-INACTIVE',
            'borrower_id' => $borrowerId,
            'loan_officer_id' => $officerId,
            'disbursed_by_officer_id' => $officerId,
            'product_id' => $productId,
            'start_date' => '2026-07-01',
            'status' => 'completed',
        ]);
        $this->createTransaction($activeLoanId, 100, '2026-08-03', [
            'collector_id' => $officerId,
        ]);
        $this->createTransaction($inactiveLoanId, 1000, '2026-08-04', [
            'collector_id' => $officerId,
        ]);

        DB::table('loan_officers')->where('id', $officerId)->update([
            'deleted_at' => '2026-08-05 10:00:00',
        ]);
        DB::table('loan_products')->where('id', $productId)->update([
            'deleted_at' => '2026-08-05 10:00:00',
        ]);

        $activeResponse = app(ActiveLoanReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $activeRows = collect(json_decode($activeResponse->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $activeRow = $activeRows->firstWhere('loan_code', 'HISTORY-ACTIVE');

        $inactiveResponse = app(InactiveLoanReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $inactiveRows = collect(json_decode($inactiveResponse->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $inactiveRow = $inactiveRows->firstWhere('loan_code', 'HISTORY-INACTIVE');

        $repaymentResponse = app(RepaymentReportController::class)->index(Request::create('/', 'GET', [
            'from_date' => '2026-08-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
        $repaymentPayload = json_decode($repaymentResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $repaymentRow = collect($repaymentPayload['data'])->firstWhere('loan_no', 'HISTORY-ACTIVE');

        $this->assertSame('Test Officer', $activeRow['officer_name']);
        $this->assertSame('Test Product', $activeRow['product_name']);
        $this->assertSame('Test Officer', $inactiveRow['co_repay']);
        $this->assertSame('Test Product', $inactiveRow['product_name']);
        $this->assertSame('Test Officer', $repaymentRow['co_repay']);
        $this->assertSame('Test Product', $repaymentRow['product_name']);
    }

    private function createLoan(array $overrides = []): int
    {
        return (int) DB::table('loans')->insertGetId(array_merge([
            'loan_code' => 'LN-'.uniqid(),
            'amount' => 1000,
            'disbursed_amount' => 1000,
            'total_paid' => 0,
            'currency' => 'USD',
            'interest_rate' => 12,
            'duration_months' => 12,
            'monthly_payment' => 100,
            'monthly_interest' => 10,
            'payment_frequency' => 'monthly',
            'repayment_method' => 'fixed_monthly',
            'loan_cycle' => 1,
            'admin_fee' => 0,
            'admin_fee_type' => 'one_time',
            'refinance_fee' => 0,
            'refinanced_amount' => 0,
            'reschedule_fee' => 0,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'aging' => 0,
            'locked_aging' => 0,
            'write_off_balance' => 0,
            'recovery_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @return array<int, array<string, mixed>> */
    private function arrearRows(string $reportType): array
    {
        return app(ArrearReportController::class)->index(Request::create('/', 'GET', [
            'report_type' => $reportType,
            'from_date' => '2026-07-01',
            'to_date' => '2026-08-09',
            'paginate' => 'false',
        ]));
    }

    private function createOfficer(): int
    {
        return (int) DB::table('loan_officers')->insertGetId([
            'name' => 'Test Officer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(): int
    {
        return (int) DB::table('loan_products')->insertGetId([
            'name' => 'Test Product',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBorrower(): int
    {
        return (int) DB::table('borrowers')->insertGetId([
            'customer_code' => 'CID-001',
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'customer_type' => 'Borrower',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchedule(int $loanId, float $principal, float $outstanding): void
    {
        DB::table('payments')->insert([
            [
                'loan_id' => $loanId,
                'payment_number' => 1,
                'principal_amount' => $principal,
                'interest_amount' => 0,
                'outstanding_balance' => $outstanding,
                'fee_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-09-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'loan_id' => $loanId,
                'payment_number' => 2,
                'principal_amount' => $principal,
                'interest_amount' => 0,
                'outstanding_balance' => 0,
                'fee_amount' => 0,
                'total_paid' => 0,
                'payment_date' => '2026-10-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createTransaction(
        int $loanId,
        float $principal,
        string $date,
        array $overrides = []
    ): void {
        DB::table('repayment_transactions')->insert(array_merge([
            'loan_id' => $loanId,
            'amount_paid' => $principal,
            'principal_paid' => $principal,
            'interest_paid' => 0,
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'prepayment_paid' => 0,
            'paid_off_amount' => 0,
            'recovery_amount' => 0,
            'withdrawn_prepayment' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Normal',
            'transaction_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createTestSchema(): void
    {
        Schema::create('loan_officers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loan_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id();
            $table->string('customer_code')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('customer_type')->default('Borrower');
            $table->string('phone')->nullable();
            $table->string('village')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['co_borrowers', 'guarantors'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('borrower_id')->nullable();
            $table->unsignedBigInteger('co_borrower_id')->nullable();
            $table->unsignedBigInteger('guarantor_id')->nullable();
            $table->unsignedBigInteger('loan_officer_id')->nullable();
            $table->unsignedBigInteger('disbursed_by_officer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('loan_code');
            $table->decimal('amount', 15, 2);
            $table->decimal('disbursed_amount', 15, 2)->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->string('currency');
            $table->decimal('interest_rate', 8, 2)->default(0);
            $table->integer('duration_months')->default(12);
            $table->decimal('monthly_payment', 15, 2)->default(0);
            $table->decimal('monthly_interest', 15, 2)->default(0);
            $table->string('payment_frequency')->nullable();
            $table->string('repayment_method')->nullable();
            $table->integer('loan_cycle')->default(1);
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->string('admin_fee_type')->default('one_time');
            $table->decimal('refinance_fee', 15, 2)->default(0);
            $table->decimal('refinanced_amount', 15, 2)->default(0);
            $table->decimal('reschedule_fee', 15, 2)->default(0);
            $table->string('sector')->nullable();
            $table->date('start_date');
            $table->date('maturity_date')->nullable();
            $table->string('status');
            $table->integer('aging')->default(0);
            $table->integer('locked_aging')->default(0);
            $table->date('late_since_date')->nullable();
            $table->date('penalty_late_since_date')->nullable();
            $table->decimal('accumulated_penalty', 15, 2)->default(0);
            $table->decimal('penalty_rate', 15, 2)->nullable();
            $table->date('written_off_at')->nullable();
            $table->decimal('write_off_balance', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->date('payment_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('collector_id')->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('waived_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('prepayment_paid', 15, 2)->default(0);
            $table->decimal('paid_off_amount', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->decimal('withdrawn_prepayment', 15, 2)->default(0);
            $table->string('payment_method')->default('Cash');
            $table->string('repayment_type')->default('Normal');
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collaterals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
}
