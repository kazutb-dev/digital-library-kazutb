<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = $request->query('lang', 'ru');
        $locale = in_array($requestedLocale, ['ru', 'kk', 'en'], true) ? $requestedLocale : 'ru';

        app()->setLocale($locale);
        View::share('pageLang', $locale);

        return $next($request);
    }
}
