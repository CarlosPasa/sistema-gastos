
@extends('layouts.mt2')

@section('title', ($item ? 'Editar ' : 'Nuevo ') . $config['title'] . ' · MT 2.0')

@section('content')

@php
    $esPresupuesto = $modulo === 'presupuestos';

    $periodo = old('effective_month', now()->format('Y-m'));

    $anioSeleccionado = (int) substr($periodo, 0, 4);
    $mesSeleccionado = substr($periodo, 5, 2);
    $anioActual = (int) now()->format('Y');

    $meses = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];
@endphp

<div class="finance-head">
    <div>
        <h1>
            {{ $item ? 'Editar' : 'Nuevo registro' }}
            · {{ $config['title'] }}
        </h1>
        <p class="sub">Los registros están asociados a tu usuario.</p>
    </div>

    <a class="action" href="{{ route('mt2.finance.index', $modulo) }}">
        ← Volver
    </a>
</div>

<div class="card finance-card finance-form">
    <form method="POST"
          action="{{ $item
              ? route('mt2.finance.update', [$modulo, $item->id])
              : route('mt2.finance.store', $modulo) }}">

        @csrf

        @if($item)
            @method('PUT')
        @endif

        <label>
            Descripción
            <input
                name="description"
                required
                maxlength="100"
                value="{{ old('description', $item->description ?? '') }}">
        </label>

        @if($modulo !== 'categorias')
            <label>
                Monto (S/)
                <input
                    name="amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    required
                    value="{{ old('amount', $item->amount ?? '') }}">
            </label>
        @endif

        @if(in_array($modulo, ['gastos', 'ingresos']))
            <label>
                Fecha de movimiento
                <input
                    name="date"
                    type="date"
                    required
                    value="{{ old('date', $item->date ?? now()->toDateString()) }}">
            </label>
        @endif

        @if($modulo === 'gastos')
            <label>
                Clasificación
                <select name="save" required>
                    <option value="0"
                        @selected((string) old('save', $item->save ?? 0) === '0')>
                        Gasto de consumo
                    </option>

                    <option value="1"
                        @selected((string) old('save', $item->save ?? 0) === '1')>
                        Dinero destinado al ahorro
                    </option>
                </select>
            </label>
        @endif

        @if(in_array($modulo, ['gastos', 'presupuestos']))
            <label>
                Categoría
                <select name="id_category" required>
                    <option value="">Selecciona una categoría</option>

                    @foreach($categories as $category)
                        <option
                            value="{{ $category->id }}"
                            @selected(old('id_category', $item->id_category ?? null) == $category->id)>
                            {{ $category->description }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif

        {{-- Vigencia mensual solo para Presupuestos --}}
        @if($esPresupuesto)
            <div class="mt2-budget-period">
                <div class="mt2-budget-period-title">
                    <strong>Vigencia del presupuesto</strong>
                    <p class="sub">
                        @if($item)
                            El nuevo importe se aplicará desde el mes seleccionado.
                            Los meses anteriores conservarán sus importes.
                        @else
                            El presupuesto se repetirá automáticamente cada mes
                            desde el periodo seleccionado.
                        @endif
                    </p>
                </div>

                <div class="mt2-budget-period-fields">
                    <label for="budget-anio">
                        Año
                        <select id="budget-anio" required>
                            @for($anio = max($anioActual, $anioSeleccionado); $anio >= 2020; $anio--)
                                <option value="{{ $anio }}"
                                    @selected($anio === $anioSeleccionado)>
                                    {{ $anio }}
                                </option>
                            @endfor
                        </select>
                    </label>

                    <label for="budget-mes">
                        Mes
                        <select id="budget-mes" required>
                            @foreach($meses as $numero => $nombre)
                                <option value="{{ $numero }}"
                                    @selected($numero === $mesSeleccionado)>
                                    {{ $nombre }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <input
                    type="hidden"
                    name="effective_month"
                    id="budget-effective-month"
                    value="{{ $periodo }}">
            </div>
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
            <button class="action primary-action" type="submit">
                Guardar
            </button>

            <a class="action" href="{{ route('mt2.finance.index', $modulo) }}">
                Cancelar
            </a>
        </div>
    </form>
</div>

@if($esPresupuesto)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const anio = document.getElementById('budget-anio');
    const mes = document.getElementById('budget-mes');
    const periodo = document.getElementById('budget-effective-month');

    if (!anio || !mes || !periodo) return;

    function actualizarPeriodo() {
        periodo.value = anio.value + '-' + mes.value;
    }

    anio.addEventListener('change', actualizarPeriodo);
    mes.addEventListener('change', actualizarPeriodo);

    anio.closest('form').addEventListener('submit', actualizarPeriodo);

    actualizarPeriodo();
});
</script>
@endif

@endsection
