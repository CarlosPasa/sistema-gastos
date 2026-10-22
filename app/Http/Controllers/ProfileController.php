<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->name = $data['name'];
        $user->email = strtolower(trim($data['email']));
        $user->save();

        return back()->with('ok', 'Perfil actualizado correctamente.');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(10)->letters()->numbers(),
            ],
        ]);

        $user = $request->user();
        $user->password = Hash::make($data['password']);
        $user->save();

        $request->session()->regenerate();

        return back()->with('ok', 'Contraseña actualizada correctamente.');
    }
}
