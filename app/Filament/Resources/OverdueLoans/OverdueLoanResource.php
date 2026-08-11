<?php

namespace App\Filament\Resources\OverdueLoans;

use App\Filament\Concerns\ChecksFeatureToggle;
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
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'overdue_payments';
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['loan.loan_code', 'loan.borrower.first_name', 'loan.borrower.last_name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Overdue: ' . ($record->loan ? $record->loan->loan_code : $record->id);
    }
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
        $today = \Carbon\Carbon::today()->toDateString();

        return parent::getEloquentQuery()
            ->where('payment_date', '<', $today)
            ->whereRaw('total_paid < (principal_amount + interest_amount - 0.01)')
            ->whereRaw(
                'payment_date = (SELECT MIN(candidate.payment_date) FROM payments AS candidate WHERE candidate.loan_id = payments.loan_id AND candidate.payment_date < ? AND candidate.total_paid < (candidate.principal_amount + candidate.interest_amount - 0.01))',
                [$today]
            )
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
