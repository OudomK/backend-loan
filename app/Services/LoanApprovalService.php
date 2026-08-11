<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoanApprovalService
{
    /**
     * Submit a loan for approval (initial submission).
     */
    public function submit(Loan $loan, User $user, ?string $comments = null): Loan
    {
        return DB::transaction(function () use ($loan, $user, $comments): Loan {
            $loan = $this->lockLoan($loan);
            $fromStatus = $loan->status;

            $loan->update([
                'status' => LoanApproval::STATUS_PENDING_CHECK,
                'submitted_by' => $user->id,
                'rejection_reason' => null, // Clear any previous rejection
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_SUBMITTED,
                'from_status' => $fromStatus,
                'to_status' => LoanApproval::STATUS_PENDING_CHECK,
                'comments' => $comments,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Check a loan (stage 2).
     */
    public function check(Loan $loan, User $user, ?string $comments = null): Loan
    {
        return DB::transaction(function () use ($loan, $user, $comments): Loan {
            $loan = $this->lockLoan($loan);
            if (!$loan->canBeChecked()) {
                throw new \InvalidArgumentException("Loan #{$loan->id} cannot be checked. Current status: {$loan->status}");
            }
            $this->ensureDifferentActor($loan, $user, ['submitted_by'], 'check');

            $loan->update([
                'status' => LoanApproval::STATUS_PENDING_VERIFY,
                'checked_by' => $user->id,
                'checked_at' => now(),
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_CHECKED,
                'from_status' => LoanApproval::STATUS_PENDING_CHECK,
                'to_status' => LoanApproval::STATUS_PENDING_VERIFY,
                'comments' => $comments,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Verify a loan (stage 3).
     */
    public function verify(Loan $loan, User $user, ?string $comments = null): Loan
    {
        return DB::transaction(function () use ($loan, $user, $comments): Loan {
            $loan = $this->lockLoan($loan);
            if (!$loan->canBeVerified()) {
                throw new \InvalidArgumentException("Loan #{$loan->id} cannot be verified. Current status: {$loan->status}");
            }
            $this->ensureDifferentActor($loan, $user, ['submitted_by', 'checked_by'], 'verify');

            $loan->update([
                'status' => LoanApproval::STATUS_PENDING_APPROVAL,
                'verified_by' => $user->id,
                'verified_at' => now(),
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_VERIFIED,
                'from_status' => LoanApproval::STATUS_PENDING_VERIFY,
                'to_status' => LoanApproval::STATUS_PENDING_APPROVAL,
                'comments' => $comments,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Approve a loan (final stage → active).
     */
    public function approve(Loan $loan, User $user, ?string $comments = null): Loan
    {
        return DB::transaction(function () use ($loan, $user, $comments): Loan {
            $loan = $this->lockLoan($loan);
            if (!$loan->canBeApproved()) {
                throw new \InvalidArgumentException("Loan #{$loan->id} cannot be approved. Current status: {$loan->status}");
            }
            if (!$loan->payments()->exists()) {
                throw new \InvalidArgumentException(
                    "Loan #{$loan->id} cannot be approved without a saved repayment schedule."
                );
            }
            $this->ensureDifferentActor(
                $loan,
                $user,
                ['submitted_by', 'checked_by', 'verified_by'],
                'approve'
            );

            $loan->update([
                'status' => LoanApproval::STATUS_APPROVED, // 'active'
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_APPROVED,
                'from_status' => LoanApproval::STATUS_PENDING_APPROVAL,
                'to_status' => LoanApproval::STATUS_APPROVED,
                'comments' => $comments,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Reject a loan (can happen at any pending stage).
     */
    public function reject(Loan $loan, User $user, string $reason): Loan
    {
        return DB::transaction(function () use ($loan, $user, $reason): Loan {
            $loan = $this->lockLoan($loan);
            if (!$loan->canBeRejected()) {
                throw new \InvalidArgumentException("Loan #{$loan->id} cannot be rejected. Current status: {$loan->status}");
            }

            $fromStatus = $loan->status;

            $loan->update([
                'status' => LoanApproval::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_REJECTED,
                'from_status' => $fromStatus,
                'to_status' => LoanApproval::STATUS_REJECTED,
                'comments' => $reason,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Resubmit a previously rejected loan.
     */
    public function resubmit(Loan $loan, User $user, ?string $comments = null): Loan
    {
        return DB::transaction(function () use ($loan, $user, $comments): Loan {
            $loan = $this->lockLoan($loan);
            if (!$loan->canBeResubmitted()) {
                throw new \InvalidArgumentException("Loan #{$loan->id} cannot be resubmitted. Current status: {$loan->status}");
            }

            $loan->update([
                'status' => LoanApproval::STATUS_PENDING_CHECK,
                'rejection_reason' => null,
                'submitted_by' => $user->id,
                'checked_by' => null,
                'checked_at' => null,
                'verified_by' => null,
                'verified_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $loan->approvals()->create([
                'user_id' => $user->id,
                'action' => LoanApproval::ACTION_SUBMITTED,
                'from_status' => LoanApproval::STATUS_REJECTED,
                'to_status' => LoanApproval::STATUS_PENDING_CHECK,
                'comments' => $comments ?? 'Resubmitted after rejection',
            ]);

            return $loan->fresh();
        });
    }

    private function lockLoan(Loan $loan): Loan
    {
        return Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Enforce maker/checker separation for the sequential approval stages.
     *
     * @param  array<int, string>  $actorColumns
     */
    private function ensureDifferentActor(
        Loan $loan,
        User $user,
        array $actorColumns,
        string $action
    ): void {
        foreach ($actorColumns as $column) {
            if ($loan->{$column} !== null && (int) $loan->{$column} === (int) $user->id) {
                throw new \InvalidArgumentException(
                    "The same user cannot {$action} this loan after participating in an earlier approval stage."
                );
            }
        }
    }
}
