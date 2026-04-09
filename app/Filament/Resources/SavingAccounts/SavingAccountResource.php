<?php

namespace App\Filament\Resources\SavingAccounts;

use App\Filament\Resources\SavingAccounts\Pages\CreateSavingAccount;
use App\Filament\Resources\SavingAccounts\Pages\EditSavingAccount;
use App\Filament\Resources\SavingAccounts\Pages\ListSavingAccounts;
use App\Filament\Resources\SavingAccounts\Schemas\SavingAccountForm;
use App\Filament\Resources\SavingAccounts\Tables\SavingAccountsTable;
use App\Models\Borrowing;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class SavingAccountResource extends Resource
{
    protected static ?string $model = Borrowing::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'account_no';
    protected static ?string $navigationLabel = 'Borrowings';
    protected static ?string $modelLabel = 'Borrowing';
    protected static ?string $pluralModelLabel = 'Borrowings';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'account_no',
            'transaction_no',
            'loan_account',
            'contract_no',
            'lender.lender_code',
            'lender.name',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return SavingAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavingAccountsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['lender'])
            ->withSum('repayments as principal_paid_total', 'principal_paid');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavingAccounts::route('/'),
            'create' => CreateSavingAccount::route('/create'),
            'edit' => EditSavingAccount::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeBorrowingData(array $data, ?Borrowing $record = null): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than 0.',
            ]);
        }

        $borrowingDate = filled($data['borrowing_date'] ?? null)
            ? Carbon::parse((string) $data['borrowing_date'])
            : null;
        $termMonths = max(0, (int) ($data['term_months'] ?? 0));

        if ($borrowingDate && blank($data['first_pay_date'] ?? null)) {
            $data['first_pay_date'] = $borrowingDate->copy()->addMonth()->toDateString();
        }

        if ($borrowingDate && $termMonths > 0 && blank($data['maturity_date'] ?? null)) {
            $data['maturity_date'] = $borrowingDate->copy()->addMonthsNoOverflow($termMonths)->toDateString();
        }

        $principalPaidTotal = $record?->repayments()->sum('principal_paid') ?? 0.0;

        if ($record && $amount + 0.001 < $principalPaidTotal) {
            throw ValidationException::withMessages([
                'amount' => 'Loan amount cannot be less than the principal already repaid.',
            ]);
        }

        $data['amount'] = $amount;
        $data['fee'] = round((float) ($data['fee'] ?? 0), 2);
        $data['interest_rate'] = round((float) ($data['interest_rate'] ?? 0), 2);
        $data['late_principal'] = round((float) ($data['late_principal'] ?? 0), 2);
        $data['loan_interest'] = round((float) ($data['loan_interest'] ?? 0), 2);
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'USD'));
        $data['category'] = (string) ($data['category'] ?? $record?->category ?? 'Loan Capital');
        $data['status'] = $record && $principalPaidTotal + 0.001 >= $amount ? 'completed' : 'active';

        return $data;
    }
}

