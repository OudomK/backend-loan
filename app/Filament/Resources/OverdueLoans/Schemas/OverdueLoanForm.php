<?php

namespace App\Filament\Resources\OverdueLoans\Schemas;

use Filament\Schemas\Schema;

class OverdueLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }
}
