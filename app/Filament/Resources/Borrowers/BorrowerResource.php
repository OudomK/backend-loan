<?php

namespace App\Filament\Resources\Borrowers;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\Borrowers\Pages\CreateBorrower;
use App\Filament\Resources\Borrowers\Pages\EditBorrower;
use App\Filament\Resources\Borrowers\Pages\ListBorrowers;
use App\Filament\Resources\Borrowers\Schemas\BorrowerForm;
use App\Filament\Resources\Borrowers\Tables\BorrowersTable;
use App\Models\Borrower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class BorrowerResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'borrowers';
    protected static ?string $model = Borrower::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'phone', 'customer_code', 'id_number'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "{$record->first_name} {$record->last_name} ({$record->customer_code})";
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
        return BorrowerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BorrowersTable::configure($table);
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
            'index' => ListBorrowers::route('/'),
            'create' => CreateBorrower::route('/create'),
            'edit' => EditBorrower::route('/{record}/edit'),
        ];
    }

    public static function nextBorrowerCode(): string
    {
        $latest = Borrower::withoutGlobalScopes()->orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;

        return 'QF-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeBorrowerData(array $data, ?Borrower $record = null): array
    {
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $record?->customer_code ?? static::nextBorrowerCode();
        }

        $status = (string) ($data['status'] ?? $record?->status ?? 'Active');
        if (!in_array($status, ['Active', 'Inactive', 'Blacklisted'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid borrower status.',
            ]);
        }

        $data['status'] = $status;
        $data['customer_type'] = 'Borrower';

        return $data;
    }
}

