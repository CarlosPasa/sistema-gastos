@extends('layouts.mt2')

@section('title', ($item ? 'Editar usuario' : 'Nuevo usuario') . ' · MT 2.0')

@section('content')

<div class="finance-head">
    <div>
        <h1>{{ $item ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
        <p class="sub">
            {{ $item
                ? 'Actualiza la información y permisos de la cuenta.'
                : 'Registra una cuenta nueva en MT 2.0.' }}
        </p>
    </div>

    <a class="action" href="{{ route('mt2.users.index') }}">
        ← Volver
    </a>
</div>

<div class="card finance-card finance-form">
    <form method="POST"
          action="{{ $item
              ? route('mt2.users.update', $item->id)
              : route('mt2.users.store') }}">

        @csrf

        @if($item)
            @method('PUT')
        @endif

        <label>
            Nombre completo
            <input type="text"
                   name="name"
                   required
                   maxlength="255"
                   value="{{ old('name', $item->name ?? '') }}">
        </label>

        <label>
            Correo electrónico
            <input type="email"
                   name="email"
                   required
                   maxlength="255"
                   value="{{ old('email', $item->email ?? '') }}">
        </label>

        <label>
            Rol
            <select name="role"
                    required
                    @disabled($item && $item->id === auth()->id())>

                <option value="user"
                    @selected(old('role', $item->role ?? 'user') === 'user')>
                    Usuario
                </option>

                <option value="admin"
                    @selected(old('role', $item->role ?? 'user') === 'admin')>
                    Administrador
                </option>
            </select>

            @if($item && $item->id === auth()->id())
                <input type="hidden" name="role" value="admin">
            @endif
        </label>

        <label>
            {{ $item ? 'Nueva contraseña (opcional)' : 'Contraseña' }}
            <input type="password"
                   name="password"
                   autocomplete="new-password"
                   @required(!$item)>
        </label>

        <label>
            Confirmar contraseña
            <input type="password"
                   name="password_confirmation"
                   autocomplete="new-password"
                   @required(!$item)>
        </label>

        @if($item)
            <p class="sub">
                Deja los campos de contraseña vacíos para conservar
                la contraseña actual.
            </p>
        @else
            <p class="sub">
                La contraseña debe tener al menos 10 caracteres,
                incluyendo letras y números.
            </p>
        @endif

        @if($errors->any())
            <div class="flash error" role="alert">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="finance-actions">
            <button type="submit" class="action primary-action">
                Guardar usuario
            </button>

            <a class="action" href="{{ route('mt2.users.index') }}">
                Cancelar
            </a>
        </div>
    </form>
</div>

@endsection
