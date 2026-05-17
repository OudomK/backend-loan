<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Filament\Resources\Collaterals\Schemas\CollateralForm;
use App\Filament\Resources\Collaterals\Tables\CollateralsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CollateralsRelationManager extends RelationManager
{
    protected static string $relationship = 'collaterals';

    protected static ?string $recordTitleAttribute = 'certificate_number';

    public function form(Schema $schema): Schema
    {
        return CollateralForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return CollateralsTable::configure($table)
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
            ]);
    }
}
