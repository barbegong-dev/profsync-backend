<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SemesterController extends Controller
{
    public function index()
    {
        return response()->json(Semester::withCount('requirements')->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $semester = DB::transaction(function () use ($data) {
            // Only one active semester per workspace
            if (!empty($data['is_active'])) {
                Semester::where('is_active', true)->update(['is_active' => false]);
            }
            return Semester::create($data);
        });

        return response()->json(['success' => true, 'semester' => $semester, 'message' => 'Semester created.'], 201);
    }

    public function show(Semester $semester)
    {
        return response()->json($semester->load('requirements'));
    }

    public function update(Request $request, Semester $semester)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($data, $semester) {
            if (!empty($data['is_active'])) {
                Semester::where('id', '!=', $semester->id)->where('is_active', true)->update(['is_active' => false]);
            }
            $semester->update($data);
        });

        return response()->json(['success' => true, 'semester' => $semester, 'message' => 'Semester updated.']);
    }

    public function destroy(Request $request, Semester $semester)
    {
        if ($request->query('confirmation') !== 'delete') {
            return response()->json(['message' => 'Deleting a semester removes its requirements and submissions. Confirm to continue.'], 422);
        }

        $semester->delete();

        return response()->json(['success' => true, 'message' => 'Semester deleted.']);
    }
}
