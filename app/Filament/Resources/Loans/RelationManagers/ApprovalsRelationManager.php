<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    protected static ?string $title = 'Approval History';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Approval Trail')
            ->description('Complete history of all approval actions on this loan.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'submitted' => 'Submitted',
                        'checked' => 'Checked',
                        'verified' => 'Verified',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'submitted' => 'gray',
                        'checked' => 'warning',
                        'verified' => 'info',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('from_status')
                    ->label('From')
                    ->formatStateUsing(fn(?string $state): string => $state ? ucfirst(str_replace('_', ' ', $state)) : '—')
                    ->color('gray'),
                TextColumn::make('to_status')
                    ->label('To')
                    ->formatStateUsing(fn(string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending_check' => 'warning',
                        'pending_verify' => 'info',
                        'pending_approval' => 'primary',
                        'active' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('comments')
                    ->label('Comments')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->comments),
            ]);
    }
}
