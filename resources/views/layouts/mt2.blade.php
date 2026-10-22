@php
    $mt2Theme = 'dark';

    if (auth()->check()) {
        $mt2Theme = \Illuminate\Support\Facades\DB::table('user_settings')
            ->where('user_id', auth()->id())
            ->value('theme') ?? 'dark';
    }

    $mt2Theme = in_array($mt2Theme, ['dark', 'light', 'auto'], true)
        ? $mt2Theme
        : 'dark';
@endphp
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','MT 2.0')</title>
    <link rel="stylesheet" href="{{ asset('mt2/css/dashboard.css') }}?v=13">
    <link rel="stylesheet" href="{{ asset('mt2/css/finance.css') }}?v=14">
    <link rel="stylesheet" href="{{ asset('mt2/css/dashboard-real.css') }}">
</head>

<body
    data-mt2-theme="{{ $mt2Theme }}"
    class="{{ $mt2Theme === 'light' ? 'light' : '' }}">
    <div id="sidebar-backdrop" class="sidebar-backdrop" hidden></div>
    <aside id="sidebar" aria-label="Navegación principal">
        <div class="side-header"><a class="logo" href="{{ url('/mt2-preview') }}" aria-label="MT 2.0 inicio">◆ <span>MT <b>2.0</b></span></a><button class="icon-button desktop-collapse" id="toggleDesktopSidebar" type="button" aria-label="Contraer menú" aria-expanded="true" title="Contraer menú"><span aria-hidden="true">←</span></button><button class="icon-button side-close" id="closeSidebar" type="button" aria-label="Cerrar menú">✕</button></div>
        <nav class="side-nav" aria-label="Secciones">

            {{-- Dashboard --}}
            <a href="{{ url('/mt2-preview') }}"
            class="{{ request()->is('mt2-preview') || request()->is('dashboard') ? 'current' : '' }}"
            @if(request()->is('mt2-preview') || request()->is('dashboard')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/dashboard.svg') }}" alt="">
                </span>
                <span class="nav-label">Dashboard</span>
            </a>

            {{-- Ingresos --}}
            <a href="{{ route('mt2.finance.index', 'ingresos') }}"
            class="{{ request()->is('mt2/ingresos*') ? 'current' : '' }}"
            @if(request()->is('mt2/ingresos*')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/ingresos.svg') }}" alt="">
                </span>
                <span class="nav-label">Ingresos</span>
            </a>

            {{-- Gastos --}}
            <a href="{{ route('mt2.finance.index', 'gastos') }}"
            class="{{ request()->is('mt2/gastos*') ? 'current' : '' }}"
            @if(request()->is('mt2/gastos*')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/gastos.svg') }}" alt="">
                </span>
                <span class="nav-label">Gastos</span>
            </a>

            {{-- Categorías --}}
            <a href="{{ route('mt2.finance.index', 'categorias') }}"
            class="{{ request()->is('mt2/categorias*') ? 'current' : '' }}"
            @if(request()->is('mt2/categorias*')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/categorias.svg') }}" alt="">
                </span>
                <span class="nav-label">Categorías</span>
            </a>

            {{-- Presupuestos --}}
            <a href="{{ route('mt2.finance.index', 'presupuestos') }}"
            class="{{ request()->is('mt2/presupuestos*') ? 'current' : '' }}"
            @if(request()->is('mt2/presupuestos*')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/presupuestos.svg') }}" alt="">
                </span>
                <span class="nav-label">Presupuestos</span>
            </a>

            {{-- Reportes --}}
            <a href="{{ route('mt2.finance.report') }}"
            class="{{ request()->is('mt2/reportes*') ? 'current' : '' }}"
            @if(request()->is('mt2/reportes*')) aria-current="page" @endif>
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/reportes.svg') }}" alt="">
                </span>
                <span class="nav-label">Reportes</span>
            </a>

            {{-- Usuarios --}}
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('mt2.users.index') }}"
                    class="{{ request()->is('mt2/usuarios*') ? 'current' : '' }}">

                        <span class="symbol">
                            <img class="duotone-icon"
                                src="{{ asset('mt2/icons/usuarios.svg') }}"
                                alt="">
                        </span>

                        <span class="nav-label">Usuarios</span>
                    </a>
                @endif
            @endauth

            {{-- Configuración --}}
            <a href="{{ route('mt2.settings.index') }}"
                class="{{ request()->is('mt2/configuracion*') ? 'current' : '' }}">
                <span class="symbol">
                    <img class="duotone-icon"
                        src="{{ asset('mt2/icons/configuracion.svg') }}" alt="">
                </span>
                <span class="nav-label">Configuración</span>
            </a>

        </nav>

        <div class="side-note">TU DINERO, TU CONTROL.<br>Mejores decisiones financieras.</div>
    </aside>
    <main>
        <div class="top">
            <div class="top-start"><button class="icon-button menu-toggle" id="openSidebar" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú">☰</button>
                <div class="search"><span aria-hidden="true">⌕</span><span class="search-label">Buscar movimientos, categorías...</span></div>
            </div>
            <div class="profile"><button class="btn theme-button" id="theme" type="button" aria-label="Cambiar tema">☼ <span>Tema</span></button>
                <a href="{{ route('mt2.profile.edit') }}"
                class="mt2-account-link"
                title="Administrar mi cuenta">

                    <span class="mt2-account-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </span>

                    <span class="mt2-account-label">Mi cuenta</span>

                    <span class="mt2-account-chevron" aria-hidden="true">›</span>
                </a>
                <form method="POST"
                    action="{{ route('logout') }}"
                    class="mt2-logout-form">
                    @csrf

                    <button type="submit" class="mt2-logout-button">
                        <span aria-hidden="true">⏻</span>
                        <!-- <span class="mt2-logout-text">Cerrar sesión</span> -->
                    </button>
                </form>
            </div>
        </div>
        <div class="content finance-content">@yield('content')</div>
    </main><button id="backToTop" class="back-to-top" type="button" aria-label="Volver al inicio" title="Volver al inicio" hidden>↑</button>
    <script src="{{ asset('mt2/js/dashboard.js') }}?v=12" defer></script>
    <script src="{{ asset('mt2/js/dashboard-live.js') }}" defer></script>
</body>

</html>
