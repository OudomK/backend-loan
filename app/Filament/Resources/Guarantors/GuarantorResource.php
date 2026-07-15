<?php

namespace App\Filament\Resources\Guarantors;

use App\Filament\Concerns\ChecksFeatureToggle;
use App\Filament\Resources\Guarantors\Pages\CreateGuarantor;
use App\Filament\Resources\Guarantors\Pages\EditGuarantor;
use App\Filament\Resources\Guarantors\Pages\ListGuarantors;
use App\Filament\Resources\Guarantors\Schemas\GuarantorForm;
use App\Filament\Resources\Guarantors\Tables\GuarantorsTable;
use App\Models\Guarantor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class GuarantorResource extends Resource
{
    use ChecksFeatureToggle;

    protected static ?string $featureToggleKey = 'guarantors';
    protected static ?string $model = Guarantor::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 6;

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
        return GuarantorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuarantorsTable::configure($table);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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
            'index' => ListGuarantors::route('/'),
            'create' => CreateGuarantor::route('/create'),
            'edit' => EditGuarantor::route('/{record}/edit'),
        ];
    }

    public static function nextGuarantorCode(): string
    {
        $latest = Guarantor::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;

        return 'GU-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeGuarantorData(array $data, ?Guarantor $record = null): array
    {
        if (blank($data['customer_code'] ?? null)) {
            $data['customer_code'] = $record?->customer_code ?? static::nextGuarantorCode();
        }

        $status = (string) ($data['status'] ?? $record?->status ?? 'Active');
        if (!in_array($status, ['Active', 'Inactive', 'Blacklisted'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid guarantor status.',
            ]);
        }

        $data['status'] = $status;

        return $data;
    }
}


