<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdmissionIndicatorsExport;
use App\Exports\AdmissionIndicatorTemplate;
use App\Http\Controllers\Controller;
use App\Imports\AdmissionIndicatorImport;
use App\Models\AdmissionIndicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Qabul ko'rsatkichlari — oldingi yillardagi qabul statistikasini
 * yuritish (ko'rish, qo'lda qo'shish/tahrirlash, Excel import/export).
 */
class AdmissionIndicatorController extends Controller
{
    private const IMPORT_SESSION_KEY = 'admission_indicators_excel_import';
    private const IMPORT_CHUNK_SIZE = 100;

    /**
     * Excel ustunlari tartibi bo'yicha admission_indicators field mapping.
     */
    private const IMPORT_COLUMN_MAP = [
        0 => 't_r',
        1 => 'jshshir_kod',
        2 => 'student_id',
        3 => 'full_name',
        4 => 'farmoyish',
        5 => 'buyruq',
        6 => 'fuqarolik',
        7 => 'davlat',
        8 => 'millat',
        9 => 'viloyat',
        10 => 'tuman',
        11 => 'jinsi',
        12 => 'tugilgan_sana',
        13 => 'passport_raqami',
        14 => 'jshshir_kod_2',
        15 => 'kurs',
        16 => 'fakultet',
        17 => 'talim_tili',
        18 => 'oquv_yili',
        19 => 'mutaxassislik',
        20 => 'talim_turi',
        21 => 'talim_shakli',
        22 => 'tolov_shakli',
        23 => 'talaba_toifasi',
        24 => 'imtiyoz_toifasi',
        25 => 'toplagan_bali',
        26 => 'otish_bali',
        27 => 'tolov_kontrakt_barobari_bazaviy',
        28 => 'tolov_kontrakt_shartnoma_summasi',
        29 => 'kvota',
    ];

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
                    ->orWhere('full_name', 'like', "%{$s}%")
                    ->orWhere('student_id', 'like', "%{$s}%")
                    ->orWhere('jshshir_kod', 'like', "%{$s}%")
                    ->orWhere('passport_raqami', 'like', "%{$s}%")
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

        $years = AdmissionIndicator::query()
            ->select('qabul_yili')
            ->distinct()
            ->orderByDesc('qabul_yili')
            ->pluck('qabul_yili');

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
        $action = $request->input('action');

        if ($action === 'upload') {
            return $this->uploadImportFile($request);
        }

        if ($action === 'process') {
            return $this->processImportChunk($request);
        }

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
                ->with('error', 'Import xatosi: ' . $e->getMessage());
        }

        $message = "{$import->imported} ta qator import qilindi.";
        if (!empty($import->errors)) {
            $details = collect($import->errors)
                ->take(10)
                ->map(fn ($e) => "{$e['row']}-qator: {$e['error']}")
                ->implode('; ');
            $message .= ' ' . count($import->errors) . ' ta qatorda xatolik: ' . $details;
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

    private function uploadImportFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ], [
            'file.required' => 'Excel fayl tanlanmadi.',
            'file.mimes' => 'Faqat .xlsx yoki .xls fayl yuklash mumkin.',
        ]);

        $this->clearImportState();

        $path = $request->file('file')->store('admission-indicators-imports');
        $rows = $this->extractExcelDataRows(Storage::disk('local')->path($path));

        if (count($rows) === 0) {
            Storage::disk('local')->delete($path);
            return response()->json([
                'success' => false,
                'message' => 'Excel faylda import qilinadigan ma\'lumot topilmadi.',
            ], 422);
        }

        $headers = $this->readExcelHeader(Storage::disk('local')->path($path));
        if (count($headers) < count(self::IMPORT_COLUMN_MAP)) {
            Storage::disk('local')->delete($path);
            return response()->json([
                'success' => false,
                'message' => 'Excel ustunlari yetarli emas. Kutilgan ustunlar soni: ' . count(self::IMPORT_COLUMN_MAP),
            ], 422);
        }

        session([
            self::IMPORT_SESSION_KEY => [
                'path' => $path,
                'original_name' => $request->file('file')->getClientOriginalName(),
                'processed_rows' => 0,
                'total_rows' => count($rows),
                'imported_rows' => 0,
                'errors' => [],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fayl yuklandi. Endi ma\'lumotlarni ko\'chirishni boshlashingiz mumkin.',
            'total_rows' => count($rows),
            'file_name' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    private function processImportChunk(Request $request): JsonResponse
    {
        $state = session(self::IMPORT_SESSION_KEY);

        if (!$state || empty($state['path']) || !Storage::disk('local')->exists($state['path'])) {
            return response()->json([
                'success' => false,
                'message' => 'Avval Excel faylni yuklang.',
            ], 422);
        }

        $rows = $this->extractExcelDataRows(Storage::disk('local')->path($state['path']));
        $chunk = array_slice($rows, $state['processed_rows'], self::IMPORT_CHUNK_SIZE);

        foreach ($chunk as $rowMeta) {
            try {
                $payload = $this->mapExcelRowToPayload($rowMeta['row']);
                $payload['created_by'] = auth()->id();
                $payload['updated_by'] = auth()->id();
                AdmissionIndicator::create($payload);
                $state['imported_rows']++;
            } catch (\Throwable $e) {
                $state['errors'][] = [
                    'row' => $rowMeta['row_number'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        $state['processed_rows'] += count($chunk);
        $state['total_rows'] = count($rows);
        session([self::IMPORT_SESSION_KEY => $state]);

        $percent = $state['total_rows'] > 0
            ? (int) floor(($state['processed_rows'] / $state['total_rows']) * 100)
            : 100;
        $finished = $state['processed_rows'] >= $state['total_rows'];

        $response = [
            'success' => true,
            'finished' => $finished,
            'processed_rows' => $state['processed_rows'],
            'total_rows' => $state['total_rows'],
            'imported_rows' => $state['imported_rows'],
            'errors_count' => count($state['errors']),
            'errors_preview' => array_slice($state['errors'], -5),
            'percent' => min(100, $percent),
            'message' => $finished
                ? 'Import yakunlandi.'
                : 'Ma\'lumotlar admission_indicators jadvaliga ko\'chirilmoqda.',
        ];

        if ($finished) {
            $this->clearImportState();
        }

        return response()->json($response);
    }

    private function readExcelHeader(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();

        return array_values($sheet->rangeToArray(
            'A1:' . $sheet->getHighestColumn() . '1',
            null,
            true,
            true,
            false
        )[0] ?? []);
    }

    private function extractExcelDataRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);
        $data = [];

        foreach (array_slice($rawRows, 1, null, true) as $index => $row) {
            if ($this->isExcelRowEmpty($row)) {
                continue;
            }

            $data[] = [
                'row_number' => $index + 1,
                'row' => array_values($row),
            ];
        }

        return $data;
    }

    private function mapExcelRowToPayload(array $row): array
    {
        $payload = [];

        foreach (self::IMPORT_COLUMN_MAP as $index => $field) {
            $payload[$field] = $this->normalizeValueByField($field, $row[$index] ?? null);
        }

        $payload['qabul_yili'] = $this->parseAdmissionYear($payload['oquv_yili']);
        if ($payload['qabul_yili'] === null) {
            throw new \RuntimeException('O\'quv yilidan qabul yilini aniqlab bo\'lmadi.');
        }

        $payload['reja'] = $payload['kvota'];
        $payload['qabul_soni'] = 1;
        $payload['min_ball'] = $payload['otish_bali'];

        return $payload;
    }

    private function normalizeValueByField(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        return match ($field) {
            't_r', 'student_id', 'kurs', 'kvota' => $this->intOrNull($value),
            'toplagan_bali', 'otish_bali', 'tolov_kontrakt_barobari_bazaviy', 'tolov_kontrakt_shartnoma_summasi' => $this->floatOrNull($value),
            'tugilgan_sana' => $this->dateOrNull($value),
            default => is_string($value) ? $value : (string) $value,
        };
    }

    private function parseAdmissionYear(?string $oquvYili): ?int
    {
        if (!$oquvYili) {
            return null;
        }

        if (preg_match('/(19|20)\d{2}/', $oquvYili, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function isExcelRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) round((float) str_replace([' ', ','], ['', '.'], (string) $value));
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) str_replace([' ', ','], ['', '.'], (string) $value);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $stringValue = trim((string) $value);
        if ($value === null || $stringValue === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime($stringValue);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function clearImportState(): void
    {
        $state = session(self::IMPORT_SESSION_KEY);

        if ($state && !empty($state['path']) && Storage::disk('local')->exists($state['path'])) {
            Storage::disk('local')->delete($state['path']);
        }

        session()->forget(self::IMPORT_SESSION_KEY);
    }
}
