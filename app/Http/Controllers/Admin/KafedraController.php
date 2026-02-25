<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class KafedraController extends Controller
{
    public function index()
    {
        // Fakultetlar (structure_type_code = 11)
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Har bir fakultetning kafedralari (parent_id orqali bog'langan)
        $facultyIds = $faculties->pluck('id')->toArray();

        $kafedras = Department::whereIn('parent_id', $facultyIds)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        // Parent_id bo'lmagan yoki noto'g'ri kafedralari (tayinlanmagan)
        $unassigned = Department::where('structure_type_code', '!=', 11)
            ->where('active', true)
            ->where(function ($q) use ($facultyIds) {
                $q->whereNull('parent_id')
                  ->orWhereNotIn('parent_id', $facultyIds);
            })
            ->orderBy('name')
            ->get();

        return view('admin.kafedra.index', compact('faculties', 'kafedras', 'unassigned'));
    }

    /**
     * Kafedrani boshqa fakultetga o'tkazish
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'kafedra_id' => 'required|integer|exists:departments,id',
            'faculty_id' => 'required|integer|exists:departments,id',
        ]);

        $kafedra = Department::findOrFail($request->kafedra_id);
        $faculty = Department::where('id', $request->faculty_id)
            ->where('structure_type_code', 11)
            ->where('active', true)
            ->firstOrFail();

        $kafedra->parent_id = $faculty->id;
        $kafedra->save();

        return response()->json([
            'success' => true,
            'message' => "«{$kafedra->name}» kafedrasi «{$faculty->name}» fakultetiga muvaffaqiyatli o'tkazildi.",
        ]);
    }
}
