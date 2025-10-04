<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Wymuś jasny motyw dla stron autoryzacji (login, register, reset hasła)
        $authRoutes = ['login', 'register', 'password.request', 'password.reset', 'verification.notice'];
        
        if ($request->routeIs($authRoutes)) {
            View::share('appearance', 'light');
        } else {
            View::share('appearance', $request->cookie('appearance') ?? 'system');
        }

        return $next($request);
    }
}
