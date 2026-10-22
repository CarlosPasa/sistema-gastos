<div class="card">
    <h2>Presupuestos mensuales</h2>
    <p>Seguimiento del periodo seleccionado</p>
    @forelse($recurringBudgets as $budget)
        <div style="margin:16px 0">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <strong>{{ $budget['description'] }}</strong>
                <span>S/ {{ number_format($budget['spent'],2) }} / S/ {{ number_format($budget['amount'],2) }}</span>
            </div>
            <small>{{ $budget['category'] }} · {{ number_format($budget['percent'],1) }}% utilizado</small>
            <div style="width:100%;height:9px;background:rgba(100,116,139,.25);border-radius:8px;overflow:hidden;margin-top:7px">
                <div style="height:100%;width:{{ min(100, $budget['percent']) }}%;background:{{ $budget['percent'] > 100 ? '#fb7185' : ($budget['percent'] >= 80 ? '#fbbf24' : '#3b82f6') }};border-radius:8px"></div>
            </div>
            @if($budget['percent'] > 100)
                <small style="color:#fb7185">Presupuesto excedido</small>
            @endif
        </div>
    @empty
        <div class="notice">Todavía no hay presupuestos con vigencia para este periodo.</div>
    @endforelse
    <a class="action" href="{{ route('mt2.finance.index', 'presupuestos') }}">Administrar presupuestos</a>
</div>
