<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdmissionIndicatorsExport;
use App\Exports\AdmissionIndicatorTemplate;
use App\Http\Controllers\Controller;
use App\Imports\AdmissionIndicatorImport;
use App\Models\AdmissionIndicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class AdmissionIndicatorChunkReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $startRow,
        private readonly int $endRow
    ) {
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row === 1 || ($row >= $this->startRow && $row <= $this->endRow);
    }
}

/**
 * Qabul ko'rsatkichlari — oldingi yillardagi qabul statistikasini
 * yuritish (ko'rish, qo'lda qo'shish/tahrirlash, Excel import/export).
 */
class AdmissionIndicatorController extends Controller
{
    private const IMPORT_SESSION_KEY = 'admission_indicators_excel_import';
    private const IMPORT_CHUNK_SIZE = 100;

    /**
     * Default index-based mapping kept as fallback.
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
            $this->applyNormalizedExactFilter($query, 'talim_turi', $request->talim_turi);
        }
        if ($request->filled('talim_shakli')) {
            $this->applyNormalizedExactFilter($query, 'talim_shakli', $request->talim_shakli);
        }
        if ($request->filled('tolov_shakli')) {
            $this->applyNormalizedExactFilter($query, 'tolov_shakli', $request->tolov_shakli);
        }
        if ($request->filled('fakultet')) {
            $this->applyNormalizedExactFilter($query, 'fakultet', $request->fakultet);
        }
        if ($request->filled('talaba_toifasi')) {
            $this->applyNormalizedExactFilter($query, 'talaba_toifasi', $request->talaba_toifasi);
        }
        if ($request->filled('imtiyoz_toifasi')) {
            $this->applyNormalizedExactFilter($query, 'imtiyoz_toifasi', $request->imtiyoz_toifasi);
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', 'like', '%' . trim((string) $request->student_id) . '%');
        }
        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . trim((string) $request->full_name) . '%');
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

    private function filteredQueryWithScoreStats(Request $request)
    {
        return $this->filteredQuery($request)
            ->select('admission_indicators.*')
            ->selectSub(function ($query) {
                $query->from('admission_indicators as ai2')
                    ->selectRaw('MAX(ai2.toplagan_bali)')
                    ->whereRaw('ai2.qabul_yili <=> admission_indicators.qabul_yili')
                    ->whereRaw('ai2.talim_turi <=> admission_indicators.talim_turi')
                    ->whereRaw('ai2.talim_shakli <=> admission_indicators.talim_shakli')
                    ->whereRaw('ai2.mutaxassislik <=> admission_indicators.mutaxassislik')
                    ->whereRaw('ai2.mutaxassislik_kodi <=> admission_indicators.mutaxassislik_kodi')
                    ->whereRaw('ai2.tolov_shakli <=> admission_indicators.tolov_shakli')
                    ->whereRaw('ai2.fakultet <=> admission_indicators.fakultet');
            }, 'max_toplagan_bali');
    }

    public function index(Request $request)
    {
        if (!Schema::hasTable('admission_indicators')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        $indicators = $this->filteredQueryWithScoreStats($request)
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

        $fakultetlar = AdmissionIndicator::query()
            ->whereNotNull('fakultet')
            ->where('fakultet', '<>', '')
            ->select('fakultet')
            ->distinct()
            ->orderBy('fakultet')
            ->pluck('fakultet');

        $talabaToifalari = AdmissionIndicator::query()
            ->whereNotNull('talaba_toifasi')
            ->where('talaba_toifasi', '<>', '')
            ->select('talaba_toifasi')
            ->distinct()
            ->orderBy('talaba_toifasi')
            ->pluck('talaba_toifasi');

        $imtiyozToifalari = AdmissionIndicator::query()
            ->whereNotNull('imtiyoz_toifasi')
            ->where('imtiyoz_toifasi', '<>', '')
            ->select('imtiyoz_toifasi')
            ->distinct()
            ->orderBy('imtiyoz_toifasi')
            ->pluck('imtiyoz_toifasi');

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
            'fakultetlar' => $fakultetlar,
            'talabaToifalari' => $talabaToifalari,
            'imtiyozToifalari' => $imtiyozToifalari,
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

    private function applyNormalizedExactFilter($query, string $column, ?string $value): void
    {
        $normalizedValue = $this->normalizeFilterValue($value);

        if ($normalizedValue === null) {
            return;
        }

        $query->whereRaw($this->normalizedColumnSql($column) . ' = ?', [$normalizedValue]);
    }

    private function normalizeFilterValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '') {
            return null;
        }

        $value = str_replace(["'", '`', '’', '‘', 'ʼ', 'ʻ', '"'], '', $value);
        $value = str_replace(['-', '–', '—', '−'], '', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? '';

        return $value !== '' ? $value : null;
    }

    private function normalizedColumnSql(string $column): string
    {
        $allowedColumns = [
            'talim_turi',
            'talim_shakli',
            'tolov_shakli',
            'fakultet',
            'talaba_toifasi',
            'imtiyoz_toifasi',
        ];

        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Unsupported filter column: {$column}");
        }

        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '–', ''), '—', ''), '−', ''), '''', ''), '’', ''), '‘', ''), 'ʼ', ''), 'ʻ', ''))";
    }

    private function uploadImportFile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls',
            ], [
                'file.required' => 'Excel fayl tanlanmadi.',
                'file.mimes' => 'Faqat .xlsx yoki .xls fayl yuklash mumkin.',
            ]);

            $this->clearImportState();

            $path = $request->file('file')->store('admission-indicators-imports');
            $absolutePath = Storage::disk('local')->path($path);
            $headers = $this->readExcelHeader($absolutePath);
            $columnMap = $this->resolveImportColumnMap($headers);

            if (!$this->hasMappedField($columnMap, 'oquv_yili')) {
                Storage::disk('local')->delete($path);
                return response()->json([
                    'success' => false,
                    'message' => 'Excel sarlavhalaridan "O\'quv yili" ustuni topilmadi.',
                ], 422);
            }

            $lastRow = $this->getExcelLastRow($absolutePath);
            $totalRows = max(0, $lastRow - 1);

            if ($totalRows === 0) {
                Storage::disk('local')->delete($path);
                return response()->json([
                    'success' => false,
                    'message' => 'Excel faylda import qilinadigan ma\'lumot topilmadi.',
                ], 422);
            }

            session([
                self::IMPORT_SESSION_KEY => [
                    'path' => $path,
                    'column_map' => $columnMap,
                    'original_name' => $request->file('file')->getClientOriginalName(),
                    'next_row' => 2,
                    'last_row' => $lastRow,
                    'processed_rows' => 0,
                    'total_rows' => $totalRows,
                    'imported_rows' => 0,
                    'errors' => [],
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fayl yuklandi. Endi ma\'lumotlarni ko\'chirishni boshlashingiz mumkin.',
                'total_rows' => $totalRows,
                'file_name' => $request->file('file')->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            $this->clearImportState();

            return response()->json([
                'success' => false,
                'message' => 'Excel yuklashda xatolik: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function processImportChunk(Request $request): JsonResponse
    {
        try {
            $state = session(self::IMPORT_SESSION_KEY);

            if (!$state || empty($state['path']) || !Storage::disk('local')->exists($state['path'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Avval Excel faylni yuklang.',
                ], 422);
            }

            $absolutePath = Storage::disk('local')->path($state['path']);
            $startRow = (int) $state['next_row'];
            $endRow = min((int) $state['last_row'], $startRow + self::IMPORT_CHUNK_SIZE - 1);
            $chunk = $this->extractExcelChunkRows($absolutePath, $startRow, $endRow);
            $columnMap = is_array($state['column_map'] ?? null) ? $state['column_map'] : self::IMPORT_COLUMN_MAP;

            foreach ($chunk as $rowMeta) {
                try {
                    $payload = $this->mapExcelRowToPayload($rowMeta['row'], $columnMap);
                    $payload['created_by'] = auth()->id();
                    $payload['updated_by'] = auth()->id();
                    AdmissionIndicator::create($payload);
                    $state['imported_rows']++;
                } catch (\Throwable $e) {
                    $state['errors'][] = [
                        'row' => $rowMeta['row_number'],
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Admission indicator import row failed', [
                        'row' => $rowMeta['row_number'],
                        'error' => $e->getMessage(),
                        'student_id' => $rowMeta['row'][2] ?? null,
                        'full_name' => $rowMeta['row'][3] ?? null,
                    ]);
                }
            }

            $state['next_row'] = $endRow + 1;
            $state['processed_rows'] = min($state['total_rows'], max(0, $endRow - 1));
            session([self::IMPORT_SESSION_KEY => $state]);

            $percent = $state['total_rows'] > 0
                ? (int) floor(($state['processed_rows'] / $state['total_rows']) * 100)
                : 100;
            $finished = $state['next_row'] > $state['last_row'];

            if ($finished && $state['imported_rows'] === 0 && count($state['errors']) > 0) {
                $message = 'Hech bir qator yozilmadi. Birinchi xato: ' . $state['errors'][0]['error'];
                $this->clearImportState();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors_count' => count($state['errors']),
                    'errors_preview' => array_slice($state['errors'], 0, 5),
                ], 422);
            }

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
                Log::info('Admission indicator import completed', [
                    'imported_rows' => $state['imported_rows'],
                    'errors_count' => count($state['errors']),
                    'errors_preview' => array_slice($state['errors'], 0, 5),
                ]);

                $this->clearImportState();
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Admission indicator import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import jarayonida xatolik: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function readExcelHeader(string $absolutePath): array
    {
        $spreadsheet = $this->loadSpreadsheetSlice($absolutePath, 1, 1);
        $sheet = $spreadsheet->getActiveSheet();

        return array_values($sheet->rangeToArray(
            'A1:' . $sheet->getHighestColumn() . '1',
            null,
            true,
            true,
            false
        )[0] ?? []);
    }

    private function getExcelLastRow(string $absolutePath): int
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $worksheetInfo = $reader->listWorksheetInfo($absolutePath);

        return (int) ($worksheetInfo[0]['totalRows'] ?? 0);
    }

    private function extractExcelChunkRows(string $absolutePath, int $startRow, int $endRow): array
    {
        $spreadsheet = $this->loadSpreadsheetSlice($absolutePath, $startRow, $endRow);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $data = [];

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $row = array_values($sheet->rangeToArray(
                'A' . $rowNumber . ':' . $highestColumn . $rowNumber,
                null,
                true,
                true,
                false
            )[0] ?? []);

            if ($this->isExcelRowEmpty($row)) {
                continue;
            }

            $data[] = [
                'row_number' => $rowNumber,
                'row' => $row,
            ];
        }

        return $data;
    }

    private function loadSpreadsheetSlice(string $absolutePath, int $startRow, int $endRow)
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new AdmissionIndicatorChunkReadFilter($startRow, $endRow));

        return $reader->load($absolutePath);
    }

    private function mapExcelRowToPayload(array $row, array $columnMap = self::IMPORT_COLUMN_MAP): array
    {
        $payload = [];

        foreach ($columnMap as $index => $field) {
            $payload[$field] = $this->normalizeValueByField($field, $row[$index] ?? null);
        }

        $payload['qabul_yili'] = $this->parseAdmissionYear($payload['oquv_yili'] ?? null);
        if ($payload['qabul_yili'] === null) {
            throw new \RuntimeException('O\'quv yilidan qabul yilini aniqlab bo\'lmadi.');
        }

        $payload['reja'] = $payload['kvota'] ?? null;
        $payload['qabul_soni'] = 1;
        $payload['min_ball'] = $payload['otish_bali'] ?? null;

        return $payload;
    }

    private function resolveImportColumnMap(array $headers): array
    {
        $resolved = [];
        $jshshirCount = 0;

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeImportHeader((string) $header);
            if ($normalized === '') {
                continue;
            }

            $field = match (true) {
                $normalized === 'tr',
                $normalized === 't r' => 't_r',
                str_contains($normalized, 'jshshir') || str_contains($normalized, 'pinfl') => ++$jshshirCount === 1 ? 'jshshir_kod' : 'jshshir_kod_2',
                str_contains($normalized, 'talaba id') || str_contains($normalized, 'student id') => 'student_id',
                str_contains($normalized, 'toliq ismi') || str_contains($normalized, 'fish') || str_contains($normalized, 'f i sh') => 'full_name',
                str_contains($normalized, 'farmoyish') => 'farmoyish',
                str_contains($normalized, 'buyruq') => 'buyruq',
                str_contains($normalized, 'fuqarolik') => 'fuqarolik',
                str_contains($normalized, 'davlat') => 'davlat',
                str_contains($normalized, 'millat') => 'millat',
                str_contains($normalized, 'viloyat') => 'viloyat',
                str_contains($normalized, 'tuman') => 'tuman',
                str_contains($normalized, 'jins') => 'jinsi',
                str_contains($normalized, 'tugilgan sana') => 'tugilgan_sana',
                str_contains($normalized, 'pasport raqami') || str_contains($normalized, 'passport raqami') => 'passport_raqami',
                str_contains($normalized, 'kurs') => 'kurs',
                str_contains($normalized, 'fakultet') => 'fakultet',
                str_contains($normalized, 'talim tili') => 'talim_tili',
                str_contains($normalized, 'oquv yili') || str_contains($normalized, 'oqish yili') => 'oquv_yili',
                str_contains($normalized, 'mutaxassislik kodi') => 'mutaxassislik_kodi',
                str_contains($normalized, 'mutaxassislik') => 'mutaxassislik',
                str_contains($normalized, 'talim turi') => 'talim_turi',
                str_contains($normalized, 'talim shakli') => 'talim_shakli',
                str_contains($normalized, 'tolov shakli') => 'tolov_shakli',
                str_contains($normalized, 'talaba toifasi') => 'talaba_toifasi',
                str_contains($normalized, 'imtiyoz toifasi') => 'imtiyoz_toifasi',
                str_contains($normalized, 'toplagan bali') => 'toplagan_bali',
                str_contains($normalized, 'otish bali') => 'otish_bali',
                str_contains($normalized, 'barobari') || str_contains($normalized, 'bazaviy') => 'tolov_kontrakt_barobari_bazaviy',
                str_contains($normalized, 'shartnoma summasi') || str_contains($normalized, 'shatrtnoma summasi') => 'tolov_kontrakt_shartnoma_summasi',
                str_contains($normalized, 'kvota') => 'kvota',
                default => null,
            };

            if ($field !== null) {
                $resolved[$index] = $field;
            }
        }

        return $resolved ?: self::IMPORT_COLUMN_MAP;
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');
        $header = str_replace(["'", '`', '’', '‘', 'ʼ', 'ʻ', '"'], '', $header);
        $header = preg_replace('/[^\pL\pN]+/u', ' ', $header) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $header) ?? '');
    }

    private function hasMappedField(array $columnMap, string $field): bool
    {
        return in_array($field, $columnMap, true);
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
