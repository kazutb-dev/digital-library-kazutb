<?php

namespace App\Http\Middleware;

use App\Support\LocaleResolver;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $queryLocale = $request->query('lang');
        if ($this->resolver->isSupported($queryLocale) && $request->hasSession()) {
            $request->session()->put('locale', $this->resolver->normalize($queryLocale));
        }
        $locale = $this->resolver->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        View::share('pageLang', $locale);
        View::share('supportedLocales', LocaleResolver::SUPPORTED);

        $response = $next($request);

        Cookie::queue(cookie(
            LocaleResolver::COOKIE,
            $locale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        return $response;
    }
}
