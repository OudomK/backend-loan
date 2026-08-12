<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Models\LoanApproval;
use App\Services\LoanApprovalService;
use App\Support\CurrencyHelper;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with(['borrower', 'officer', 'product']))
            ->heading('Loan Portfolio')
            ->description('Review disbursements, interest terms, and repayment status across all loan records.')
            ->defaultSort('start_date', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('loan_code')
                    ->label('Loan')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->start_date) ? 'Start ' . self::formatDate($record->start_date) : null,
                        filled($record->loan_cycle) ? 'Cycle ' . $record->loan_cycle : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('borrower.first_name')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => trim("{$record->borrower?->first_name} {$record->borrower?->last_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->description(fn($record): ?string => collect([
                        filled($record->officer?->name) ? 'Officer ' . $record->officer->name : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('product.name')
                    ->label('Product')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')
                    ->label('Principal')
                    ->formatStateUsing(fn ($state, $record) => CurrencyHelper::display(
                        (float) $state,
                        $record->currency ?? CurrencyHelper::USD,
                    ))
                    ->alignEnd()
                    ->sortable()
                    ->description(fn($record): ?string => collect([
                        filled($record->interest_rate) ? 'Rate ' . self::formatNumber((float) $record->interest_rate) . '%' : null,
                        filled($record->duration_months) ? $record->duration_months . ' ' . self::termUnitAbbreviation($record->payment_frequency) : null,
                        filled($record->monthly_payment)
                        ? 'Pay ' . CurrencyHelper::display(
                            (float) $record->monthly_payment,
                            $record->currency ?? CurrencyHelper::USD,
                        )
                        : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('duration_months')
                    ->label('Term')
                    ->formatStateUsing(fn($state, $record) => filled($state) ? $state . ' ' . self::termUnitAbbreviation($record->payment_frequency) : null)
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('start_date')
                    ->date('d M Y')
                    ->sortable()
                    ->visibleFrom('2xl'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'pending_check' => 'Pending Check',
                        'pending_verify' => 'Pending Verify',
                        'pending_approval' => 'Pending Approval',
                        'rejected' => 'Rejected',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'paid_off' => 'Paid Off',
                        'written_off' => 'Written Off',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'pending_check' => 'warning',
                        'pending_verify' => 'info',
                        'pending_approval' => 'primary',
                        'rejected' => 'danger',
                        'active' => 'success',
                        'completed' => 'info',
                        'paid_off' => 'success',
                        'written_off' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('purpose')
                    ->label('Purpose')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending (Legacy)',
                        'pending_check' => 'Pending Check',
                        'pending_verify' => 'Pending Verify',
                        'pending_approval' => 'Pending Approval',
                        'rejected' => 'Rejected',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'paid_off' => 'Paid off',
                        'written_off' => 'Written off',
                    ]),
                SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'KHR' => 'KHR',
                    ]),
                SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->label('Product')
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                // ── Approval Actions ──────────────────────────────────
                Action::make('check')
                    ->label('Check')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Mark as Checked')
                    ->authorize(fn (): bool => \Illuminate\Support\Facades\Auth::user()?->can('check_loan') ?? false)
                    ->visible(fn ($record) => $record->canBeChecked())
                    ->requiresConfirmation()
                    ->modalHeading('Check Loan Application')
                    ->modalDescription('Are you sure this loan application has been properly checked?')
                    ->form([
                        Textarea::make('comments')
                            ->label('Comments (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(LoanApprovalService::class)->check($record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                        Notification::make()->title('Loan checked successfully')->success()->send();
                    }),

                Action::make('verify')
                    ->label('Verify')
                    ->icon('heroicon-m-shield-check')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Mark as Verified')
                    ->authorize(fn (): bool => \Illuminate\Support\Facades\Auth::user()?->can('verify_loan') ?? false)
                    ->visible(fn ($record) => $record->canBeVerified())
                    ->requiresConfirmation()
                    ->modalHeading('Verify Loan Application')
                    ->modalDescription('Are you sure this loan application has been properly verified?')
                    ->form([
                        Textarea::make('comments')
                            ->label('Comments (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(LoanApprovalService::class)->verify($record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                        Notification::make()->title('Loan verified successfully')->success()->send();
                    }),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Approve Loan')
                    ->authorize(fn (): bool => \Illuminate\Support\Facades\Auth::user()?->can('approve_loan') ?? false)
                    ->visible(fn ($record) => $record->canBeApproved())
                    ->requiresConfirmation()
                    ->modalHeading('Approve Loan Application')
                    ->modalDescription('This will activate the loan. Are you sure?')
                    ->form([
                        Textarea::make('comments')
                            ->label('Comments (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(LoanApprovalService::class)->approve($record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                        Notification::make()->title('Loan approved and activated!')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Reject Loan')
                    ->authorize(fn (): bool => \Illuminate\Support\Facades\Auth::user()?->can('reject_loan') ?? false)
                    ->visible(fn ($record) => $record->canBeRejected())
                    ->requiresConfirmation()
                    ->modalHeading('Reject Loan Application')
                    ->modalDescription('Please provide a reason for rejection.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(2000)
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(LoanApprovalService::class)->reject($record, \Illuminate\Support\Facades\Auth::user(), $data['reason']);
                        Notification::make()->title('Loan rejected')->danger()->send();
                    }),

                Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon('heroicon-m-arrow-path')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Resubmit for Review')
                    ->authorize(fn (): bool => \Illuminate\Support\Facades\Auth::user()?->can('check_loan') ?? false)
                    ->visible(fn ($record) => $record->canBeResubmitted())
                    ->requiresConfirmation()
                    ->modalHeading('Resubmit Loan Application')
                    ->modalDescription('This will restart the approval process from Check stage.')
                    ->form([
                        Textarea::make('comments')
                            ->label('Comments (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        app(LoanApprovalService::class)->resubmit($record, \Illuminate\Support\Facades\Auth::user(), $data['comments'] ?? null);
                        Notification::make()->title('Loan resubmitted for review')->success()->send();
                    }),

                // ── Standard Actions ─────────────────────────────────
                EditAction::make()
                    ->label('Manage Loan')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Manage loan'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Loan')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Deletion removed
                ]),
            ]);
    }

    private static function formatDate(?string $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private static function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function termUnitAbbreviation(?string $paymentFrequency): string
    {
        $normalized = strtolower(trim((string) $paymentFrequency));

        return match ($normalized) {
            'monthly' => 'mo',
            'biweekly' => 'biwk',
            'weekly' => 'wk',
            'daily' => 'd',
            'term' => 'inst',
            default => 'mo',
        };
    }
}
