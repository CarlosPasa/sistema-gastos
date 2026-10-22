@extends('layouts.mt2')
@section('title', 'Dashboard · MT 2.0')
@section('content')
@php
    $money = fn ($value) => 'S/ '.number_format((float) $value, 2, '.', ',');
    $peak = max(1, ...array_map(fn ($item) => max($item['income'], $item['spent'], $item['saving']), $months));
    $x = fn ($i) => 45 + ($i * 116);
    $y = fn ($value) => round(195 - ((float) $value / $peak) * 150, 2);
    $line = fn ($field) => collect($months)->map(fn ($item, $i) => $x($i).','.$y($item[$field]))->implode(' ');
    $palette = ['#ff7284', '#ffc866', '#4b91ff', '#9e85fc', '#34d6ad', '#90a7c9'];
    $categoryTotal = $categoryRows->sum('total');
    $stops = [];
    $cursor = 0;
    foreach ($categoryRows as $i => $category) {
        $next = $cursor + (($categoryTotal > 0 ? (float) $category->total / $categoryTotal : 0) * 100);
        $color = $palette[$i % count($palette)];
        $stops[] = "$color {$cursor}% {$next}%";
        $cursor = $next;
    }
    $donutGradient = $categoryTotal > 0 ? 'conic-gradient('.implode(', ', $stops).')' : 'conic-gradient(#243b57 0% 100%)';
    $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
@endphp
<div class="head mt2-real-head">
    <div>
        <h1>Dashboard</h1>
        <p class="sub">Tus finanzas, con total claridad.</p>
    </div>
    <form action="{{ url('/mt2-preview') }}" method="GET" class="mt2-dashboard-filter">
        <label for="dashboard-year">Año</label>
        <select id="dashboard-year" aria-label="Año">
            @for ($year = max((int)now()->format('Y'), (int)substr($month,0,4)); $year >= 2020; $year--)
                <option value="{{ $year }}" @selected((int)substr($month,0,4)===$year)>{{ $year }}</option>
            @endfor
        </select>
        <label for="dashboard-month">Mes</label>
        <select id="dashboard-month" aria-label="Mes">
            @foreach ($meses as $number => $name)
                <option value="{{ $number }}" @selected(substr($month,5,2)===$number)>{{ $name }}</option>
            @endforeach
        </select>
        <input type="hidden" name="mes" id="dashboard-period" value="{{ $month }}">
        <button class="action primary-action" type="submit">Actualizar</button>
    </form>
</div>
<div class="kpis">
    <div class="card kpi primary"><span class="ico"><img class="duotone-icon" src="{{ asset('mt2/icons/disponible.svg') }}" alt=""></span><div class="label">Disponible sin asignar</div><div class="amount">{{ $money($available) }}</div><div class="desc">Ingresos − consumo − ahorros</div></div>
    <div class="card kpi"><span class="ico"><img class="duotone-icon" src="{{ asset('mt2/icons/ingresos.svg') }}" alt=""></span><div class="label">Ingresos del mes</div><div class="amount">{{ $money($income) }}</div><div class="desc green">Entradas registradas</div></div>
    <div class="card kpi"><span class="ico"><img class="duotone-icon" src="{{ asset('mt2/icons/gastos.svg') }}" alt=""></span><div class="label">Gastos de consumo</div><div class="amount">{{ $money($spent) }}</div><div class="desc red">Excluye dinero ahorrado</div></div>
    <div class="card kpi"><span class="ico"><img class="duotone-icon" src="{{ asset('mt2/icons/ahorro.svg') }}" alt=""></span><div class="label">Ahorro registrado</div><div class="amount">{{ $money($saving) }}</div><div class="desc green">Movimientos clasificados como ahorro</div></div>
</div>
<div class="section">
    <div class="card" id="analitica">
        <h2>Ingresos, gastos y ahorros</h2><p>Evolución real · últimos 6 meses hasta el periodo seleccionado</p>
        <div class="chart-wrap">
            <svg viewBox="0 0 670 240" role="img" aria-label="Evolución de ingresos, gastos y ahorros durante seis meses">
                <g stroke="#60748a" stroke-opacity=".25"><path d="M40 45 H640 M40 95 H640 M40 145 H640 M40 195 H640"/></g>
                <g fill="currentColor" opacity=".7" font-size="11"><text x="0" y="48">{{ number_format($peak, 0) }}</text><text x="10" y="198">0</text></g>
                @foreach($months as $i => $m)
                    <text x="{{ $x($i) }}" y="225" text-anchor="middle" fill="currentColor" opacity=".75" font-size="11">{{ $m['label'] }}</text>
                @endforeach
                <polyline points="{{ $line('income') }}" fill="none" stroke="#4b91ff" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                <polyline points="{{ $line('spent') }}" fill="none" stroke="#ff7284" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                <polyline points="{{ $line('saving') }}" fill="none" stroke="#34d6ad" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="legend"><span><i class="dot" style="background:#4b91ff"></i>Ingresos</span><span><i class="dot" style="background:#ff7284"></i>Gastos</span><span><i class="dot" style="background:#34d6ad"></i>Ahorro</span></div>
    </div>
    <div class="card" id="categorias">
        <h2>Gastos por categoría</h2><p>Distribución de gastos de consumo del periodo</p>
        <div class="donut-section">
            <div class="donut mt2-real-donut" style="background:{{ $donutGradient }}">
                <div class="donut-inner"><div>{{ $money($categoryTotal) }}<small>Total consumo</small></div></div>
            </div>
            <div class="breakdown">
                @forelse($categoryRows as $i => $category)
                    <div><span><i class="dot" style="background:{{ $palette[$i % count($palette)] }}"></i>{{ $category->description }}</span><b>{{ number_format($categoryTotal > 0 ? $category->total * 100 / $categoryTotal : 0, 1) }}%</b></div>
                @empty
                    <p>Sin gastos de consumo en el periodo.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
<div class="bottom mt2-real-bottom">
    <div class="card mt2-budget-dashboard" id="presupuestos">
        <h2>Presupuestos recurrentes</h2>
        <p>Seguimiento de tus límites mensuales · {{ $start->locale('es')->translatedFormat('F Y') }}</p>

        @php
            $totalBudget = collect($budgetSummary)->sum('amount');
            $totalBudgetSpent = collect($budgetSummary)->sum('spent');
        @endphp

        @if(count($budgetSummary) > 0)

            <div class="mt2-budget-summary">
                <div>
                    <span>Presupuesto mensual</span>
                    <strong>{{ $money($totalBudget) }}</strong>
                </div>

                <div>
                    <span>Consumo registrado</span>
                    <strong>{{ $money($totalBudgetSpent) }}</strong>
                </div>
            </div>

            <div class="mt2-budget-list">
                @foreach($budgetSummary as $budget)
                    @php
                        $percent = (float) $budget['percent'];
                        $barWidth = min(100, max(0, $percent));

                        $barClass = $percent > 100
                            ? 'danger'
                            : ($percent >= 80 ? 'warning' : 'success');
                    @endphp

                    <div class="mt2-budget-item">
                        <div class="mt2-budget-item-head">
                            <div>
                                <strong>{{ $budget['category'] }}</strong>
                                <small>{{ $budget['description'] }}</small>
                            </div>

                            <span>
                                {{ $money($budget['spent']) }}
                                / {{ $money($budget['amount']) }}
                            </span>
                        </div>

                        <div class="mt2-budget-track"
                            role="progressbar"
                            aria-label="Consumo del presupuesto {{ $budget['description'] }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $barWidth }}">

                            <div class="mt2-budget-progress {{ $barClass }}"
                                style="width: {{ $barWidth }}%">
                            </div>
                        </div>

                        <div class="mt2-budget-item-footer">
                            <span>{{ number_format($percent, 1) }}% utilizado</span>

                            @if($percent > 100)
                                <span class="red">Presupuesto excedido</span>
                            @elseif($percent >= 80)
                                <span>Próximo al límite</span>
                            @else
                                <span class="green">Dentro del presupuesto</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        @else
            <div class="notice">
                <strong>Sin presupuestos vigentes</strong>
                <p>
                    No tienes presupuestos activos para el periodo seleccionado.
                    Puedes crear uno o revisar su mes de inicio de vigencia.
                </p>
            </div>
        @endif

        <a class="action"
        href="{{ route('mt2.finance.index', 'presupuestos') }}">
            Administrar presupuestos
        </a>
    </div>
    <div class="card" id="movimientos"><h2>Últimos movimientos</h2><p>Registros activos del periodo seleccionado</p><div class="table-scroll"><table><thead><tr><th>Concepto</th><th>Tipo</th><th>Monto</th></tr></thead><tbody>
        @forelse($movements as $movement)
            <tr><td>{{ $movement['description'] }}<small>{{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}</small></td><td>{{ $movement['type'] }}</td><td class="{{ $movement['type']==='Gasto' ? 'red' : 'green' }}">{{ $money($movement['amount']) }}</td></tr>
        @empty<tr><td colspan="3">No hay movimientos en este periodo.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="card"><h2>Información útil</h2><p>Indicadores basados en los movimientos registrados</p><div class="notice"><strong class="green">Ahorros separados</strong><p>Los movimientos marcados como ahorro no se incluyen en gastos de consumo.</p></div><div class="notice"><strong>Disponible sin asignar</strong><p>{{ $money($available) }} para el periodo seleccionado. No representa necesariamente el saldo de una cuenta bancaria.</p></div><div class="notice"><strong>Fechas de ingresos</strong><p>Por ahora los ingresos se asignan al mes de su fecha de creación.</p></div></div>
</div>
@endsection
