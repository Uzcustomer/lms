<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentVisaInfo;
use App\Models\VisaCountryRule;
use App\Services\VisaSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Xalqaro talabalar bo'limi sozlamalari — faqat admin/superadmin.
 */
class InternationalStudentSettingsController extends Controller
{
    public function edit()
    {
        if (!Schema::hasTable('visa_country_rules')) {
            return redirect()->route('admin.international-students.index')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        // Xalqaro talabalar davlatlari (HEMIS country_name) va soni
        $countryCounts = Student::where(function ($q) {
                $q->where('group_name', 'like', 'xd%')
                  ->orWhere('citizenship_name', 'like', '%orijiy%');
            })
            ->whereNotNull('country_name')->where('country_name', '!=', '')
            ->selectRaw('country_name, count(*) as cnt')
            ->groupBy('country_name')
            ->pluck('cnt', 'country_name');

        $rules = VisaCountryRule::all()->keyBy('country_name');
        $fallback = VisaSettings::fallbackRule();

        // Talabasi yo'q bo'lsa ham saqlangan qoidalar ro'yxatda qoladi
        $countries = $countryCounts->keys()->merge($rules->keys())->unique()->sort()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'count' => (int) ($countryCounts[$name] ?? 0),
                'show_choice' => $rules[$name]->show_choice ?? $fallback['show_choice'],
                'default_basis' => $rules[$name]->default_basis ?? $fallback['default_basis'],
                'saved' => $rules->has($name),
            ]);

        $thresholds = [];
        foreach (array_keys(VisaSettings::THRESHOLD_DEFAULTS) as $type) {
            $thresholds[$type] = VisaSettings::thresholds($type);
        }

        $requiredDocs = [];
        foreach (array_keys(StudentVisaInfo::BASIS_LABELS) as $basis) {
            $requiredDocs[$basis] = VisaSettings::requiredDocs($basis);
        }

        $firmLines = collect(VisaSettings::firmOptions())
            ->map(fn ($label, $key) => $label === $key ? $key : "{$key} | {$label}")
            ->implode("\n");

        return view('admin.international-students.settings', [
            'countries' => $countries,
            'fallback' => $fallback,
            'thresholds' => $thresholds,
            'infoDeadlineDays' => VisaSettings::infoDeadlineDays(),
            'visaTypesText' => implode("\n", array_keys(VisaSettings::visaTypes())),
            'firmLines' => $firmLines,
            'dormitoryAddress' => VisaSettings::dormitoryAddress(),
            'requiredDocs' => $requiredDocs,
        ]);
    }

    public function update(Request $request)
    {
        $basisKeys = implode(',', array_keys(StudentVisaInfo::BASIS_LABELS));

        $request->validate([
            'countries' => 'array',
            'countries.*.name' => 'required|string|max:255',
            'countries.*.default_basis' => 'required|in:' . $basisKeys,
            'fallback_default_basis' => 'required|in:' . $basisKeys,
            'thresholds' => 'required|array',
            'thresholds.*.*' => 'required|integer|min:1|max:365',
            'info_deadline_days' => 'required|integer|min:1|max:60',
            'visa_types' => 'required|string|max:2000',
            'firms' => 'required|string|max:5000',
            'dormitory_address' => 'required|string|max:500',
            'required_docs' => 'array',
        ], [
            'thresholds.*.*.required' => "Ogohlantirish kunlarini to'liq kiriting.",
            'thresholds.*.*.min' => "Ogohlantirish kuni kamida 1 bo'lishi kerak.",
            'visa_types.required' => 'Kamida bitta viza turini kiriting.',
            'firms.required' => 'Kamida bitta firmani kiriting.',
            'dormitory_address.required' => 'Yotoqxona manzilini kiriting.',
        ]);

        $thresholds = $this->parseThresholds($request->input('thresholds', []));
        $visaTypes = $this->parseVisaTypes($request->input('visa_types'));
        $firms = $this->parseFirms($request->input('firms'));

        $requiredDocs = [];
        foreach (VisaSettings::DOCS_BY_BASIS as $basis => $docs) {
            $requiredDocs[$basis] = array_values(array_intersect($docs, (array) $request->input("required_docs.{$basis}", [])));
        }

        DB::transaction(function () use ($request, $thresholds, $visaTypes, $firms, $requiredDocs) {
            foreach ($request->input('countries', []) as $row) {
                VisaCountryRule::updateOrCreate(
                    ['country_name' => $row['name']],
                    ['show_choice' => !empty($row['show_choice']), 'default_basis' => $row['default_basis']]
                );
            }

            Setting::set('intl_choice_default_show', $request->boolean('fallback_show_choice') ? '1' : '0');
            Setting::set('intl_choice_default_basis', $request->input('fallback_default_basis'));
            Setting::set('intl_thresholds', json_encode($thresholds));
            Setting::set('intl_info_deadline_days', (string) (int) $request->input('info_deadline_days'));
            Setting::set('intl_visa_types', json_encode($visaTypes, JSON_UNESCAPED_UNICODE));
            Setting::set('intl_firm_options', json_encode($firms, JSON_UNESCAPED_UNICODE));
            Setting::set('intl_dormitory_address', trim($request->input('dormitory_address')));
            Setting::set('intl_required_docs', json_encode($requiredDocs));
        });

        VisaSettings::forget();

        $message = 'Sozlamalar saqlandi.';

        // O'chirilgan, lekin talabalarga biriktirilgan firmalar haqida ogohlantirish
        $orphanFirms = StudentVisaInfo::whereNotNull('firm')
            ->whereNotIn('firm', array_merge(array_keys($firms), ['other', '']))
            ->distinct()->pluck('firm');
        if ($orphanFirms->isNotEmpty()) {
            $message .= ' Diqqat: ro\'yxatdan olib tashlangan, lekin talabalarga biriktirilgan firmalar bor: '
                . $orphanFirms->implode(', ') . '.';
        }

        return redirect()->route('admin.international-students.settings')->with('success', $message);
    }

    /** danger <= warning <= info bo'lishi shart */
    private function parseThresholds(array $input): array
    {
        $out = [];
        foreach (array_keys(VisaSettings::THRESHOLD_DEFAULTS) as $type) {
            $t = [
                'info' => (int) ($input[$type]['info'] ?? 0),
                'warning' => (int) ($input[$type]['warning'] ?? 0),
                'danger' => (int) ($input[$type]['danger'] ?? 0),
            ];
            if (!($t['danger'] <= $t['warning'] && $t['warning'] <= $t['info'])) {
                throw ValidationException::withMessages([
                    'thresholds' => "Ogohlantirish kunlari: qizil ≤ sariq ≤ yashil bo'lishi kerak.",
                ]);
            }
            $out[$type] = $t;
        }
        return $out;
    }

    private function parseVisaTypes(string $text): array
    {
        $types = collect(preg_split('/\R/', $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values();

        foreach ($types as $type) {
            if (mb_strlen($type) > 20) {
                throw ValidationException::withMessages([
                    'visa_types' => "Viza turi 20 belgidan oshmasligi kerak: {$type}",
                ]);
            }
        }

        if ($types->isEmpty()) {
            throw ValidationException::withMessages(['visa_types' => 'Kamida bitta viza turini kiriting.']);
        }

        return $types->all();
    }

    /** Har bir qator: "KALIT | Ko'rinadigan nom" yoki faqat "KALIT" */
    private function parseFirms(string $text): array
    {
        $firms = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$key, $label] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($key === '' || mb_strlen($key) > 50 || $key === 'other') {
                throw ValidationException::withMessages([
                    'firms' => "Firma kaliti noto'g'ri (bo'sh, 50 belgidan uzun yoki \"other\"): {$line}",
                ]);
            }
            $firms[$key] = $label !== '' ? $label : $key;
        }

        if (!$firms) {
            throw ValidationException::withMessages(['firms' => 'Kamida bitta firmani kiriting.']);
        }

        return $firms;
    }
}
