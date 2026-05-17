<?php

namespace App\Filament\Resources\PaymentQrs;

use App\Filament\Resources\PaymentQrs\Pages\CreatePaymentQr;
use App\Filament\Resources\PaymentQrs\Pages\EditPaymentQr;
use App\Filament\Resources\PaymentQrs\Pages\ListPaymentQrs;
use App\Filament\Resources\PaymentQrs\Schemas\PaymentQrForm;
use App\Filament\Resources\PaymentQrs\Tables\PaymentQrsTable;
use App\Models\PaymentQr;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentQrResource extends Resource
{
    protected static ?string $model = PaymentQr::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;
    protected static string|\UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $navigationLabel = 'Payment QR Codes';
    protected static ?int $navigationSort = 17;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PaymentQrForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentQrsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
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
            'index' => ListPaymentQrs::route('/'),
            'create' => CreatePaymentQr::route('/create'),
            'edit' => EditPaymentQr::route('/{record}/edit'),
        ];
    }
}
