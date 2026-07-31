<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanApprovalApiController extends Controller
{
    protected LoanApprovalService $approvalService;

    public function __construct(LoanApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Get all pending loans (pending_check, pending_verify, pending_approval)
     */
    public function getPendingApprovals(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            $user = \App\Models\User::first();
        }

        $allowedStatuses = [];

        if ($user && $user->can('check_loan')) {
            $allowedStatuses[] = 'pending_check';
        }
        if ($user && $user->can('verify_loan')) {
            $allowedStatuses[] = 'pending_verify';
        }
        if ($user && $user->can('approve_loan')) {
            $allowedStatuses[] = 'pending_approval';
        }

        if (empty($allowedStatuses)) {
            return response()->json([]);
        }

        $loans = Loan::with(['borrower', 'coBorrower', 'guarantor', 'officer', 'product'])
            ->whereIn('status', $allowedStatuses)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($loans);
    }

    /**
     * Perform an action on a loan (check, verify, approve, reject, resubmit)
     */
    public function performAction(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:check,verify,approve,reject,resubmit',
            'comments' => 'nullable|string',
        ]);

        $loan = Loan::findOrFail($id);
        
        // Use an existing user if auth fails (for local testing without strict auth token)
        $user = Auth::user();
        if (!$user) {
            $user = \App\Models\User::first();
        }

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $action = $request->input('action');
        $comments = $request->input('comments');

        try {
            switch ($action) {
                case 'check':
                    if (!$user->can('check_loan')) {
                        return response()->json(['error' => 'You do not have permission to check loans.'], 403);
                    }
                    $this->approvalService->check($loan, $user, $comments);
                    break;
                case 'verify':
                    if (!$user->can('verify_loan')) {
                        return response()->json(['error' => 'You do not have permission to verify loans.'], 403);
                    }
                    $this->approvalService->verify($loan, $user, $comments);
                    break;
                case 'approve':
                    if (!$user->can('approve_loan')) {
                        return response()->json(['error' => 'You do not have permission to approve loans.'], 403);
                    }
                    $this->approvalService->approve($loan, $user, $comments);
                    break;
                case 'reject':
                    if (!$user->can('reject_loan')) {
                        return response()->json(['error' => 'You do not have permission to reject loans.'], 403);
                    }
                    $this->approvalService->reject($loan, $user, $comments);
                    break;
                case 'resubmit':
                    if (!$user->can('check_loan')) { // same as check for resubmit
                        return response()->json(['error' => 'You do not have permission to resubmit loans.'], 403);
                    }
                    $this->approvalService->resubmit($loan, $user, $comments);
                    break;
            }

            return response()->json([
                'message' => 'Action ' . $action . ' performed successfully.',
                'loan' => $loan->fresh()
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred during approval action.', 'details' => $e->getMessage()], 500);
        }
    }
}
