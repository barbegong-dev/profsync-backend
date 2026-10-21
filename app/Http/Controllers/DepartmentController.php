<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(Department::withCount('users')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:255', $this->uniqueName()]]);

        $department = Department::create(['name' => $request->name]);

        return response()->json(['success' => true, 'department' => $department, 'message' => 'Department added.'], 201);
    }

    public function update(Request $request, Department $department)
    {
        $request->validate(['name' => ['required', 'string', 'max:255', $this->uniqueName()->ignore($department->id)]]);

        $department->update(['name' => $request->name]);

        return response()->json(['success' => true, 'department' => $department, 'message' => 'Department updated.']);
    }

    public function destroy(Department $department)
    {
        if ($department->users()->exists()) {
            return response()->json(['message' => 'Move or remove the users in this department before deleting it.'], 422);
        }

        $department->delete();

        return response()->json(['success' => true, 'message' => 'Department deleted.']);
    }

    private function uniqueName()
    {
        return Rule::unique('departments', 'name')->where('tenant_id', Auth::user()->tenant_id);
    }
}
