<?php

namespace App\Filament\Resources\LoanOfficers;

use App\Filament\Resources\LoanOfficers\Pages\CreateLoanOfficer;
use App\Filament\Resources\LoanOfficers\Pages\EditLoanOfficer;
use App\Filament\Resources\LoanOfficers\Pages\ListLoanOfficers;
use App\Filament\Resources\LoanOfficers\Schemas\LoanOfficerForm;
use App\Filament\Resources\LoanOfficers\Tables\LoanOfficersTable;
use App\Models\LoanOfficer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class LoanOfficerResource extends Resource
{
    protected static ?string $model = LoanOfficer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'employee.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return LoanOfficerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanOfficersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employee']);
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
            'index' => ListLoanOfficers::route('/'),
            'create' => CreateLoanOfficer::route('/create'),
            'edit' => EditLoanOfficer::route('/{record}/edit'),
        ];
    }
}
