<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Kontroler logowania dla administratorów
 */
class AdminLoginController extends Controller
{
    /**
     * Wyświetla formularz logowania dla administratorów
     */
    public function show()
    {
        return Inertia::render('Admin/Login');
    }

    /**
     * Obsługuje logowanie administratora
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Sprawdź czy użytkownik jest adminem
            if (!$user->isAdmin()) {
                Auth::logout();
                
                throw ValidationException::withMessages([
                    'email' => 'Nie masz uprawnień administratora.',
                ]);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'Podane dane logowania są nieprawidłowe.',
        ]);
    }

    /**
     * Wylogowuje administratora
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

