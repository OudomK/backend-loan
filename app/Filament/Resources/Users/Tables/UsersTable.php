<?php

namespace App\Filament\Resources\Users\Tables;

use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    private static function isTrashed($record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed();
    }

    private static function canManageRecord($record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Filament::auth()->user();
        $superAdminRole = Utils::getSuperAdminName();

        return $user?->hasRole($superAdminRole) || ! $record->hasRole($superAdminRole);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Users')
            ->description('Manage system users, their access credentials, and assigned roles.')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => collect([
                        filled($record->username) ? $record->username : null,
                        filled($record->email) ? $record->email : null,
                    ])->filter()->implode(' • ')),
                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('xl'),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->description(fn ($record): ?string => filled($record->created_at) ? $record->created_at->format('d M Y') : null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('2xl'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('roles')
                    ->relationship('roles', 'name', function ($query) {
                        $superAdminRole = Utils::getSuperAdminName();
                        /** @var \App\Models\User|null $user */
                        $user = Filament::auth()->user();

                        if (! $user?->hasRole($superAdminRole)) {
                            $query->where('name', '!=', $superAdminRole);
                        }
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn ($record): bool => self::canManageRecord($record))
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Manage user')
                    ->visible(fn ($record): bool => self::canManageRecord($record) && ! self::isTrashed($record)),
                DeleteAction::make()
                    ->visible(fn ($record): bool => self::canManageRecord($record) && ! self::isTrashed($record)),
                RestoreAction::make()
                    ->visible(fn ($record): bool => self::canManageRecord($record) && self::isTrashed($record)),
                ForceDeleteAction::make()
                    ->visible(fn ($record): bool => self::canManageRecord($record) && self::isTrashed($record)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New User')
                    ->icon('heroicon-m-plus-circle')
                    ->button(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
