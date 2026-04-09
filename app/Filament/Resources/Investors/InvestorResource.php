<?php

namespace App\Filament\Resources\Investors;

use App\Filament\Resources\Investors\Pages\CreateInvestor;
use App\Filament\Resources\Investors\Pages\EditInvestor;
use App\Filament\Resources\Investors\Pages\ListInvestors;
use App\Filament\Resources\Investors\Schemas\InvestorForm;
use App\Filament\Resources\Investors\Tables\InvestorsTable;
use App\Models\Investor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvestorResource extends Resource
{
    protected static ?string $model = Investor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Fund Management';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'customer_code', 'phone', 'id_number'];
    }

    public static function form(Schema $schema): Schema
    {
        return InvestorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvestorsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \Illuminate\Database\Eloquent\SoftDeletingScope::class,
            ]);
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
            'index' => ListInvestors::route('/'),
            'create' => CreateInvestor::route('/create'),
            'edit' => EditInvestor::route('/{record}/edit'),
        ];
    }

    public static function nextInvestorCode(): string
    {
        $lastCode = Investor::orderBy('id', 'desc')->first();
        $nextNumber = $lastCode ? intval(substr((string) $lastCode->customer_code, 3)) + 1 : 1;

        return 'INV' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeInvestorData(array $data, ?Investor $record = null): array
    {
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $record?->customer_code ?? static::nextInvestorCode();
        }

        $data['status'] = filled($data['status'] ?? null)
            ? (string) $data['status']
            : (string) ($record?->status ?? 'Active');
        $data['customer_type'] = 'Investor';

        return $data;
    }
}

