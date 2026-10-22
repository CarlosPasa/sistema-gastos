@extends('layouts.mt2')

@section('title', 'Configuración · MT 2.0')

@section('content')

@php
    $currency = old('preferred_currency', $settings->preferred_currency ?? 'PEN');
    $theme = old('theme', $settings->theme ?? 'dark');
    $defaultMonth = old('default_month', $settings->default_month ?? '');
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
@endphp

<div class="finance-head">
    <div>
        <h1>Configuración</h1>
        <p class="sub">
            Personaliza tu experiencia en MT 2.0.
        </p>
    </div>
</div>

@if(session('ok'))
    <div class="flash">{{ session('ok') }}</div>
@endif

<div class="card finance-card finance-form mt2-settings-card">

    <h2>Preferencias personales</h2>
    <p class="sub">
        Estas opciones se guardan únicamente para tu cuenta.
    </p>

    <form method="POST" action="{{ route('mt2.settings.update') }}">
        @csrf
        @method('PUT')

        <label>
            Moneda preferida
            <select name="preferred_currency" required>
                <option value="PEN" @selected($currency === 'PEN')>
                    Sol peruano (PEN)
                </option>
                <option value="USD" @selected($currency === 'USD')>
                    Dólar estadounidense (USD)
                </option>
            </select>
        </label>

        <p class="sub">
            Esta preferencia no convierte los movimientos
            existentes a otra moneda.
        </p>

        <label>
            Apariencia
            <select name="theme" required>
                <option value="dark" @selected($theme === 'dark')>
                    Oscuro
                </option>
                <option value="light" @selected($theme === 'light')>
                    Claro
                </option>
                <option value="auto" @selected($theme === 'auto')>
                    Automático
                </option>
            </select>
        </label>

        <label>
            Mes predeterminado
            <select name="default_month">
                <option value="" @selected($defaultMonth === '')>
                    Mes actual
                </option>

                @foreach($months as $number => $name)
                    <option value="{{ $number }}"
                        @selected((string)$defaultMonth === (string)$number)>
                        {{ $name }}
                    </option>
                @endforeach
            </select>
        </label>

        <p class="sub">
            El mes predeterminado se aplicará al abrir
            el dashboard sin seleccionar un periodo.
            Utilizará el año actual.
        </p>

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
                Guardar configuración
            </button>
        </div>
    </form>
</div>

@endsection
