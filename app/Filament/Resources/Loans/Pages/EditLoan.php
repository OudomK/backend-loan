<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Services\LoanApprovalService;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── Approval Workflow Actions ────────────────────────────
            Action::make('check')
                ->label('Check')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('warning')
                ->visible(fn () => $this->record->canBeChecked())
                ->requiresConfirmation()
                ->modalHeading('Check Loan Application')
                ->modalDescription('Confirm that this loan application has been properly checked.')
                ->form([
                    Textarea::make('comments')
                        ->label('Comments (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    app(LoanApprovalService::class)->check($this->record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                    Notification::make()->title('Loan checked successfully')->success()->send();
                    $this->refreshFormData(['status', 'checked_by', 'checked_at']);
                }),

            Action::make('verify')
                ->label('Verify')
                ->icon('heroicon-m-shield-check')
                ->color('info')
                ->visible(fn () => $this->record->canBeVerified())
                ->requiresConfirmation()
                ->modalHeading('Verify Loan Application')
                ->modalDescription('Confirm that this loan application has been properly verified.')
                ->form([
                    Textarea::make('comments')
                        ->label('Comments (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    app(LoanApprovalService::class)->verify($this->record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                    Notification::make()->title('Loan verified successfully')->success()->send();
                    $this->refreshFormData(['status', 'verified_by', 'verified_at']);
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeApproved())
                ->requiresConfirmation()
                ->modalHeading('Approve Loan Application')
                ->modalDescription('This will activate the loan. Are you sure?')
                ->form([
                    Textarea::make('comments')
                        ->label('Comments (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    app(LoanApprovalService::class)->approve($this->record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                    Notification::make()->title('Loan approved and activated!')->success()->send();
                    $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canBeRejected())
                ->requiresConfirmation()
                ->modalHeading('Reject Loan Application')
                ->modalDescription('Please provide a reason for rejection.')
                ->form([
                    Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    app(LoanApprovalService::class)->reject($this->record, \Illuminate\Support\Facades\Auth::user(), $data['reason']);
                    Notification::make()->title('Loan rejected')->danger()->send();
                    $this->refreshFormData(['status', 'rejection_reason']);
                }),

            Action::make('resubmit')
                ->label('Resubmit')
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->canBeResubmitted())
                ->requiresConfirmation()
                ->modalHeading('Resubmit Loan Application')
                ->modalDescription('This will restart the approval process from Check stage.')
                ->form([
                    Textarea::make('comments')
                        ->label('Comments (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    app(LoanApprovalService::class)->resubmit($this->record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                    Notification::make()->title('Loan resubmitted for review')->success()->send();
                    $this->refreshFormData(['status', 'rejection_reason', 'checked_by', 'checked_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at']);
                }),

            // ── Standard Actions ─────────────────────────────────────
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
