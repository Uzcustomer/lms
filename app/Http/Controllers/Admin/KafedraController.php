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

        // Ko'rsatiladigan fakultetlar (dublikatlarsiz)
        $faculties = $allFaculties->reject(fn($f) => $xalqaroDuplicates->contains('id', $f->id));

        // parent_id -> ko'rsatiladigan faculty hemis_id mapping
        // Dublikat xalqaro faculty hemis_id larini asosiy xalqaro ga yo'naltirish
        $parentMap = [];
        foreach ($faculties as $f) {
            $parentMap[strval($f->department_hemis_id)] = strval($f->department_hemis_id);
        }
        if ($xalqaroMain) {
            foreach ($xalqaroDuplicates as $dup) {
                $parentMap[strval($dup->department_hemis_id)] = strval($xalqaroMain->department_hemis_id);
            }
        }

        // Barcha kafedralarni BITTA so'rov bilan olish
        $allKafedras = Department::where('active', true)
            ->where('name', 'LIKE', '%kafedra%')
            ->where('structure_type_code', '!=', 11)
            ->orderBy('name')
            ->get();

        // Kafedralarni fakultetlarga ajratish
        $assigned = collect();
        $unassigned = collect();

        foreach ($allKafedras as $k) {
            $pid = strval($k->parent_id);
            if (isset($parentMap[$pid])) {
                // Dublikat xalqaro → asosiy xalqaro ga yo'naltirish
                $k->setAttribute('display_parent_id', $parentMap[$pid]);
                $assigned->push($k);
            } else {
                $unassigned->push($k);
            }
        }

        $kafedras = $assigned->groupBy('display_parent_id');

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
