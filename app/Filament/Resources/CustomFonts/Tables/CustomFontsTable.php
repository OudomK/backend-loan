<?php

namespace App\Filament\Resources\CustomFonts\Tables;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CustomFontsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('is_system')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'System' : 'Imported')
                    ->color(fn (bool $state): string => $state ? 'gray' : 'success'),
                TextColumn::make('admin_status')
                    ->label('Admin Font')
                    ->badge()
                    ->state(fn ($record): string => Setting::where('key', 'admin_font_family')->value('value') === $record->key ? 'In use' : 'Available')
                    ->color(fn (string $state): string => $state === 'In use' ? 'success' : 'gray'),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn ($record): bool => (bool) $record->is_system),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('No fonts available')
            ->emptyStateDescription('System fonts will appear here automatically after migrations run.')
            ->emptyStateIcon('heroicon-o-x-mark')
            ->checkIfRecordIsSelectableUsing(fn ($record): bool => ! (bool) $record->is_system)
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('use_for_admin')
                    ->label('Use')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => (bool) $record->is_active)
                    ->disabled(fn ($record): bool => Setting::where('key', 'admin_font_family')->value('value') === $record->key)
                    ->action(function ($record, $livewire): void {
                        Setting::updateOrCreate(
                            ['key' => 'admin_font_family'],
                            ['value' => $record->key],
                        );

                        Notification::make()
                            ->title("Admin font changed to {$record->name}")
                            ->success()
                            ->send();

                        $livewire->redirect(request()->fullUrl());
                    }),
                EditAction::make()
                    ->hidden(fn ($record): bool => (bool) $record->is_system),
                DeleteAction::make()
                    ->hidden(fn ($record): bool => (bool) $record->is_system),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
