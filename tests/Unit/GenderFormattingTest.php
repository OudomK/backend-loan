<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\LoanOfficer;
use PHPUnit\Framework\TestCase;

class GenderFormattingTest extends TestCase
{
    public function test_employee_gender_is_exposed_as_an_initial_for_display(): void
    {
        $employee = new Employee(['gender' => 'Female']);

        $this->assertSame('F', $employee->formatted_gender);
        $this->assertSame('F', $employee->toArray()['formatted_gender']);
        $this->assertSame('Female', $employee->gender);
    }

    public function test_loan_officer_gender_is_exposed_as_an_initial_for_display(): void
    {
        $officer = new LoanOfficer(['gender' => 'Male']);

        $this->assertSame('M', $officer->formatted_gender);
        $this->assertSame('M', $officer->toArray()['formatted_gender']);
        $this->assertSame('Male', $officer->gender);
    }

    public function test_empty_gender_has_an_empty_display_value(): void
    {
        $this->assertSame('', (new Employee)->formatted_gender);
        $this->assertSame('', (new LoanOfficer)->formatted_gender);
    }
}
