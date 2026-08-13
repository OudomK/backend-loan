<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanApprovalApiController;
use App\Http\Controllers\LoanController;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanApprovalService;
use App\Services\RejectedLoanScheduleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class LoanApprovalWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loan::unsetEventDispatcher();
        Payment::unsetEventDispatcher();
        activity()->disableLogging();
        Schema::dropAllTables();
        $this->createTestSchema();
    }

    public function test_approval_flow_uses_distinct_actors_and_records_every_transition(): void
    {
        $submitter = $this->createUser('Submitter');
        $checker = $this->createUser('Checker');
        $verifier = $this->createUser('Verifier');
        $approver = $this->createUser('Approver');
        $loan = $this->createLoan('FLOW-001', 'pending');
        $this->createPayment($loan);
        $service = app(LoanApprovalService::class);

        $loan = $service->submit($loan, $submitter, 'Submitted');
        $loan = $service->check($loan, $checker, 'Checked');
        $loan = $service->verify($loan, $verifier, 'Verified');
        $loan = $service->approve($loan, $approver, 'Approved');

        $this->assertSame(LoanApproval::STATUS_APPROVED, $loan->status);
        $this->assertSame($submitter->id, (int) $loan->submitted_by);
        $this->assertSame($checker->id, (int) $loan->checked_by);
        $this->assertSame($verifier->id, (int) $loan->verified_by);
        $this->assertSame($approver->id, (int) $loan->approved_by);
        $this->assertSame(
            ['submitted', 'checked', 'verified', 'approved'],
            $loan->approvals()->orderBy('id')->pluck('action')->all()
        );
    }

    public function test_same_user_can_participate_in_all_approval_stages(): void
    {
        $user = $this->createUser('Workflow User');
        $service = app(LoanApprovalService::class);
        $loan = $service->submit($this->createLoan('SAME-ACTOR-001', 'pending'), $user);
        $this->createPayment($loan);

        $loan = $service->check($loan, $user);
        $loan = $service->verify($loan, $user);
        $loan = $service->approve($loan, $user);

        $this->assertSame(LoanApproval::STATUS_APPROVED, $loan->status);
        $this->assertSame($user->id, (int) $loan->submitted_by);
        $this->assertSame($user->id, (int) $loan->checked_by);
        $this->assertSame($user->id, (int) $loan->verified_by);
        $this->assertSame($user->id, (int) $loan->approved_by);
    }

    public function test_stale_loan_instance_cannot_create_a_duplicate_transition(): void
    {
        $submitter = $this->createUser('Submitter');
        $firstChecker = $this->createUser('First Checker');
        $secondChecker = $this->createUser('Second Checker');
        $service = app(LoanApprovalService::class);
        $loan = $service->submit($this->createLoan('LOCK-001', 'pending'), $submitter);
        $staleLoan = $loan->fresh();

        $service->check($loan, $firstChecker);

        try {
            $service->check($staleLoan, $secondChecker);
            $this->fail('A stale approval action must be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(
                1,
                LoanApproval::query()
                    ->where('loan_id', $loan->id)
                    ->where('action', LoanApproval::ACTION_CHECKED)
                    ->count()
            );
        }
    }

    public function test_reject_requires_a_reason_at_the_api_boundary(): void
    {
        $loan = $this->createLoan('REJECT-VALIDATION', 'pending_check');
        $user = $this->createUser('Rejector');
        $request = Request::create('/', 'POST', ['action' => 'reject']);
        $request->setUserResolver(fn () => $user);

        $this->expectException(ValidationException::class);

        app(LoanApprovalApiController::class)->performAction($request, $loan->id);
    }

    public function test_reject_requires_permission_at_the_api_boundary(): void
    {
        $loan = $this->createLoan('REJECT-PERMISSION', LoanApproval::STATUS_PENDING_CHECK);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('reject_loan')->andReturnFalse();
        $request = Request::create('/', 'POST', [
            'action' => 'reject',
            'comments' => 'Incomplete documents',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(LoanApprovalApiController::class)->performAction($request, $loan->id);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(LoanApproval::STATUS_PENDING_CHECK, $loan->fresh()->status);
        $this->assertSame(0, $loan->approvals()->count());
    }

    public function test_reject_service_rejects_a_blank_reason(): void
    {
        $loan = $this->createLoan('REJECT-BLANK', LoanApproval::STATUS_PENDING_CHECK);
        $rejector = $this->createUser('Blank Reason Rejector');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rejection reason is required');

        app(LoanApprovalService::class)->reject($loan, $rejector, '   ');
    }

    public function test_generic_loan_update_cannot_change_workflow_status(): void
    {
        $loan = $this->createLoan('STATUS-BYPASS', LoanApproval::STATUS_PENDING_APPROVAL);
        $request = Request::create('/', 'PUT', ['status' => 'active']);

        try {
            app(LoanController::class)->update($request, $loan);
            $this->fail('The generic loan endpoint must not change workflow status.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertSame(LoanApproval::STATUS_PENDING_APPROVAL, $loan->fresh()->status);
        }
    }

    public function test_financial_terms_can_only_be_edited_after_rejection(): void
    {
        $loan = $this->createLoan('TERMS-WHILE-PENDING', LoanApproval::STATUS_PENDING_CHECK);
        $request = Request::create('/', 'PUT', ['amount' => 1500]);

        try {
            app(LoanController::class)->update($request, $loan);
            $this->fail('Pending financial terms must be read-only until rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('loan', $exception->errors());
            $this->assertEquals(1000, $loan->fresh()->amount);
        }
    }

    public function test_new_loan_cannot_start_in_a_terminal_status(): void
    {
        $borrowerId = DB::table('borrowers')->insertGetId([
            'first_name' => 'Workflow',
            'last_name' => 'Borrower',
        ]);
        $request = Request::create('/', 'POST', [
            'borrower_id' => $borrowerId,
            'status' => 'rejected',
            'purpose' => 'Test loan',
        ]);

        try {
            app(LoanController::class)->store($request);
            $this->fail('A new loan must always start in the pending check stage.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
            $this->assertSame(0, Loan::query()->count());
        }
    }

    public function test_loan_resource_routes_require_feature_permissions(): void
    {
        $routes = collect(app('router')->getRoutes());

        foreach ([
            ['GET', 'api/loans', 'permission:ui:loan_application:view'],
            ['GET', 'api/loans/{loan}', 'permission:ui:loan_application:view'],
            ['POST', 'api/loans', 'permission:ui:loan_application:create'],
            ['PUT', 'api/loans/{loan}', 'permission:ui:loan_application:edit'],
            ['DELETE', 'api/loans/{loan}', 'permission:ui:loan_application:delete'],
        ] as [$method, $uri, $permission]) {
            $route = $routes->first(fn ($candidate): bool => $candidate->uri() === $uri
                && in_array($method, $candidate->methods(), true));

            $this->assertNotNull($route, "Missing route {$method} {$uri}");
            $this->assertContains($permission, $route->gatherMiddleware());
        }
    }

    public function test_loan_without_a_saved_schedule_cannot_be_approved(): void
    {
        $approver = $this->createUser('Approver');
        $loan = $this->createLoan('NO-SCHEDULE', LoanApproval::STATUS_PENDING_APPROVAL);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('without a saved repayment schedule');

        app(LoanApprovalService::class)->approve($loan, $approver);
    }

    public function test_reject_only_user_can_see_all_pending_stages_with_saved_payments(): void
    {
        foreach (LoanApproval::pendingStatuses() as $index => $status) {
            $loan = $this->createLoan('REJECT-LIST-'.$index, $status);
            $loan->payments()->create([
                'payment_number' => 1,
                'principal_amount' => 100,
                'interest_amount' => 10,
                'fee_amount' => 0,
                'outstanding_balance' => 900,
                'total_paid' => 0,
                'payment_date' => '2026-09-11',
                'payment_method' => 'Cash',
            ]);
        }

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(
            fn (string $permission): bool => $permission === 'reject_loan'
        );
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(LoanApprovalApiController::class)->getPendingApprovals($request);
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(3, $payload);
        $this->assertCount(1, $payload[0]['payments']);
    }

    public function test_resubmit_assigns_the_new_submitter_and_clears_old_actors(): void
    {
        $oldSubmitter = $this->createUser('Old Submitter');
        $rejector = $this->createUser('Rejector');
        $newSubmitter = $this->createUser('New Submitter');
        $service = app(LoanApprovalService::class);
        $loan = $service->submit($this->createLoan('RESUBMIT-001', 'pending'), $oldSubmitter);
        $this->createPayment($loan);
        $loan = $service->reject($loan, $rejector, 'Incorrect documents');
        $loan = $service->resubmit($loan, $newSubmitter, 'Documents corrected');

        $this->assertSame(LoanApproval::STATUS_PENDING_CHECK, $loan->status);
        $this->assertSame($newSubmitter->id, (int) $loan->submitted_by);
        $this->assertNull($loan->checked_by);
        $this->assertNull($loan->verified_by);
        $this->assertNull($loan->approved_by);
        $this->assertNull($loan->rejection_reason);
    }

    public function test_editing_rejected_financial_terms_marks_the_schedule_dirty_and_blocks_resubmit(): void
    {
        $submitter = $this->createUser('Correction Submitter');
        $rejector = $this->createUser('Correction Rejector');
        $loan = $this->createLoan('CORRECTION-DIRTY', LoanApproval::STATUS_PENDING_CHECK);
        $this->createPayment($loan);
        $loan = app(LoanApprovalService::class)->reject($loan, $rejector, 'Interest rate is incorrect');

        Loan::clearBootedModels();
        Loan::setEventDispatcher(new \Illuminate\Events\Dispatcher(app()));
        $loan = Loan::findOrFail($loan->id);
        $loan->update(['interest_rate' => 2.5]);

        $this->assertTrue($loan->fresh()->schedule_needs_recalculation);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be regenerated');

        app(LoanApprovalService::class)->resubmit($loan->fresh(), $submitter);
    }

    public function test_rejected_schedule_regeneration_uses_the_selected_method_and_enables_resubmit(): void
    {
        $rejector = $this->createUser('Schedule Rejector');
        $corrector = $this->createUser('Schedule Corrector');
        $loan = $this->createLoan('CORRECTION-REGENERATE', LoanApproval::STATUS_PENDING_CHECK);
        $oldPayment = $this->createPayment($loan);
        $loan = app(LoanApprovalService::class)->reject($loan, $rejector, 'Amount and method are incorrect');

        $loan->update([
            'amount' => 1200,
            'interest_rate' => 2,
            'duration_months' => 3,
            'start_date' => '2026-08-12',
            'repayment_method' => 'annuity_monthly',
            'pay_day_1' => 20,
        ]);

        $preview = app(RejectedLoanScheduleService::class)->preview($loan->fresh());
        $this->assertCount(3, $preview['schedule']);
        $this->assertEquals(1200, $preview['summary']['principal']);
        $this->assertSame(
            20,
            \Carbon\Carbon::createFromFormat('d/m/Y', $preview['schedule'][0]['date'])->day
        );

        $loan = app(RejectedLoanScheduleService::class)->regenerate($loan->fresh(), $corrector);

        $this->assertFalse($loan->schedule_needs_recalculation);
        $this->assertSame('monthly', $loan->payment_frequency);
        $this->assertSame(3, $loan->payments()->count());
        $this->assertEquals(1200, $loan->payments()->sum('principal_amount'));
        $this->assertEquals(0, $loan->payments()->orderByDesc('payment_number')->value('outstanding_balance'));
        $this->assertSoftDeleted('payments', ['id' => $oldPayment->id]);

        $loan = app(LoanApprovalService::class)->resubmit($loan, $corrector, 'Corrected terms and schedule');
        $this->assertSame(LoanApproval::STATUS_PENDING_CHECK, $loan->status);
    }

    private function createUser(string $name): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'password' => 'test-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function createLoan(string $code, string $status): Loan
    {
        return Loan::create([
            'loan_code' => $code,
            'amount' => 1000,
            'currency' => 'USD',
            'repayment_method' => 'fixed_monthly',
            'status' => $status,
        ]);
    }

    private function createPayment(Loan $loan): Payment
    {
        return $loan->payments()->create([
            'payment_number' => 1,
            'principal_amount' => 1000,
            'interest_amount' => 10,
            'fee_amount' => 0,
            'outstanding_balance' => 0,
            'total_paid' => 0,
            'payment_date' => '2026-09-11',
            'payment_method' => 'Cash',
        ]);
    }

    private function createTestSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->string('loan_code');
            $table->decimal('amount', 15, 2);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->integer('duration_months')->default(1);
            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->string('currency')->default('USD');
            $table->string('repayment_method')->nullable();
            $table->string('payment_frequency')->nullable();
            $table->unsignedTinyInteger('pay_day_1')->nullable();
            $table->unsignedTinyInteger('pay_day_2')->nullable();
            $table->decimal('admin_fee', 8, 4)->default(0);
            $table->string('admin_fee_type')->default('one_time');
            $table->date('maturity_date')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('schedule_needs_recalculation')->default(false);
            $table->timestamp('schedule_recalculated_at')->nullable();
            $table->unsignedBigInteger('schedule_recalculated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loan_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->integer('payment_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->unsignedBigInteger('repayment_transaction_id')->nullable();
            $table->date('payment_date');
            $table->string('payment_method')->default('Cash');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collaterals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('repayment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->string('payment_method')->default('Cash');
            $table->date('transaction_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
