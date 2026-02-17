<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
// use Yajra\DataTables\Facades\DataTables;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
            'force.student.contact' => \App\Http\Middleware\ForceStudentContact::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'telegram/webhook/*',
            'moodle/import',
            'moodle/should-sync',
        ]);

    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->call(function () {
            Log::info('✅ Scheduler is running at ' . now());
        })->everyMinute();
        $schedule->command('import:curricula')->weekly();
        $schedule->command('import:curriculum-subjects')->weekly();
        $schedule->command('import:groups')->weekly();
        $schedule->command('import:semesters')->weekly();
        $schedule->command('import:specialties-departments')->weekly();
        $schedule->command('students:import')->weekly();
        $schedule->command('import:schedules')->daily()->withoutOverlapping(120);
        $schedule->command('import:curriculum-subject-teachers')->dailyAt('22:00');

        $schedule->command('student:import-data')->everyFourHours();
        $schedule->command('import:teachers')->cron('0 0 */2 * *'); // Every 2 days at midnight
//        $schedule->command('grades:close-expired')->everyMinute();
        $schedule->command('grades:close-expired')->dailyAt('23:59');
//        $schedule->command('app:test-cron')->everyFifteenSeconds();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            $guard = \Illuminate\Support\Facades\Auth::getDefaultDriver();
            $user = auth()->user();
            $userRoles = $user?->getRoleNames()?->join(', ') ?? 'rollarsiz';
            $userName = $user?->full_name ?? $user?->name ?? 'noma\'lum';

            Log::warning('Ruxsat berilmadi (UnauthorizedException)', [
                'guard' => $guard,
                'user' => $userName,
                'user_roles' => $userRoles,
                'url' => $request->fullUrl(),
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sizda bu amalni bajarish huquqi yo\'q.',
                    'debug' => config('app.debug') ? [
                        'guard' => $guard,
                        'user' => $userName,
                        'roles' => $userRoles,
                        'error' => $e->getMessage(),
                    ] : null,
                ], 403);
            }

            $errorMessage = 'Sizda bu amalni bajarish huquqi yo\'q.';
            if (config('app.debug')) {
                $errorMessage .= " (Guard: {$guard}, Foydalanuvchi: {$userName}, Rollar: {$userRoles})";
            }

            return redirect()->back()->with('error', $errorMessage);
        });
    })->create();
