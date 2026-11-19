@extends('layouts.mt2')

@section('title', 'Importar extracto · MT 2.0')

@section('content')
<div class="finance-head">
    <div>
        <h1>Importar extracto</h1>
        <p class="sub">Sube el CSV de tu banco y registramos los movimientos por ti.</p>
    </div>
</div>

@if(session('ok'))
    <div class="flash" role="status">{{ session('ok') }}</div>
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

<div class="card finance-card finance-form">
    @if($categories->isEmpty())
        <p>
            Primero crea una categoría: los gastos del extracto se registrarán en ella.
            <a class="action" href="{{ route('mt2.finance.create', 'categorias') }}">+ Nueva categoría</a>
        </p>
    @else
        <form method="POST" action="{{ route('mt2.imports.store') }}" enctype="multipart/form-data">
            @csrf

            <label>
                Archivo CSV (máx. 1 MB)
                <input type="file" name="file" accept=".csv,text/csv" required>
            </label>

            <label>
                Categoría para los gastos
                <select name="id_category" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('id_category') == $category->id)>
                            {{ $category->description }}
                        </option>
                    @endforeach
                </select>
            </label>

            <p class="sub">
                Columnas: <strong>fecha</strong>, <strong>descripcion</strong>, <strong>monto</strong>.
                Montos negativos = gastos; positivos = ingresos.
                Si subes el mismo extracto dos veces, no se duplican movimientos.
            </p>

            <div class="finance-actions">
                <button class="action primary-action" type="submit">Importar</button>
            </div>
        </form>
    @endif
</div>

<div class="card finance-card">
    <h2>Importaciones recientes</h2>

    <div class="table-scroll mt2-table-scroll">
        <table class="finance-table">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Estado</th>
                    <th>Gastos</th>
                    <th>Ingresos</th>
                    <th>Duplicados omitidos</th>
                    <th>Subido</th>
                </tr>
            </thead>
            <tbody>
                @forelse($imports as $import)
                    <tr>
                        <td>
                            <strong>{{ $import->original_name }}</strong>
                            <small>Categoría: {{ $import->category?->description ?? '—' }}</small>
                        </td>
                        <td>
                            <span class="finance-pill {{ $import->status === \App\Enums\ImportStatus::Completed ? 'save' : '' }}">
                                {{ $import->status->label() }}
                            </span>
                            @if($import->failure_message)
                                <small>{{ $import->failure_message }}</small>
                            @endif
                        </td>
                        <td>{{ $import->created_expenses }}</td>
                        <td>{{ $import->created_incomes }}</td>
                        <td>{{ $import->skipped_duplicates }}</td>
                        <td>{{ $import->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                    @if(! empty($import->row_errors))
                        <tr>
                            <td colspan="6">
                                <details>
                                    <summary>{{ count($import->row_errors) }} fila(s) no importada(s)</summary>
                                    <ul>
                                        @foreach($import->row_errors as $rowError)
                                            <li>{{ $rowError }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6">Todavía no has importado extractos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($imports->contains(fn ($import) => ! $import->status->isFinished()))
    {{-- Hay importaciones en curso: refrescar hasta que terminen. --}}
    <script>setTimeout(() => window.location.reload(), 4000);</script>
@endif
@endsection
