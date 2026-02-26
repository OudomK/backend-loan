<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Models\LeaveRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeaveRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('leave_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Approved' => 'Approved',
                        'Rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn(LeaveRequest $record): bool => $record->status === 'Pending')
                    ->action(function (LeaveRequest $record) {
                        $record->update([
                            'status' => 'Approved',
                            'approved_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation(),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn(LeaveRequest $record): bool => $record->status === 'Pending')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (LeaveRequest $record, array $data) {
                        $record->update([
                            'status' => 'Rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'approved_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
