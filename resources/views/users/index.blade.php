@extends('layouts.mt2')

@section('title', 'Usuarios · MT 2.0')

@section('content')

<div class="finance-head">
    <div>
        <h1>Administración de usuarios</h1>
        <p class="sub">Gestiona cuentas, roles y accesos a MT 2.0.</p>
    </div>

    <a class="action primary-action"
       href="{{ route('mt2.users.create') }}">
        + Nuevo usuario
    </a>
</div>

<div class="card finance-card">

    @if(session('ok'))
        <div class="flash">{{ session('ok') }}</div>
    @endif

    @if($errors->any())
        <div class="flash error" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="GET"
          action="{{ route('mt2.users.index') }}"
          class="mt2-users-filters">

        <input type="search"
               name="q"
               placeholder="Buscar por nombre o correo"
               value="{{ request('q') }}">

        <select name="role" aria-label="Filtrar por rol">
            <option value="">Todos los roles</option>
            <option value="admin" @selected(request('role') === 'admin')>
                Administrador
            </option>
            <option value="user" @selected(request('role') === 'user')>
                Usuario
            </option>
        </select>

        <select name="status" aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            <option value="active" @selected(request('status') === 'active')>
                Activos
            </option>
            <option value="inactive" @selected(request('status') === 'inactive')>
                Inactivos
            </option>
        </select>

        <button class="action primary-action" type="submit">
            Buscar
        </button>

        <a class="action" href="{{ route('mt2.users.index') }}">
            Limpiar
        </a>
    </form>

    <div class="table-scroll">
        <table class="finance-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Registro</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            @if($user->id === auth()->id())
                                <small>(Tú)</small>
                            @endif
                        </td>

                        <td>{{ $user->email }}</td>

                        <td>
                            {{ $user->role === 'admin'
                                ? 'Administrador'
                                : 'Usuario' }}
                        </td>

                        <td>
                            <span class="mt2-user-status {{ $user->is_active ? 'active' : 'inactive' }}">
                                {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td>{{ $user->created_at?->format('d/m/Y') }}</td>

                        <td>
                            <div class="mt2-user-actions actions-stack">

                                <a class="action"
                                href="{{ route('mt2.users.edit', $user->id) }}">
                                    Editar
                                </a>

                                @if($user->id !== auth()->id())
                                    <form method="POST"
                                        action="{{ route('mt2.users.status', $user->id) }}"
                                        onsubmit="return confirm('¿Confirmas el cambio de estado de esta cuenta?')">

                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="action">
                                            {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            No se encontraron usuarios.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt2-users-pagination">
        {{ $users->links() }}
    </div>
</div>

@endsection
