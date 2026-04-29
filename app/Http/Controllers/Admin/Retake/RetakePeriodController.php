<?php

namespace App\Http\Controllers\Admin\Retake;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\CreateRetakePeriodRequest;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationPeriod;
use App\Models\Semester;
use App\Models\Specialty;
use App\Services\Retake\RetakePeriodService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * O'quv bo'limi qabul oynalarini boshqaradi.
 * Spec talabi: bir vaqtda bir nechta oyna parallel faol bo'la oladi
 * (har yo'nalish + kurs + semestr uchun bittadan unique).
 *
 * Sanalar yaratilgach lock — bu yerda update endpointi yo'q (faqat
 * super-admin uchun alohida joyda — keyingi bosqichda).
 */
class RetakePeriodController extends Controller
{
    public function __construct(
        private readonly RetakePeriodService $periodService,
    ) {
    }

    public function index(Request $request): View
    {
        $query = RetakeApplicationPeriod::query()->orderByDesc('start_date');

        if ($specialtyId = $request->query('specialty_id')) {
            $query->where('specialty_id', $specialtyId);
        }
        if ($course = $request->query('course')) {
            $query->where('course', $course);
        }
        if ($semesterId = $request->query('semester_id')) {
            $query->where('semester_id', $semesterId);
        }
        if ($state = $request->query('state')) {
            $this->applyStateFilter($query, $state);
        }

        $periods = $query->paginate(25)->withQueryString();

        // Ariza sonlarini bir martalik bulk olish (N+1 oldini olish)
        $periodIds = $periods->getCollection()->pluck('id');
        $applicationCounts = RetakeApplication::query()
            ->whereIn('period_id', $periodIds)
            ->selectRaw('period_id, COUNT(*) as cnt')
            ->groupBy('period_id')
            ->pluck('cnt', 'period_id');

        $periods->getCollection()->transform(function (RetakeApplicationPeriod $period) use ($applicationCounts) {
            $period->setAttribute('applications_count', (int) ($applicationCounts[$period->id] ?? 0));
            return $period;
        });

        // Form uchun ro'yxatlar
        $specialties = Specialty::query()
            ->orderBy('name')
            ->get(['id', 'specialty_hemis_id', 'name', 'code']);

        $semesters = Semester::query()
            ->orderByDesc('current')
            ->orderBy('name')
            ->get(['id', 'semester_hemis_id', 'name', 'code', 'education_year', 'current']);

        return view('admin.retake.periods.index', [
            'periods' => $periods,
            'specialties' => $specialties,
            'semesters' => $semesters,
            'filters' => [
                'specialty_id' => $request->query('specialty_id'),
                'course' => $request->query('course'),
                'semester_id' => $request->query('semester_id'),
                'state' => $request->query('state'),
            ],
        ]);
    }

    public function store(CreateRetakePeriodRequest $request): RedirectResponse
    {
        try {
            $this->periodService->create(
                $request->user(),
                (int) $request->input('specialty_id'),
                (int) $request->input('course'),
                (int) $request->input('semester_id'),
                Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
                Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.retake.periods.index')
            ->with('success', "Qabul oynasi yaratildi.");
    }

    private function applyStateFilter(Builder $query, string $state): void
    {
        $today = Carbon::today()->toDateString();
        match ($state) {
            'active' => $query
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today),
            'upcoming' => $query->where('start_date', '>', $today),
            'closed' => $query->where('end_date', '<', $today),
            default => null,
        };
    }
}
