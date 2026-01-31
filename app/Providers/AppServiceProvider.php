<?php

namespace App\Providers;

use App\View\Components\CustomSelect;
use App\View\Components\SelectInput;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Facades\DataTables;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        if (env('APP_ENV', 'local') != 'local') {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}