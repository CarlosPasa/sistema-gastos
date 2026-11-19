
@extends('layouts.mt2')

@section('title', $config['title'].' · MT 2.0')

@section('content')
<div class="finance-head">
    <div>
        <h1>{{ $config['title'] }}</h1>
        <p class="sub">
            Administra tus registros financieros de forma segura.
        </p>
    </div>

    <a class="action primary-action"
       href="{{ route('mt2.finance.create', $modulo) }}">
        + Nuevo registro
    </a>
</div>

@if(session('ok'))
    <div class="flash" role="status">
        {{ session('ok') }}
    </div>
@endif

@if($errors->any())
    <div class="flash error" role="alert">
        {{ $errors->first() }}
    </div>
@endif

<div class="card finance-card">

    {{-- FILTROS --}}
    <form class="finance-filters mt2-finance-filters"
          method="GET"
          action="{{ route('mt2.finance.index', $modulo) }}">

        <div class="mt2-filter-main">

            <div class="mt2-filter-field">
                <label for="filter-q">Buscar movimiento</label>
                <input
                    id="filter-q"
                    type="search"
                    name="q"
                    placeholder="Buscar por descripción..."
                    value="{{ request('q') }}"
                >
            </div>

            @if($modulo === 'gastos')
                <div class="mt2-filter-field">
                    <label for="filter-tipo">Tipo de movimiento</label>
                    <select id="filter-tipo" name="tipo">
                        <option value="">Todos los movimientos</option>
                        <option value="consumo"
                            @selected(request('tipo') === 'consumo')>
                            Consumo
                        </option>
                        <option value="ahorro"
                            @selected(request('tipo') === 'ahorro')>
                            Ahorro
                        </option>
                    </select>
                </div>
            @endif
        </div>

        @if($modulo === 'gastos')
            @php
                $periodoSeleccionado =
                    request()->filled('desde') ||
                    request()->filled('hasta')
                        ? 'rango'
                        : 'mes';
            @endphp

            <div class="mt2-period-section">

                <div class="mt2-filter-field">
                    <label for="filter-periodo">Filtrar por periodo</label>

                    <select id="filter-periodo">
                        <option value="mes"
                            @selected($periodoSeleccionado === 'mes')>
                            Por mes
                        </option>
                        <option value="rango"
                            @selected($periodoSeleccionado === 'rango')>
                            Rango de fechas
                        </option>
                    </select>
                </div>

                <div id="mt2-filter-month"
                     class="mt2-period-fields"
                     @if($periodoSeleccionado !== 'mes') hidden @endif>

                     @php
                    $mesSeleccionado = request('mes', now()->format('Y-m'));

                    $anioActual = (int) now()->format('Y');
                    $anioSeleccionado = (int) substr($mesSeleccionado, 0, 4);
                    $numeroMes = substr($mesSeleccionado, 5, 2);

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

                <div class="mt2-month-selectors">

                    <div class="mt2-filter-field">
                        <label for="filter-anio">Año</label>

                        <select id="filter-anio">
                            @foreach(range(max($anioActual, $anioSeleccionado), 2020) as $anio)
                                <option value="{{ $anio }}"
                                    @selected($anio == $anioSeleccionado)>
                                    {{ $anio }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt2-filter-field">
                        <label for="filter-numero-mes">Mes</label>

                        <select id="filter-numero-mes">
                            @foreach($meses as $numero => $nombre)
                                <option value="{{ $numero }}"
                                    @selected($numero === $numeroMes)>
                                    {{ $nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <input type="hidden"
                        name="mes"
                        id="filter-mes"
                        value="{{ $mesSeleccionado }}">
                </div>

                </div>

                <div id="mt2-filter-range"
                     class="mt2-period-fields mt2-date-range"
                     @if($periodoSeleccionado !== 'rango') hidden @endif>

                    <div class="mt2-filter-field">
                        <label for="filter-desde">Desde</label>
                        <input
                            id="filter-desde"
                            type="date"
                            name="desde"
                            value="{{ request('desde') }}"
                        >
                    </div>

                    <div class="mt2-filter-field">
                        <label for="filter-hasta">Hasta</label>
                        <input
                            id="filter-hasta"
                            type="date"
                            name="hasta"
                            value="{{ request('hasta') }}"
                        >
                    </div>
                </div>
            </div>
        @endif

        <div class="mt2-filter-actions">
            <button class="action primary-action" type="submit">
                Buscar
            </button>

            <a class="action"
               href="{{ route('mt2.finance.index', $modulo) }}">
                Limpiar
            </a>
        </div>

    </form>

    {{-- TABLA --}}
    <div class="table-scroll mt2-table-scroll">
        <table class="finance-table">
            <thead>
                <tr>
                    <th>Concepto</th>

                    @if($modulo !== 'categorias')
                        <th>Monto</th>
                    @endif

                    @if(in_array($modulo, ['gastos', 'presupuestos']))
                        <th>Categoría</th>
                    @endif

                    @if($modulo === 'gastos')
                        <th>Tipo</th>
                    @endif

                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row->description }}</strong>
                        </td>

                        @if($modulo !== 'categorias')
                            <td class="money">
                                S/ {{ number_format((float) $row->amount, 2, '.', ',') }}
                            </td>
                        @endif

                        @if(in_array($modulo, ['gastos', 'presupuestos']))
                            <td>
                                {{ $names[$row->id_category] ?? 'Sin categoría' }}
                            </td>
                        @endif

                        @if($modulo === 'gastos')
                            <td>
                                <span class="finance-pill {{ $row->save ? 'save' : '' }}">
                                    {{ $row->save ? 'Ahorro' : 'Consumo' }}
                                </span>
                            </td>
                        @endif

                        <td>
                            <span class="finance-pill">
                                {{ $row->estado ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>

                        <td>
                            {{ in_array($modulo, ['gastos', 'ingresos'])
                                ? $row->date
                                : substr((string) $row->created_at, 0, 10) }}
                        </td>

                        <td class="actions">
                            <div class="mt2-row-actions">

                                <a class="action"
                                   href="{{ route('mt2.finance.edit', [$modulo, $row->id]) }}">
                                    Editar
                                </a>

                                @if(!$row->estado)
                                    <form method="POST"
                                          action="{{ route('mt2.finance.activate', [
                                              'modulo' => $modulo,
                                              'id' => $row->id
                                          ]) }}">
                                        @csrf
                                        @method('PATCH')

                                        <button type="submit" class="mt2-activate">
                                            Activar
                                        </button>
                                    </form>
                                @else
                                    <form method="POST"
                                          action="{{ route('mt2.finance.destroy', [
                                              $modulo, $row->id
                                          ]) }}"
                                          onsubmit="return confirm('¿Desactivar este registro?')">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="action danger">
                                            Desactivar
                                        </button>
                                    </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            No hay registros para mostrar.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="finance-pages">
        {{ $rows->links('vendor.pagination.mt2') }}
    </div>
</div>

@if($modulo === 'gastos')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const anioSelect = document.getElementById('filter-anio');
    const mesSelect = document.getElementById('filter-numero-mes');
    const mesInput = document.getElementById('filter-mes');

    function actualizarMes() {
        mesInput.value = anioSelect.value + '-' + mesSelect.value;
    }

    anioSelect.addEventListener('change', actualizarMes);
    mesSelect.addEventListener('change', actualizarMes);

    actualizarMes();

    const selector = document.getElementById('filter-periodo');
    const month = document.getElementById('mt2-filter-month');
    const range = document.getElementById('mt2-filter-range');
    const form = selector.closest('form');

    function updatePeriod() {
        const isMonth = selector.value === 'mes';
        mesInput.disabled = !isMonth;
        anioSelect.disabled = !isMonth;
        mesSelect.disabled = !isMonth;
        month.hidden = !isMonth;
        range.hidden = isMonth;

        month.querySelectorAll('input').forEach(input => {
            input.disabled = !isMonth;
        });

        range.querySelectorAll('input').forEach(input => {
            input.disabled = isMonth;
        });
    }

    selector.addEventListener('change', updatePeriod);

    form.addEventListener('submit', function (event) {
        if (selector.value !== 'rango') return;

        const desde = document.getElementById('filter-desde').value;
        const hasta = document.getElementById('filter-hasta').value;

        if (!desde || !hasta || desde > hasta) {
            event.preventDefault();
            alert('Selecciona un rango de fechas válido.');
        }
    });

    updatePeriod();
});
</script>
@endif
@endsection
