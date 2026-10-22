<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('q')) {
            $search = mb_substr($request->input('q'), 0, 100);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if (in_array($request->query('role'), ['admin', 'user'], true)) {
            $query->where('role', $request->query('role'));
        }

        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $users = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', [
            'item' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('mt2.users.index')
            ->with('ok', 'Usuario creado correctamente.');
    }

    public function edit(int $id)
    {
        $item = User::findOrFail($id);

        return view('users.form', compact('item'));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
        ]);

        DB::transaction(function () use ($request, $id, $data) {
            // Serializa cambios administrativos concurrentes.
            $admins = User::where('role', 'admin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $item = User::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->id === (int) $request->user()->id
                && $data['role'] !== 'admin') {
                throw ValidationException::withMessages([
                    'role' => 'No puedes quitarte tu propio rol administrador.',
                ]);
            }

            if ($item->role === 'admin'
                && $item->is_active
                && $data['role'] !== 'admin'
                && $admins->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Debe existir al menos un administrador activo.',
                ]);
            }

            $item->name = $data['name'];
            $item->email = strtolower(trim($data['email']));
            $item->role = $data['role'];

            if (!empty($data['password'])) {
                $item->password = Hash::make($data['password']);
            }

            $item->save();
        });

        return redirect()
            ->route('mt2.users.index')
            ->with('ok', 'Usuario actualizado correctamente.');
    }

    public function changeStatus(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $admins = User::where('role', 'admin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $item = User::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->is_active) {
                if ($item->id === (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'status' => 'No puedes desactivar tu propia cuenta.',
                    ]);
                }

                if ($item->role === 'admin' && $admins->count() <= 1) {
                    throw ValidationException::withMessages([
                        'status' => 'No puedes desactivar al último administrador activo.',
                    ]);
                }
            }

            $item->is_active = !$item->is_active;
            $item->save();
        });

        return back()->with('ok', 'Estado del usuario actualizado.');
    }
}
