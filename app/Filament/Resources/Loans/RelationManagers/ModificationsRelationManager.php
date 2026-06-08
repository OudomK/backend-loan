<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'modifications';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required(),
                Textarea::make('old_data')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('new_data')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')->badge(),
                TextEntry::make('created_at')
                    ->dateTime('d/m/Y h:i A')
                    ->label('Modified At'),
                \Filament\Infolists\Components\KeyValueEntry::make('old_data')
                    ->label('Old Terms'),
                \Filament\Infolists\Components\KeyValueEntry::make('new_data')
                    ->label('New Terms'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Modified At')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reschedule' => 'warning',
                        'refinance' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('old_data.remaining_term')
                    ->label('Old Term')
                    ->suffix(' mos'),
                TextColumn::make('new_data.remaining_term')
                    ->label('New Term')
                    ->suffix(' mos'),
                TextColumn::make('old_data.interest_rate')
                    ->label('Old Rate')
                    ->suffix('%'),
                TextColumn::make('new_data.new_rate')
                    ->label('New Rate')
                    ->suffix('%'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
            ]);
    }
}
