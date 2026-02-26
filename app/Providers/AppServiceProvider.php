<?php

namespace App\Providers;

use App\View\Components\CustomSelect;
use App\View\Components\SelectInput;
use App\Models\AbsenceExcuse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Facades\DataTables;

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
        App::alias('DataTables', DataTables::class);
        Blade::component('layouts.student-app', 'student-app-layout');
        Blade::component('layouts.teacher-app', 'teacher-app-layout');
        Blade::component('select-input', SelectInput::class);

        View::composer('components.admin-sidebar-menu', function ($view) {
            $view->with('pendingExcusesCount', AbsenceExcuse::where('status', 'pending')->count());

            $user = auth()->user();
            $unread = 0;
            if ($user && method_exists($user, 'unreadNotifications')) {
                $unread = $user->unreadNotifications()->count();
            }
            $view->with('unreadNotificationsCount', $unread);
        });

        if (env('APP_ENV', 'local') != 'local') {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}