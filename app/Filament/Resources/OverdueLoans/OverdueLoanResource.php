<?php

namespace App\Filament\Resources\OverdueLoans;

use App\Filament\Resources\OverdueLoans\Pages\CreateOverdueLoan;
use App\Filament\Resources\OverdueLoans\Pages\EditOverdueLoan;
use App\Filament\Resources\OverdueLoans\Pages\ListOverdueLoans;
use App\Filament\Resources\OverdueLoans\Schemas\OverdueLoanForm;
use App\Filament\Resources\OverdueLoans\Tables\OverdueLoansTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class OverdueLoanResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static string|\UnitEnum|null $navigationGroup = 'Credit Operations';
    protected static ?string $navigationLabel = 'Overdue Payments';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'overdue-payments';

    protected static ?string $modelLabel = 'Overdue Payment';
    protected static ?string $pluralModelLabel = 'Overdue Payments';

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->count() > 0 ? 'danger' : 'gray';
    }

    protected static ?string $recordTitleAttribute = 'loan_code';

    public static function form(Schema $schema): Schema
    {
        return OverdueLoanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverdueLoansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('payment_date', '<', \Carbon\Carbon::today())
            ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
            ->whereHas('loan', function ($query) {
                $query->whereIn('status', ['active', 'arrear']);
            })
            ->orderBy('payment_date', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverdueLoans::route('/'),
        ];
    }
}
