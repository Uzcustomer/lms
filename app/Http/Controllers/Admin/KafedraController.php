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

        // Fakultetlarning HEMIS ID lari (parent_id shu qiymatga ishora qiladi)
        $facultyHemisIds = $faculties->pluck('department_hemis_id')->toArray();

        // HEMIS ID -> faculty mapping (view da groupBy uchun)
        $hemisToFaculty = $faculties->keyBy('department_hemis_id');

        // Faqat haqiqiy kafedralar - nomida "kafedra" so'zi bor bo'lganlar
        // parent_id = faculty ning department_hemis_id ga teng
        $assignedKafedras = Department::whereIn('parent_id', $facultyHemisIds)
            ->where('active', true)
            ->where('name', 'LIKE', '%kafedra%')
            ->orderBy('name')
            ->get();

        $kafedras = $assignedKafedras->groupBy('parent_id');
        $assignedIds = $assignedKafedras->pluck('id')->toArray();

        // Fakultetga tayinlanmagan kafedralar - tayinlanganlarni aniq chiqarib tashlash
        $unassigned = Department::where('active', true)
            ->where('name', 'LIKE', '%kafedra%')
            ->where('structure_type_code', '!=', 11)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        return view('admin.kafedra.index', compact('faculties', 'kafedras', 'unassigned', 'hemisToFaculty'));
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

        $kafedra->parent_id = $faculty->department_hemis_id;
        $kafedra->save();

        return response()->json([
            'success' => true,
            'message' => "«{$kafedra->name}» kafedrasi «{$faculty->name}» fakultetiga muvaffaqiyatli o'tkazildi.",
        ]);
    }
}
