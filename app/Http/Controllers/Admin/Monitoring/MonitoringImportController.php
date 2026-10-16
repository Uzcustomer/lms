<?php

namespace App\Http\Controllers\Admin\Monitoring;

use App\Exports\MonitoringImportTemplate;
use App\Http\Controllers\Controller;
use App\Models\MonitoringDataImport;
use App\Services\Monitoring\Imports\ImportSheet;
use App\Services\Monitoring\Imports\OrderImporter;
use App\Services\Monitoring\Imports\RecommendationImporter;
use App\Services\Monitoring\MonitoringAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Manba ma'lumotlari importi (TZ 4.2): Excel/CSV yuklash → ustunlarni moslash → import.
 * HEMIS'da buyruqlar sinxronlanmaydi, shuning uchun reyestr shu yerdan to'ldiriladi.
 */
class MonitoringImportController extends Controller
{
    public function index()
    {
        abort_unless(MonitoringAccess::canViewAll(MonitoringAccess::actor()), 403);

        return view('admin.monitoring.importlar.index', [
            'imports' => MonitoringDataImport::with('importedBy')->latest()->paginate(30),
            'canWork' => MonitoringAccess::canWork(MonitoringAccess::actor()),
            'defaultYear' => self::defaultEducationYear(),
        ]);
    }

    public function template(string $kind)
    {
        abort_unless(MonitoringAccess::canWork(MonitoringAccess::actor()), 403);
        abort_unless(array_key_exists($kind, MonitoringDataImport::KINDS), 404);

        return Excel::download(new MonitoringImportTemplate($kind), "monitoring_{$kind}_shablon.xlsx");
    }

    public function upload(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canWork($actor), 403);

        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', array_keys(MonitoringDataImport::KINDS)),
            'file' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
            'evidence' => 'nullable|file|max:' . (int) config('monitoring.evidence_max_kb', 20480) . '|mimes:pdf,jpg,jpeg,png',
            'education_year' => 'nullable|string|max:20|regex:/^\d{4}-\d{4}$/',
        ], [
            'file.mimes' => 'Fayl Excel (.xlsx, .xls) yoki CSV bo\'lishi kerak.',
        ]);

        $disk = config('monitoring.evidence_disk', 'local');
        $dir = trim(config('monitoring.evidence_dir', 'monitoring'), '/') . '/imports';
        $uuid = Str::uuid()->toString();
        $file = $request->file('file');
        $path = $file->storeAs($dir, $uuid . '.' . strtolower($file->getClientOriginalExtension() ?: 'xlsx'), $disk);
        $evidencePath = $request->hasFile('evidence')
            ? $request->file('evidence')->storeAs($dir, $uuid . '-dalil.' . strtolower($request->file('evidence')->getClientOriginalExtension()), $disk)
            : null;

        try {
            $sheet = ImportSheet::read($path, $disk);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete(array_filter([$path, $evidencePath]));

            return back()->with('error', 'Faylni o\'qib bo\'lmadi: ' . mb_substr($e->getMessage(), 0, 200));
        }

        if ($sheet->headers === [] || $sheet->rows === []) {
            Storage::disk($disk)->delete(array_filter([$path, $evidencePath]));

            return back()->with('error', 'Faylda sarlavha qatori yoki ma\'lumot yo\'q.');
        }

        $import = MonitoringDataImport::create([
            'kind' => $data['kind'],
            'source' => strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'excel',
            'status' => MonitoringDataImport::STATUS_PENDING,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $path,
            'evidence_path' => $evidencePath,
            'rows_total' => count($sheet->rows),
            'meta' => ['education_year' => $data['education_year'] ?? self::defaultEducationYear()],
            'imported_by_type' => $actor->getMorphClass(),
            'imported_by_id' => $actor->getKey(),
        ]);

        return redirect()->route('admin.monitoring.importlar.mapping', $import);
    }

    public function mapping(MonitoringDataImport $import)
    {
        abort_unless(MonitoringAccess::canWork(MonitoringAccess::actor()), 403);
        if ($import->status !== MonitoringDataImport::STATUS_PENDING) {
            return redirect()->route('admin.monitoring.importlar.index')->with('error', 'Bu import allaqachon bajarilgan.');
        }

        $sheet = ImportSheet::read($import->file_path, config('monitoring.evidence_disk', 'local'));
        $fields = self::fields($import->kind);

        return view('admin.monitoring.importlar.mapping', [
            'import' => $import,
            'fields' => $fields,
            'headers' => $sheet->headers,
            'guess' => $sheet->guess(array_map(fn ($f) => $f['aliases'], $fields)),
            'sample' => array_slice($sheet->rows, 0, 5),
            'distinct' => $sheet->distinctByColumn(),
            'orderTypes' => collect(config('monitoring.order_types'))->map(fn ($t) => $t['label'])->all(),
            'typeGuesses' => collect($sheet->distinctByColumn())->map(
                fn ($values) => collect($values)->mapWithKeys(fn ($v) => [$v => OrderImporter::guessType($v)])->all()
            )->all(),
        ]);
    }

    public function process(Request $request, MonitoringDataImport $import)
    {
        abort_unless(MonitoringAccess::canWork(MonitoringAccess::actor()), 403);
        if ($import->status !== MonitoringDataImport::STATUS_PENDING) {
            return redirect()->route('admin.monitoring.importlar.index')->with('error', 'Bu import allaqachon bajarilgan.');
        }

        $fields = self::fields($import->kind);
        $rules = ['map' => 'required|array', 'type_map' => 'nullable|array'];
        foreach ($fields as $key => $field) {
            $rules["map.{$key}"] = ($field['required'] ? 'required' : 'nullable') . '|integer|min:0';
        }
        $request->validate($rules, ['map.*.required' => 'Majburiy maydonlar uchun ustun tanlang.']);

        $map = array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, (array) $request->input('map'));
        $sheet = ImportSheet::read($import->file_path, config('monitoring.evidence_disk', 'local'));
        $import->forceFill(['meta' => array_merge((array) $import->meta, ['map' => $map])])->save();

        if ($import->kind === MonitoringDataImport::KIND_ORDERS) {
            // type_map[ustun][xom tur] — faqat tanlangan «Buyruq turi» ustunidagisi olinadi.
            $typeMap = (array) data_get((array) $request->input('type_map', []), (string) ($map['order_type_raw'] ?? ''), []);
            app(OrderImporter::class)->import($import, $sheet, $map, $typeMap);
            $next = route('admin.monitoring.buyruqlar.index');
        } else {
            $year = (string) data_get($import->meta, 'education_year', self::defaultEducationYear());
            app(RecommendationImporter::class)->import($import, $sheet, $map, $year);
            $next = route('admin.monitoring.tavsiyalar.index', ['yil' => $year]);
        }

        $import->refresh();
        $message = "Import yakunlandi: {$import->rows_ok} ta qator qabul qilindi"
            . ($import->rows_error ? ", {$import->rows_error} ta xato (importlar ro'yxatida)." : '.');

        return redirect($next)->with($import->rows_error ? 'error' : 'success', $message);
    }

    public function evidence(MonitoringDataImport $import)
    {
        abort_unless(MonitoringAccess::canViewAll(MonitoringAccess::actor()), 403);
        $disk = Storage::disk(config('monitoring.evidence_disk', 'local'));
        abort_unless($import->evidence_path && $disk->exists($import->evidence_path), 404);

        return $disk->download($import->evidence_path, 'dalil-' . $import->id . '.' . pathinfo($import->evidence_path, PATHINFO_EXTENSION));
    }

    public static function fields(string $kind): array
    {
        return $kind === MonitoringDataImport::KIND_ORDERS ? OrderImporter::FIELDS : RecommendationImporter::FIELDS;
    }

    public static function defaultEducationYear(): string
    {
        $year = now()->month >= 8 ? now()->year : now()->year - 1;

        return $year . '-' . ($year + 1);
    }
}
