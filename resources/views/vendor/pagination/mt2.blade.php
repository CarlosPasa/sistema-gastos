@if ($paginator->hasPages())
    <nav class="mt2-pagination" aria-label="Paginación de gastos">

        <div class="mt2-pagination-info">
            Mostrando {{ $paginator->firstItem() }}
            a {{ $paginator->lastItem() }}
            de {{ $paginator->total() }} registros
        </div>

        <div class="mt2-pagination-links">

            @if ($paginator->onFirstPage())
                <span class="mt2-page disabled">‹ Anterior</span>
            @else
                <a class="mt2-page"
                   href="{{ $paginator->previousPageUrl() }}">
                    ‹ Anterior
                </a>
            @endif

            @foreach ($elements as $element)

                @if (is_string($element))
                    <span class="mt2-page disabled">
                        {{ $element }}
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)

                        @if ($page == $paginator->currentPage())
                            <span class="mt2-page active"
                                  aria-current="page">
                                {{ $page }}
                            </span>
                        @else
                            <a class="mt2-page" href="{{ $url }}">
                                {{ $page }}
                            </a>
                        @endif

                    @endforeach
                @endif

            @endforeach

            @if ($paginator->hasMorePages())
                <a class="mt2-page"
                   href="{{ $paginator->nextPageUrl() }}">
                    Siguiente ›
                </a>
            @else
                <span class="mt2-page disabled">Siguiente ›</span>
            @endif

        </div>
    </nav>
@endif
