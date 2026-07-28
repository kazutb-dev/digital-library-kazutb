<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceArraySessionInTests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            config()->set('session.driver', 'array');
            $session = app('session.store');
            $session->start();
            $request->setLaravelSession($session);
        }

        return $next($request);
    }
}
