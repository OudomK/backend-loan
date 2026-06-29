<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Support\CurrencyHelper;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    protected EmployeeService $employeeService;

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
        $data = $request->validated();
        $data['currency'] = CurrencyHelper::normalize($data['currency'] ?? CurrencyHelper::USD);

        if (empty($data['employee_code'])) {
            $latest = Employee::orderBy('id', 'desc')->first();
            $nextId = $latest ? $latest->id + 1 : 1;
            $data['employee_code'] = 'EMP-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        }

        $employee = Employee::create($data);

        $this->employeeService->syncWithLoanOfficer($employee);

        return response()->json(
            (new EmployeeResource($employee->load('position')))->resolve(),
            201
        );
    }

    public function show(Employee $employee)
    {
        return (new EmployeeResource($employee->load('position')))->resolve();
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();
        if (array_key_exists('currency', $data)) {
            $data['currency'] = CurrencyHelper::normalize($data['currency']);
        }

        $employee->update($data);

        $this->employeeService->syncWithLoanOfficer($employee);

        return (new EmployeeResource($employee->load('position')))->resolve();
    }

    public function destroy(Employee $employee)
    {
        $this->employeeService->handleDeletion($employee);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // Max 5MB
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employees', 'public');
            $url = asset('storage/' . $path);
            return response()->json(['url' => $url]);
        }

        return response()->json(['message' => 'Upload failed'], 400);
    }

    public function getNextCode()
    {
        $latest = Employee::orderBy('id', 'desc')->first();
        $nextId = $latest ? $latest->id + 1 : 1;
        $code = 'EMP-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        
        return response()->json(['code' => $code]);
    }
}
