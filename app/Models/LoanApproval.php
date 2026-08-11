<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApproval extends Model
{
    protected $fillable = [
        'loan_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'comments',
    ];

    public function loan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Valid actions for the approval workflow.
     */
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_CHECKED = 'checked';
    public const ACTION_VERIFIED = 'verified';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';

    /**
     * Valid statuses for the loan approval workflow.
     */
    public const STATUS_PENDING_CHECK = 'pending_check';
    public const STATUS_PENDING_VERIFY = 'pending_verify';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'active';
    public const STATUS_REJECTED = 'rejected';

    /**
     * All pending statuses (for querying).
     */
    public static function pendingStatuses(): array
    {
        return [
            self::STATUS_PENDING_CHECK,
            self::STATUS_PENDING_VERIFY,
            self::STATUS_PENDING_APPROVAL,
        ];
    }

    /**
     * Loan statuses that must not be treated as disbursed portfolio records.
     */
    public static function nonReportableStatuses(): array
    {
        return [
            'pending',
            ...self::pendingStatuses(),
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Human-readable labels for each status.
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING_CHECK => 'Pending Check',
            self::STATUS_PENDING_VERIFY => 'Pending Verify',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * Human-readable labels for each action.
     */
    public static function actionLabels(): array
    {
        return [
            self::ACTION_SUBMITTED => 'Submitted',
            self::ACTION_CHECKED => 'Checked',
            self::ACTION_VERIFIED => 'Verified',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
        ];
    }
}
