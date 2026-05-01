<?php

namespace App\Filament\Widgets;

use App\Models\Loan;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class RecentLoanApplications extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Loan Applications';
    protected static ?int $sort = 10;
    protected int|string|array $columnSpan = [
        'default' => 'full',
        '2xl' => 1,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Loan Applications')
            ->description('Latest loan applications across all statuses')
            ->emptyStateHeading('No loan applications')
            ->emptyStateDescription('New loan applications will appear here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->query(function () {
                return Loan::query()
                    ->with(['borrower', 'officer'])
                    ->latest('created_at')
                    ->limit(5);
            })
            ->columns([
                Tables\Columns\TextColumn::make('loan_code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold')
                    ->limit(14)
                    ->description(fn ($record): ?string => filled($record->created_at) ? $record->created_at->diffForHumans() : null),

                Tables\Columns\TextColumn::make('borrower')
                    ->label('Borrower')
                    ->getStateUsing(fn ($record) => ($record->borrower->last_name ?? '') . ' ' . ($record->borrower->first_name ?? ''))
                    ->limit(18)
                    ->description(fn ($record): ?string => filled($record->officer?->name) ? 'Officer ' . $record->officer->name : null),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, $record) {
                        $amount = (float) $state;
                        $isKhr = str_starts_with((string) ($record->currency ?? ''), 'KHR');
                        return $isKhr
                            ? '៛ ' . number_format($amount, 0)
                            : '$' . number_format($amount, 2);
                    })
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'completed' => 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->visibleFrom('xl'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->since()
                    ->color('gray')
                    ->visibleFrom('xl'),
            ])
            ->paginated(false);
    }
}
