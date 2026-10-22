<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
        ]);

        $user = new User();

        $user->name = $data['name'];
        $user->email = strtolower(trim($data['email']));
        $user->password = Hash::make($data['password']);

        // Nunca recibir el rol desde el formulario público.
        $user->role = 'user';
        $user->is_active = true;

        $user->save();

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
