<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\Loans\Pages\CreateLoan;
use App\Filament\Resources\Loans\Pages\EditLoan;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Schemas\LoanForm;
use App\Filament\Resources\Loans\Tables\LoansTable;
use App\Models\Loan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class LoanResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'loans';
    protected static ?string $model = Loan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'loan_code';

    public static function getGloballySearchableAttributes(): array
    {
        return ['loan_code', 'borrower.first_name', 'borrower.last_name', 'borrower.customer_code'];
    }

    public static function form(Schema $schema): Schema
    {
        return LoanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoansTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ])
            ->with(['borrower', 'officer']);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Loans\RelationManagers\ModificationsRelationManager::class,
            \App\Filament\Resources\Loans\RelationManagers\ApprovalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoans::route('/'),
            'create' => CreateLoan::route('/create'),
            'edit' => EditLoan::route('/{record}/edit'),
        ];
    }
}

