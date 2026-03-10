<?php

namespace App\Filament\Widgets;

use App\Models\RepaymentTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Repayments';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RepaymentTransaction::query()
                    ->with(['loan.borrower'])
                    ->latest('transaction_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('loan.borrower.full_name')
                    ->label('Borrower')
                    ->getStateUsing(fn($record) => ($record->loan->borrower->first_name ?? '') . ' ' . ($record->loan->borrower->last_name ?? '')),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Amount')
                    ->money('USD')
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->badge(),
                Tables\Columns\TextColumn::make('repayment_type')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
            ])
            ->paginated(false);
    }
}
