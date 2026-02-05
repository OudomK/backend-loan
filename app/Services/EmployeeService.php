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
                ['name' => $employee->name],
                [
                    'phone' => $employee->phone,
                    'status' => $employee->status === 'active' ? 'active' : 'inactive'
                ]
            );
        } else {
            // If position changed from Loan Officer to something else, remove from loan_officers
            LoanOfficer::where('name', $employee->name)->delete();
        }
    }

    public function handleDeletion(Employee $employee)
    {
        LoanOfficer::where('name', $employee->name)->delete();
    }
}
