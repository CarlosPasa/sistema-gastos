@extends('layouts.mt2')

@section('title', 'Mi cuenta · MT 2.0')

@section('content')

<div class="finance-head">
    <div>
        <h1>Mi cuenta</h1>
        <p class="sub">Administra tu información personal y seguridad.</p>
    </div>
</div>

@if(session('ok'))
    <div class="flash">{{ session('ok') }}</div>
@endif

<div class="mt2-profile-grid">

    <div class="card finance-card finance-form">
        <h2>Información personal</h2>
        <p class="sub">Datos asociados a tu cuenta.</p>

        <form method="POST" action="{{ route('mt2.profile.update') }}">
            @csrf
            @method('PUT')

            <label>
                Nombre completo
                <input type="text" name="name" required maxlength="255"
                       value="{{ old('name', $user->name) }}">
            </label>

            <label>
                Correo electrónico
                <input type="email" name="email" required maxlength="255"
                       value="{{ old('email', $user->email) }}">
            </label>

            <div class="mt2-profile-details">
                <div>
                    <span>Rol</span>
                    <strong>{{ $user->role === 'admin' ? 'Administrador' : 'Usuario' }}</strong>
                </div>
                <div>
                    <span>Estado</span>
                    <strong>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</strong>
                </div>
            </div>

            @if($errors->has('name') || $errors->has('email'))
                <div class="flash error">
                    @foreach($errors->get('name') as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                    @foreach($errors->get('email') as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="finance-actions">
                <button type="submit" class="action primary-action">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <div class="card finance-card finance-form">
        <h2>Cambiar contraseña</h2>
        <p class="sub">Protege el acceso a tu cuenta.</p>

        <form method="POST" action="{{ route('mt2.profile.password') }}">
            @csrf
            @method('PUT')

            <label>
                Contraseña actual
                <input type="password" name="current_password"
                       autocomplete="current-password" required>
            </label>

            <label>
                Nueva contraseña
                <input type="password" name="password"
                       autocomplete="new-password" required>
            </label>

            <label>
                Confirmar nueva contraseña
                <input type="password" name="password_confirmation"
                       autocomplete="new-password" required>
            </label>

            <p class="sub">
                Mínimo 10 caracteres, incluyendo letras y números.
            </p>

            @if($errors->has('current_password') || $errors->has('password'))
                <div class="flash error">
                    @foreach($errors->get('current_password') as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                    @foreach($errors->get('password') as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="finance-actions">
                <button type="submit" class="action primary-action">
                    Actualizar contraseña
                </button>
            </div>
        </form>
    </div>

</div>

@endsection
