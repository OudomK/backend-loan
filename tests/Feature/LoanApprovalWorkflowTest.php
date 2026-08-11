<?php

namespace Tests\Feature;

use App\Http\Controllers\LoanApprovalApiController;
use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoanApprovalService;
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

    public function test_same_user_cannot_participate_in_two_sequential_approval_stages(): void
    {
        $submitter = $this->createUser('Submitter');
        $loan = app(LoanApprovalService::class)->submit(
            $this->createLoan('SEPARATION-001', 'pending'),
            $submitter
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same user cannot check');

        app(LoanApprovalService::class)->check($loan, $submitter);
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
        $loan = $service->reject($loan, $rejector, 'Incorrect documents');
        $loan = $service->resubmit($loan, $newSubmitter, 'Documents corrected');

        $this->assertSame(LoanApproval::STATUS_PENDING_CHECK, $loan->status);
        $this->assertSame($newSubmitter->id, (int) $loan->submitted_by);
        $this->assertNull($loan->checked_by);
        $this->assertNull($loan->verified_by);
        $this->assertNull($loan->approved_by);
        $this->assertNull($loan->rejection_reason);
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
            $table->string('currency')->default('USD');
            $table->string('repayment_method')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
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
            $table->decimal('outstanding_balance', 15, 2)->nullable();
            $table->decimal('total_paid', 15, 2)->default(0);
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
    }
}
