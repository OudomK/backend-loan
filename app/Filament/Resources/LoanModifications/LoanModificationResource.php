<?php

namespace App\Filament\Resources\LoanModifications;

use App\Filament\Resources\LoanModifications\Pages\ManageLoanModifications;
use App\Models\LoanModification;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoanModificationResource extends Resource
{
    protected static ?string $model = LoanModification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Operations';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('loan_id')
                    ->required()
                    ->numeric(),
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('loan.loan_code')->label('Loan Code'),
                TextEntry::make('loan.borrower.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->loan->borrower->first_name . ' ' . $record->loan->borrower->last_name ?? '-'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at', 'desc')
            ->heading('Loan Modifications')
            ->description('Review and manage all loan modifications including reschedules and refinances.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Modified At')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
                TextColumn::make('loan.loan_code')
                    ->label('Loan Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loan.borrower.first_name')
                    ->label('Client')
                    ->formatStateUsing(fn ($record) => $record->loan->borrower->first_name . ' ' . $record->loan->borrower->last_name ?? '-')
                    ->searchable(['first_name', 'last_name']),
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
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoanModifications::route('/'),
        ];
    }
}
