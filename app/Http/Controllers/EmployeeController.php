<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index()
    {
        $employees = Employee::with('position')->get();
        // resolve() returns the underlying array, removing the 'data' wrapper
        return EmployeeResource::collection($employees)->resolve();
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = Employee::create($request->validated());

        $this->employeeService->syncWithLoanOfficer($employee);

        return (new EmployeeResource($employee->load('position')))->resolve();
    }

    public function show(Employee $employee)
    {
        return (new EmployeeResource($employee->load('position')))->resolve();
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        $this->employeeService->syncWithLoanOfficer($employee);

        return (new EmployeeResource($employee->load('position')))->resolve();
    }

    public function destroy(Employee $employee)
    {
        $this->employeeService->handleDeletion($employee);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }
}
