<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdmissionIndicatorsExport;
use App\Exports\AdmissionIndicatorTemplate;
use App\Http\Controllers\Controller;
use App\Imports\AdmissionIndicatorImport;
use App\Models\AdmissionIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Qabul ko'rsatkichlari — oldingi yillardagi qabul statistikasini
 * yuritish (ko'rish, qo'lda qo'shish/tahrirlash, Excel import/export).
 */
class AdmissionIndicatorController extends Controller
{
    /**
     * Filtrlangan so'rov + tanlangan filtrlar uchun yordamchi.
     */
    private function filteredQuery(Request $request)
    {
        $query = AdmissionIndicator::query();

        if ($request->filled('qabul_yili')) {
            $query->where('qabul_yili', (int) $request->qabul_yili);
        }
        if ($request->filled('talim_turi')) {
            $query->where('talim_turi', $request->talim_turi);
        }
        if ($request->filled('talim_shakli')) {
            $query->where('talim_shakli', $request->talim_shakli);
        }
        if ($request->filled('tolov_shakli')) {
            $query->where('tolov_shakli', $request->tolov_shakli);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('mutaxassislik', 'like', "%{$s}%")
                  ->orWhere('mutaxassislik_kodi', 'like', "%{$s}%")
                  ->orWhere('izoh', 'like', "%{$s}%");
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        if (!Schema::hasTable('admission_indicators')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        $indicators = $this->filteredQuery($request)
            ->orderByDesc('qabul_yili')
            ->orderBy('talim_turi')
            ->orderBy('talim_shakli')
            ->orderBy('mutaxassislik')
            ->paginate(50)
            ->withQueryString();

        // Filtr uchun mavjud yillar ro'yxati
        $years = AdmissionIndicator::query()
            ->select('qabul_yili')
            ->distinct()
            ->orderByDesc('qabul_yili')
            ->pluck('qabul_yili');

        // Yig'ma ko'rsatkichlar (joriy filtrga mos). filteredQuery() har
        // safar yangi builder qaytaradi, shuning uchun alohida chaqiramiz.
        $summary = [
            'jami_reja' => $this->filteredQuery($request)->sum('reja'),
            'jami_qabul' => $this->filteredQuery($request)->sum('qabul_soni'),
            'qatorlar' => $this->filteredQuery($request)->count(),
        ];

        return view('admin.admission-indicators.index', [
            'indicators' => $indicators,
            'years' => $years,
            'summary' => $summary,
            'talimTurlari' => AdmissionIndicator::TALIM_TURLARI,
            'talimShakllari' => AdmissionIndicator::TALIM_SHAKLLARI,
            'tolovShakllari' => AdmissionIndicator::TOLOV_SHAKLLARI,
        ]);
    }

    public function create()
    {
        return view('admin.admission-indicators.create', [
            'talimTurlari' => AdmissionIndicator::TALIM_TURLARI,
            'talimShakllari' => AdmissionIndicator::TALIM_SHAKLLARI,
            'tolovShakllari' => AdmissionIndicator::TOLOV_SHAKLLARI,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        AdmissionIndicator::create($data);

        return redirect()->route('admin.admission-indicators.index')
            ->with('success', "Qabul ko'rsatkichi qo'shildi.");
    }

    public function edit(AdmissionIndicator $admission_indicator)
    {
        return view('admin.admission-indicators.edit', [
            'indicator' => $admission_indicator,
            'talimTurlari' => AdmissionIndicator::TALIM_TURLARI,
            'talimShakllari' => AdmissionIndicator::TALIM_SHAKLLARI,
            'tolovShakllari' => AdmissionIndicator::TOLOV_SHAKLLARI,
        ]);
    }

    public function update(Request $request, AdmissionIndicator $admission_indicator)
    {
        $data = $this->validateData($request);
        $data['updated_by'] = auth()->id();

        $admission_indicator->update($data);

        return redirect()->route('admin.admission-indicators.index')
            ->with('success', "Qabul ko'rsatkichi yangilandi.");
    }

    public function destroy(AdmissionIndicator $admission_indicator)
    {
        $admission_indicator->delete();

        return redirect()->route('admin.admission-indicators.index')
            ->with('success', "Qabul ko'rsatkichi o'chirildi.");
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ], [
            'file.required' => 'Excel fayl tanlanmadi.',
            'file.mimes' => 'Faqat .xlsx yoki .xls fayl yuklash mumkin.',
        ]);

        $import = new AdmissionIndicatorImport(auth()->id());

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.admission-indicators.index')
                ->with('error', "Import xatosi: " . $e->getMessage());
        }

        $message = "{$import->imported} ta qator import qilindi.";
        if (!empty($import->errors)) {
            $details = collect($import->errors)
                ->take(10)
                ->map(fn ($e) => "{$e['row']}-qator: {$e['error']}")
                ->implode('; ');
            $message .= " " . count($import->errors) . " ta qatorda xatolik: " . $details;
            return redirect()->route('admin.admission-indicators.index')
                ->with('warning', $message);
        }

        return redirect()->route('admin.admission-indicators.index')
            ->with('success', $message);
    }

    public function export(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->orderByDesc('qabul_yili')
            ->orderBy('talim_turi')
            ->orderBy('talim_shakli')
            ->orderBy('mutaxassislik')
            ->get();

        $fileName = 'qabul-korsatkichlari-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new AdmissionIndicatorsExport($rows), $fileName);
    }

    public function template()
    {
        return Excel::download(new AdmissionIndicatorTemplate(), 'qabul-korsatkichlari-shablon.xlsx');
    }

    /**
     * Qo'lda qo'shish/tahrirlash uchun validatsiya.
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'qabul_yili' => 'required|integer|min:1900|max:2100',
            'talim_turi' => 'nullable|string|max:60',
            'talim_shakli' => 'nullable|string|max:60',
            'mutaxassislik' => 'nullable|string|max:255',
            'mutaxassislik_kodi' => 'nullable|string|max:30',
            'tolov_shakli' => 'nullable|string|max:60',
            'reja' => 'nullable|integer|min:0',
            'qabul_soni' => 'nullable|integer|min:0',
            'min_ball' => 'nullable|numeric|min:0|max:1000',
            'izoh' => 'nullable|string|max:2000',
        ], [
            'qabul_yili.required' => 'Qabul yili kiritilishi shart.',
            'qabul_yili.integer' => "Qabul yili butun son bo'lishi kerak.",
        ]);
    }
}
