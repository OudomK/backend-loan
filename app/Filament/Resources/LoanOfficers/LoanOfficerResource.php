<?php

namespace App\Filament\Resources\LoanOfficers;

use App\Filament\Resources\LoanOfficers\Pages\CreateLoanOfficer;
use App\Filament\Resources\LoanOfficers\Pages\EditLoanOfficer;
use App\Filament\Resources\LoanOfficers\Pages\ListLoanOfficers;
use App\Filament\Resources\LoanOfficers\Schemas\LoanOfficerForm;
use App\Filament\Resources\LoanOfficers\Tables\LoanOfficersTable;
use App\Models\Employee;
use App\Models\LoanOfficer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LoanOfficerResource extends Resource
{
    protected static ?string $model = LoanOfficer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Client Management';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'employee.name', 'employee.employee_code'];
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
            ->with(['employee'])
            ->withCount([
                'loans as active_loans_count' => fn(Builder $query) => $query->where('status', 'active'),
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
            'index' => ListLoanOfficers::route('/'),
            'create' => CreateLoanOfficer::route('/create'),
            'edit' => EditLoanOfficer::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeLoanOfficerData(array $data, ?LoanOfficer $record = null): array
    {
        $status = strtolower((string) ($data['status'] ?? $record?->status ?? 'active'));
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid loan officer status.',
            ]);
        }

        $employee = null;
        if (filled($data['employee_id'] ?? null)) {
            $employee = Employee::find($data['employee_id']);

            if (!$employee) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Selected employee was not found.',
                ]);
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' && $employee) {
            $name = trim((string) $employee->name);
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Officer name is required.',
            ]);
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '' && $employee && filled($employee->phone)) {
            $phone = trim((string) $employee->phone);
        }

        $gender = $data['gender'] ?? $record?->gender;
        if (blank($gender) && $employee && filled($employee->gender)) {
            $gender = $employee->gender;
        }

        $startDate = $data['start_date'] ?? $record?->start_date;
        if (blank($startDate) && $employee && filled($employee->date_joined)) {
            $startDate = $employee->date_joined;
        }

        return array_merge($data, [
            'employee_id' => filled($data['employee_id'] ?? null) ? (int) $data['employee_id'] : null,
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'status' => $status,
            'gender' => filled($gender) ? (string) $gender : null,
            'start_date' => filled($startDate) ? $startDate : null,
            'max_loan_amount' => filled($data['max_loan_amount'] ?? null)
                ? round((float) $data['max_loan_amount'], 2)
                : null,
        ]);
    }
}

