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
        $allFaculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // "Xalqaro" fakultetlarni birlashtirish - faqat "Xalqaro ta'lim fakulteti" qoladi
        $xalqaroMain = $allFaculties->first(fn($f) => stripos($f->name, 'xalqaro') !== false && stripos($f->name, 'davolash') === false);
        $xalqaroDuplicates = $allFaculties->filter(fn($f) => stripos($f->name, 'xalqaro') !== false && $f->id !== ($xalqaroMain->id ?? 0));

        // Asosiy fakultetlar ro'yxati (dublikat xalqaro fakultetlarsiz)
        $faculties = $allFaculties->reject(fn($f) => $xalqaroDuplicates->contains('id', $f->id));

        // Barcha fakultetlarning HEMIS ID lari (dublikatlar ham)
        $allFacultyHemisIds = $allFaculties->pluck('department_hemis_id')->map(fn($id) => (int) $id)->toArray();

        // Dublikat xalqaro fakultetlarning HEMIS ID lari
        $xalqaroDupHemisIds = $xalqaroDuplicates->pluck('department_hemis_id')->map(fn($id) => (int) $id)->toArray();
        $xalqaroMainHemisId = $xalqaroMain ? (int) $xalqaroMain->department_hemis_id : 0;

        // Barcha kafedralarni BITTA so'rov bilan olish
        $allKafedras = Department::where('active', true)
            ->where('name', 'LIKE', '%kafedra%')
            ->where('structure_type_code', '!=', 11)
            ->orderBy('name')
            ->get();

        // Dublikat xalqaro fakultet kafedralari uchun parent_id ni asosiyga yo'naltirish
        $allKafedras->each(function ($k) use ($xalqaroDupHemisIds, $xalqaroMainHemisId) {
            if (in_array((int) $k->parent_id, $xalqaroDupHemisIds)) {
                $k->parent_id = $xalqaroMainHemisId;
            }
        });

        // PHP da ajratish - har bir kafedra faqat BITTA joyda ko'rinadi
        $displayHemisIds = $faculties->pluck('department_hemis_id')->map(fn($id) => (int) $id)->toArray();

        $kafedras = $allKafedras
            ->filter(fn($k) => in_array((int) $k->parent_id, $displayHemisIds))
            ->groupBy('parent_id');

        $unassigned = $allKafedras
            ->filter(fn($k) => !in_array((int) $k->parent_id, $displayHemisIds));

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
