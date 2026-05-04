<?php

namespace App\Providers;

use App\View\Components\CustomSelect;
use App\View\Components\SelectInput;
use App\Models\AbsenceExcuse;
use App\Models\ExamAppeal;
use App\Models\StudentGrade;
use App\Observers\StudentGradeObserver;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ensure helpers are loaded even if composer autoload_files is stale
        $helpersPath = app_path('Helpers/helpers.php');
        if (file_exists($helpersPath) && !function_exists('format_date')) {
            require_once $helpersPath;
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        StudentGrade::observe(StudentGradeObserver::class);

        App::alias('DataTables', DataTables::class);
        Blade::component('layouts.student-app', 'student-app-layout');
        Blade::component('layouts.teacher-app', 'teacher-app-layout');
        Blade::component('select-input', SelectInput::class);

        View::composer('components.admin-sidebar-menu', function ($view) {
            $view->with('pendingExcusesCount', AbsenceExcuse::where('status', 'pending')->count());

            $pendingAppeals = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('exam_appeals')) {
                    $pendingAppeals = ExamAppeal::whereIn('status', ['pending', 'reviewing'])->count();
                }
            } catch (\Throwable $e) {}
            $view->with('pendingAppealsCount', $pendingAppeals);

            // Sidebar pending count — har user uchun 1 daqiqaga keshlanadi.
            // Har admin sahifa ochilganda DB'ga urilmasligi uchun.
            $pendingRetake = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('retake_applications')) {
                    $activeRole = (string) session('active_role', '');
                    $teacher = \Illuminate\Support\Facades\Auth::guard('teacher')->user();
                    $userId = $teacher?->id ?? \Illuminate\Support\Facades\Auth::guard('web')->id() ?? 0;

                    $cacheKey = "retake.sidebar.{$activeRole}.{$userId}";

                    $pendingRetake = \Illuminate\Support\Facades\Cache::remember(
                        $cacheKey,
                        60, // 1 daqiqa
                        function () use ($activeRole, $teacher) {
                            $registrarLikeRoles = [
                                \App\Enums\ProjectRole::REGISTRAR_OFFICE->value,
                                \App\Enums\ProjectRole::SUPERADMIN->value,
                                \App\Enums\ProjectRole::ADMIN->value,
                            ];

                            if ($activeRole === \App\Enums\ProjectRole::DEAN->value && $teacher instanceof \App\Models\Teacher) {
                                $facultyIds = array_map('intval', $teacher->deanFacultyIds);
                                if (empty($facultyIds)) {
                                    return 0;
                                }
                                return \App\Models\RetakeApplication::query()
                                    ->where('dean_status', 'pending')
                                    ->where('final_status', 'pending')
                                    ->whereIn('student_hemis_id', function ($q) use ($facultyIds) {
                                        $q->select('hemis_id')->from('students')->whereIn('department_id', $facultyIds);
                                    })
                                    ->count();
                            }

                            if (in_array($activeRole, $registrarLikeRoles, true)) {
                                $count = \App\Models\RetakeApplication::query()
                                    ->where('registrar_status', 'pending')
                                    ->where('final_status', 'pending')
                                    ->count();

                                if (\Illuminate\Support\Facades\Schema::hasColumn('retake_application_groups', 'payment_verification_status')) {
                                    $count += \App\Models\RetakeApplicationGroup::query()
                                        ->whereNotNull('payment_uploaded_at')
                                        ->where('payment_verification_status', 'pending')
                                        ->count();
                                }
                                return $count;
                            }

                            return 0;
                        }
                    );
                }
            } catch (\Throwable $e) {}
            $view->with('pendingRetakeCount', $pendingRetake);
        });

        // Carbon diffForHumans() uchun o'zbek lotin alifbosida
        Carbon::macro('diffUz', function () {
            /** @var Carbon $this */
            $now = Carbon::now();
            $isFuture = $this->gt($now);
            $diff = $isFuture ? $now->diff($this) : $this->diff($now);

            if ($diff->y > 0) {
                $text = $diff->y . ' yil';
            } elseif ($diff->m > 0) {
                $text = $diff->m . ' oy';
            } elseif ($diff->d > 0) {
                $text = $diff->d . ' kun';
            } elseif ($diff->h > 0) {
                $text = $diff->h . ' soat';
            } elseif ($diff->i > 0) {
                $text = $diff->i . ' daqiqa';
            } else {
                return 'hozirgina';
            }

            return $isFuture ? $text . ' keyin' : $text . ' avval';
        });

        if (env('APP_ENV', 'local') != 'local') {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}