<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = DB::table('user_settings')
            ->where('user_id', $request->user()->id)
            ->first();

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'preferred_currency' => [
                'required',
                Rule::in(['PEN', 'USD']),
            ],
            'theme' => [
                'required',
                Rule::in(['dark', 'light', 'auto']),
            ],
            'default_month' => [
                'nullable',
                'integer',
                'between:1,12',
            ],
        ]);

        DB::table('user_settings')->updateOrInsert(
            ['user_id' => $request->user()->id],
            [
                'preferred_currency' => $data['preferred_currency'],
                'theme' => $data['theme'],
                'default_month' => $data['default_month'] ?? null,
                'updated_at' => now(),
            ]
        );

        return back()->with('ok', 'Preferencias guardadas correctamente.');
    }


    public function updateTheme(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', \Illuminate\Validation\Rule::in([
                'dark', 'light', 'auto'
            ])],
        ]);

        $userId = $request->user()->id;

        $existing = DB::table('user_settings')
            ->where('user_id', $userId)
            ->exists();

        if ($existing) {
            DB::table('user_settings')
                ->where('user_id', $userId)
                ->update([
                    'theme' => $data['theme'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('user_settings')->insert([
                'user_id' => $userId,
                'preferred_currency' => 'PEN',
                'theme' => $data['theme'],
                'default_month' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'theme' => $data['theme'],
        ]);
    }
}
