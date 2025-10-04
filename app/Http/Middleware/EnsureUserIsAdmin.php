<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware sprawdzający czy użytkownik jest administratorem
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jeśli użytkownik nie jest zalogowany, przekieruj do logowania admina
        if (!$request->user()) {
            return redirect()->route('admin.login');
        }

        // Jeśli użytkownik nie jest adminem, wyloguj i przekieruj
        if (!$request->user()->isAdmin()) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->with('error', 'Brak uprawnień administratora.');
        }

        return $next($request);
    }
}

