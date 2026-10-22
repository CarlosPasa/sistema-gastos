<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['is_active'] = true;

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas o cuenta inactiva.',
            ]);
        }

        $request->session()->regenerate();
        \Log::info('MT2 LOGIN DEBUG', [
            'user_id' => auth()->id(),
            'authenticated' => auth()->check(),
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->route('dashboard');
    }


    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
