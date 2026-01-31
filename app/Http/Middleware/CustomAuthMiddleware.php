<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class CustomAuthMiddleware extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        // Ваши кастомные проверки
        if ($request->header('X-Custom-Header') !== 'valid') {
            abort(403, 'Invalid Header');
        }

        // Вызов стандартной аутентификации
        parent::authenticate($request, $guards);
    }
}
