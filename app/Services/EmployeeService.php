<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LoanOfficer;
use App\Models\Position;

class EmployeeService
{
    public function syncWithLoanOfficer(Employee $employee)
    {
        $loanOfficerPosition = Position::where('name', 'Loan Officer')->first();

        if ($employee->position_id == $loanOfficerPosition?->id) {
            LoanOfficer::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'name' => $employee->name,
                    'phone' => $employee->phone,
                    'status' => $employee->status === 'active' ? 'active' : 'inactive'
                ]
            );
        } else {
            // If position changed from Loan Officer to something else, remove from loan_officers
            LoanOfficer::where('employee_id', $employee->id)
                ->get()
                ->each(function (LoanOfficer $loanOfficer) use ($employee): void {
                    activity('employees')
                        ->performedOn($employee)
                        ->withProperties([
                            'loan_officer_id' => $loanOfficer->id,
                            'reason' => 'position_changed',
                        ])
                        ->log('Removed linked loan officer record');

                    $loanOfficer->delete();
                });
        }
    }

    public function handleDeletion(Employee $employee)
    {
        LoanOfficer::where('employee_id', $employee->id)
            ->get()
            ->each(function (LoanOfficer $loanOfficer) use ($employee): void {
                activity('employees')
                    ->performedOn($employee)
                    ->withProperties([
                        'loan_officer_id' => $loanOfficer->id,
                        'reason' => 'employee_deleted',
                    ])
                    ->log('Removed linked loan officer record');

                $loanOfficer->delete();
            });
    }
}
