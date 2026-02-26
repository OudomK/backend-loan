<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class LeaveRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Leave Request Details')
                    ->schema([
                        Select::make('employee_id')
                            ->relationship('employee', 'name')
                            ->required()
                            ->disabled(fn($context) => $context !== 'create'),
                        Select::make('leave_type')
                            ->options([
                                'Annual Leave' => 'Annual Leave',
                                'Sick Leave' => 'Sick Leave',
                                'Maternity/Paternity' => 'Maternity/Paternity',
                                'Unpaid Leave' => 'Unpaid Leave',
                                'Other' => 'Other',
                            ])
                            ->required()
                            ->disabled(fn($context) => $context !== 'create'),
                        DatePicker::make('start_date')
                            ->required()
                            ->disabled(fn($context) => $context !== 'create'),
                        DatePicker::make('end_date')
                            ->required()
                            ->disabled(fn($context) => $context !== 'create'),
                        Textarea::make('reason')
                            ->required()
                            ->columnSpanFull()
                            ->disabled(fn($context) => $context !== 'create'),
                    ])->columns(2),

                Section::make('Admin Action')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'Pending' => 'Pending',
                                'Approved' => 'Approved',
                                'Rejected' => 'Rejected',
                            ])
                            ->default('Pending')
                            ->required(),
                        Textarea::make('rejection_reason')
                            ->label('Reason for Rejection (if any)')
                            ->columnSpanFull()
                            ->visible(fn($get) => $get('status') === 'Rejected'),
                    ])->columns(1),
            ]);
    }
}
