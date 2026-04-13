<?php

namespace App\Filament\Resources\CoBorrowers;

use App\Filament\Resources\CoBorrowers\Pages\CreateCoBorrower;
use App\Filament\Resources\CoBorrowers\Pages\EditCoBorrower;
use App\Filament\Resources\CoBorrowers\Pages\ListCoBorrowers;
use App\Filament\Resources\CoBorrowers\Schemas\CoBorrowerForm;
use App\Filament\Resources\CoBorrowers\Tables\CoBorrowersTable;
use App\Models\CoBorrower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class CoBorrowerResource extends Resource
{
    protected static ?string $model = CoBorrower::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone', 'customer_code', 'id_number'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "{$record->last_name} {$record->first_name} ({$record->customer_code})";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Phone' => $record->phone,
            'ID' => "{$record->id_type}: {$record->id_number}",
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return CoBorrowerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoBorrowersTable::configure($table);
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
            'index' => ListCoBorrowers::route('/'),
            'create' => CreateCoBorrower::route('/create'),
            'edit' => EditCoBorrower::route('/{record}/edit'),
        ];
    }

    public static function nextCoBorrowerCode(): string
    {
        $latest = CoBorrower::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;

        return 'CB-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeCoBorrowerData(array $data, ?CoBorrower $record = null): array
    {
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $record?->customer_code ?? static::nextCoBorrowerCode();
        }

        $status = (string) ($data['status'] ?? $record?->status ?? 'Active');
        if (!in_array($status, ['Active', 'Inactive', 'Blacklisted'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid co-borrower status.',
            ]);
        }

        $data['status'] = $status;

        return $data;
    }
}

