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
        $facultyHemisIds = $faculties->pluck('department_hemis_id')->map(fn($id) => (int) $id)->toArray();

        // Barcha kafedralarni BITTA so'rov bilan olish
        $allKafedras = Department::where('active', true)
            ->where('name', 'LIKE', '%kafedra%')
            ->where('structure_type_code', '!=', 11)
            ->orderBy('name')
            ->get();

        // PHP da ajratish - har bir kafedra faqat BITTA joyda ko'rinadi
        $kafedras = $allKafedras
            ->filter(fn($k) => in_array((int) $k->parent_id, $facultyHemisIds))
            ->groupBy('parent_id');

        $unassigned = $allKafedras
            ->filter(fn($k) => !in_array((int) $k->parent_id, $facultyHemisIds));

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

        $kafedra->parent_id = $faculty->department_hemis_id;
        $kafedra->save();

        return response()->json([
            'success' => true,
            'message' => "«{$kafedra->name}» kafedrasi «{$faculty->name}» fakultetiga muvaffaqiyatli o'tkazildi.",
        ]);
    }
}
