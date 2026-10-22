
@extends('layouts.mt2')

@section('title', 'Reportes · MT 2.0')

@section('content')

@php
    $periodo = $month ?? now()->format('Y-m');

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
        <h1>Reporte financiero</h1>
        <p class="sub">
            Ingresos, consumo, ahorros y disponible sin asignar.
        </p>
    </div>

    <form class="finance-filters"
          method="GET"
          action="{{ route('mt2.finance.report') }}">

        <div class="report-filter-group">
            <label for="report-anio">Año</label>

            <select id="report-anio">
                @for($anio = max($anioActual, $anioSeleccionado); $anio >= 2020; $anio--)
                    <option value="{{ $anio }}"
                        @selected($anio === $anioSeleccionado)>
                        {{ $anio }}
                    </option>
                @endfor
            </select>
        </div>

        <div class="report-filter-group">
            <label for="report-mes">Mes</label>

            <select id="report-mes">
                @foreach($meses as $numero => $nombre)
                    <option value="{{ $numero }}"
                        @selected($numero === $mesSeleccionado)>
                        {{ $nombre }}
                    </option>
                @endforeach
            </select>
        </div>

        <input
            type="hidden"
            id="report-periodo"
            name="mes"
            value="{{ $periodo }}"
        >

        <button class="action primary-action" type="submit">
            Actualizar
        </button>
    </form>
</div>

<div class="kpis finance-kpis">
    <div class="card kpi">
        <div class="label">Ingresos</div>
        <div class="amount">
            S/ {{ number_format($totals['income'], 2) }}
        </div>
    </div>

    <div class="card kpi">
        <div class="label">Gastos de consumo</div>
        <div class="amount">
            S/ {{ number_format($totals['spent'], 2) }}
        </div>
    </div>

    <div class="card kpi">
        <div class="label">Ahorro registrado</div>
        <div class="amount">
            S/ {{ number_format($totals['saving'], 2) }}
        </div>
    </div>

    <div class="card kpi primary">
        <div class="label">Disponible sin asignar</div>
        <div class="amount">
            S/ {{ number_format(
                $totals['income'] - $totals['spent'] - $totals['saving'],
                2
            ) }}
        </div>
    </div>
</div>

<div class="card finance-card" style="margin-top:16px">
    <h2>Consumo por categoría</h2>

    <div class="table-scroll">
        <table class="finance-table">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody>
                @forelse($breakdown as $row)
                    <tr>
                        <td>{{ $row->description }}</td>
                        <td>
                            S/ {{ number_format($row->total, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">
                            Sin movimientos de consumo en el periodo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('.finance-head .finance-filters');
    const anio = document.getElementById('report-anio');
    const mes = document.getElementById('report-mes');
    const periodo = document.getElementById('report-periodo');

    if (!form || !anio || !mes || !periodo) return;

    function actualizarPeriodo() {
        periodo.value = anio.value + '-' + mes.value;
    }

    anio.addEventListener('change', actualizarPeriodo);
    mes.addEventListener('change', actualizarPeriodo);
    form.addEventListener('submit', actualizarPeriodo);

    actualizarPeriodo();
});
</script>

@endsection
