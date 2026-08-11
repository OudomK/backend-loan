<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WriteOffCollectionReportController;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\RepaymentTransaction;
use App\Services\RepaymentService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WriteOffCollectionFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loan::unsetEventDispatcher();
        activity()->disableLogging();
        Schema::dropAllTables();
        $this->createTestSchema();
    }

    public function test_recovery_is_recorded_without_changing_the_schedule_or_written_off_status(): void
    {
        $collectorId = $this->createCollector();
        $loan = $this->createLoan([
            'status' => 'written_off',
            'written_off_at' => '2026-07-01',
            'write_off_balance' => 800,
        ]);
        $payment = Payment::create([
            'loan_id' => $loan->id,
            'payment_number' => 1,
            'principal_amount' => 800,
            'interest_amount' => 80,
            'outstanding_balance' => 800,
            'total_paid' => 0,
            'payment_date' => '2026-07-15',
        ]);

        $result = app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => $collectorId,
            'amount_paid' => 125,
            'payment_method' => 'Cash',
            'repayment_type' => 'Recovery',
            'transaction_date' => '2026-08-01',
            'penalty_amount' => 0,
            'fee_amount' => 25,
            'waived_amount' => 0,
        ]);

        $this->assertSame('written_off', $result['loan']->status);
        $this->assertEquals(125, $result['loan']->recovery_amount);
        $this->assertEquals(125, $result['transaction']->recovery_amount);
        $this->assertEquals(0, $result['transaction']->principal_paid);
        $this->assertEquals(0, $result['transaction']->interest_paid);
        $this->assertEquals(0, $result['transaction']->fee_paid);
        $this->assertEquals(0, $payment->fresh()->total_paid);
    }

    public function test_recovery_is_rejected_for_a_loan_that_is_not_written_off(): void
    {
        $loan = $this->createLoan(['status' => 'active']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recovery can only be recorded after the loan is written off.');

        app(RepaymentService::class)->process([
            'loan_id' => $loan->id,
            'collector_id' => $this->createCollector(),
            'amount_paid' => 50,
            'payment_method' => 'Cash',
            'repayment_type' => 'Recovery',
            'transaction_date' => '2026-08-01',
        ]);
    }

    public function test_recovery_cannot_exceed_the_remaining_write_off_balance_and_can_be_voided_cleanly(): void
    {
        $collectorId = $this->createCollector();
        $loan = $this->createLoan([
            'status' => 'written_off',
            'written_off_at' => '2026-07-01',
            'write_off_balance' => 800,
        ]);
        $service = app(RepaymentService::class);
        $result = $service->process([
            'loan_id' => $loan->id,
            'collector_id' => $collectorId,
            'amount_paid' => 700,
            'payment_method' => 'Cash',
            'repayment_type' => 'Recovery',
            'transaction_date' => '2026-08-01',
        ]);

        try {
            $service->process([
                'loan_id' => $loan->id,
                'collector_id' => $collectorId,
                'amount_paid' => 101,
                'payment_method' => 'Cash',
                'repayment_type' => 'Recovery',
                'transaction_date' => '2026-08-02',
            ]);
            $this->fail('An over-recovery should have been rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('remaining write-off balance (100.00)', $exception->getMessage());
        }

        $voided = $service->void($result['transaction']);

        $this->assertSame('written_off', $voided['loan']->status);
        $this->assertEquals(0, $voided['loan']->recovery_amount);
        $this->assertSoftDeleted('repayment_transactions', ['id' => $result['transaction']->id]);
    }

    public function test_report_is_an_as_of_snapshot_and_recovery_is_limited_to_the_selected_period(): void
    {
        $activeLoan = $this->createLoan([
            'loan_code' => 'OLD-ACTIVE',
            'status' => 'active',
            'start_date' => '2025-01-01',
        ]);
        $writtenOffLoan = $this->createLoan([
            'loan_code' => 'OLD-WO',
            'status' => 'written_off',
            'start_date' => '2025-02-01',
            'written_off_at' => '2026-06-01',
            'write_off_balance' => 800,
        ]);
        $this->createLoan(['status' => 'pending_check']);
        $this->createLoan(['status' => 'rejected']);

        $collectorId = $this->createCollector();
        $this->createRecovery($writtenOffLoan, $collectorId, 100, '2026-06-15');
        $this->createRecovery($writtenOffLoan, $collectorId, 50, '2026-07-15');

        $request = Request::create('/reports/write-off-collection', 'GET', [
            'from_date' => '2026-07-01',
            'to_date' => '2026-08-09',
            'currency' => 'all',
            'paginate' => 'false',
        ]);
        $response = app(WriteOffCollectionReportController::class)->index($request);
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $payload['meta']['total']);
        $this->assertCount(1, $payload['data']['Standard Loan']);
        $this->assertCount(1, $payload['data']['Loss Loan']);
        $this->assertSame($activeLoan->loan_code, $payload['data']['Standard Loan'][0]['loan_code']);
        $this->assertEquals(50, $payload['data']['Loss Loan'][0]['recovery_amount']);
        $this->assertEquals(650, $payload['data']['Loss Loan'][0]['default_balance']);
        $this->assertEquals(50, $payload['meta']['grand_totals']['USD']['recovery_amount']);
    }

    public function test_dashboard_portfolio_quality_uses_schedule_aging_instead_of_stale_loan_fields(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');

        try {
            $loan = $this->createLoan([
                'status' => 'active',
                'aging' => 0,
                'locked_aging' => 0,
                'late_since_date' => null,
            ]);
            Payment::create([
                'loan_id' => $loan->id,
                'payment_number' => 1,
                'principal_amount' => 1000,
                'interest_amount' => 10,
                'outstanding_balance' => 1000,
                'total_paid' => 0,
                'payment_date' => '2026-06-30',
            ]);

            $response = app(DashboardController::class)
                ->getStats(Request::create('/dashboard/stats', 'GET'));
            $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertEquals(0, $payload['portfolio_quality']['standard']);
            $this->assertEquals(1000, $payload['portfolio_quality']['special_mention']);
            $this->assertEquals(0, $payload['portfolio_quality']['loss']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_par30_excludes_day_30_and_includes_day_31(): void
    {
        Carbon::setTestNow('2026-08-09 12:00:00');

        try {
            foreach ([
                '2026-07-10', // Aging 30: not yet PAR30.
                '2026-07-09', // Aging 31: included in PAR30.
            ] as $paymentDate) {
                $loan = $this->createLoan(['status' => 'active']);
                Payment::create([
                    'loan_id' => $loan->id,
                    'payment_number' => 1,
                    'principal_amount' => 1000,
                    'interest_amount' => 10,
                    'outstanding_balance' => 1000,
                    'total_paid' => 0,
                    'payment_date' => $paymentDate,
                ]);
            }

            $response = app(DashboardController::class)
                ->getStats(Request::create('/dashboard/stats', 'GET'));
            $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertEquals(2000, $payload['outstanding_amount']);
            $this->assertEquals(1000, $payload['par_amount']);
            $this->assertEquals(50, $payload['par_ratio']);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function createLoan(array $overrides = []): Loan
    {
        return Loan::create(array_merge([
            'loan_code' => 'LN-'.uniqid(),
            'amount' => 1000,
            'currency' => 'USD',
            'interest_rate' => 1,
            'duration_months' => 12,
            'monthly_payment' => 100,
            'payment_frequency' => 'monthly',
            'repayment_method' => 'fixed_monthly',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ], $overrides));
    }

    private function createCollector(): int
    {
        return (int) DB::table('loan_officers')->insertGetId([
            'name' => 'Test Collector',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRecovery(Loan $loan, int $collectorId, float $amount, string $date): void
    {
        RepaymentTransaction::create([
            'loan_id' => $loan->id,
            'collector_id' => $collectorId,
            'amount_paid' => $amount,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'penalty_paid' => 0,
            'fee_paid' => 0,
            'payment_method' => 'Cash',
            'repayment_type' => 'Recovery',
            'transaction_date' => $date,
            'recovery_amount' => $amount,
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('loan_officers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        foreach (['borrowers', 'co_borrowers', 'guarantors'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('customer_code')->nullable();
                $table->string('phone')->nullable();
                $table->string('village')->nullable();
                $table->string('commune')->nullable();
                $table->string('district')->nullable();
                $table->string('province')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->string('loan_code')->nullable();
            $table->unsignedBigInteger('borrower_id')->nullable();
            $table->unsignedBigInteger('co_borrower_id')->nullable();
            $table->unsignedBigInteger('guarantor_id')->nullable();
            $table->unsignedBigInteger('loan_officer_id')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->string('currency')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->integer('duration_months')->nullable();
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->string('payment_frequency')->nullable();
            $table->string('repayment_method')->nullable();
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('status')->default('pending');
            $table->integer('aging')->default(0);
            $table->integer('locked_aging')->default(0);
            $table->decimal('accumulated_penalty', 15, 2)->default(0);
            $table->date('late_since_date')->nullable();
            $table->date('penalty_late_since_date')->nullable();
            $table->date('written_off_at')->nullable();
            $table->decimal('write_off_balance', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->string('admin_fee_type')->default('one_time');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collaterals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('prepayment', 15, 2)->default(0);
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->date('payment_date');
            $table->string('payment_method')->default('Cash');
            $table->date('settled_at')->nullable();
            $table->date('settled_due_date')->nullable();
            $table->integer('settled_days_variance')->nullable();
            $table->string('settlement_source', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('collector_id')->nullable();
            $table->decimal('amount_paid', 15, 2);
            $table->decimal('waived_amount', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('prepayment_paid', 15, 2)->default(0);
            $table->decimal('paid_off_amount', 15, 2)->default(0);
            $table->decimal('recovery_amount', 15, 2)->default(0);
            $table->decimal('withdrawn_prepayment', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('repayment_type')->default('Normal');
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('repayment_transaction_id');
            $table->decimal('amount_applied', 15, 2)->default(0);
            $table->decimal('fee_applied', 15, 2)->default(0);
            $table->decimal('interest_applied', 15, 2)->default(0);
            $table->decimal('principal_applied', 15, 2)->default(0);
            $table->decimal('penalty_applied', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
