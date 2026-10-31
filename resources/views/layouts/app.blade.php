<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Inicio') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f8; }
        .navbar-brand { font-weight: 700; letter-spacing: .02em; }
        .navbar .nav-link { white-space: nowrap; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .kpi { font-size: 2rem; font-weight: 700; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
@auth
<nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ route('panel') }}"><i class="bi bi-shop"></i> El Portal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Mostrar menú"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="menu">
            @php($u = auth()->user())
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('panel')) active @endif" href="{{ route('panel') }}"><i class="bi bi-speedometer2"></i> Panel</a></li>
                @if($u->tieneRol('administrador', 'vendedor'))
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('ventas.*')) active @endif" href="{{ route('ventas.create') }}"><i class="bi bi-cart"></i> Venta</a></li>
                @endif
                <li class="nav-item"><a class="nav-link @if(request()->routeIs('productos.*')) active @endif" href="{{ route('productos.index') }}"><i class="bi bi-box-seam"></i> Productos</a></li>
                @if($u->tieneRol('administrador', 'bodeguero'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if(request()->routeIs('recepciones.*', 'alertas.*', 'lotes.*', 'mermas.*', 'conteos.*', 'movimientos.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-boxes"></i> Inventario</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item @if(request()->routeIs('recepciones.*')) active @endif" href="{{ route('recepciones.index') }}"><i class="bi bi-box-arrow-in-down"></i> Recepciones</a></li>
                            <li><a class="dropdown-item @if(request()->routeIs('alertas.*')) active @endif" href="{{ route('alertas.index') }}"><i class="bi bi-bell"></i> Alertas de reorden</a></li>
                            <li><a class="dropdown-item @if(request()->routeIs('lotes.*')) active @endif" href="{{ route('lotes.por-vencer') }}"><i class="bi bi-hourglass-split"></i> Lotes por vencer</a></li>
                            <li><a class="dropdown-item @if(request()->routeIs('mermas.*')) active @endif" href="{{ route('mermas.index') }}"><i class="bi bi-trash3"></i> Mermas</a></li>
                            <li><a class="dropdown-item @if(request()->routeIs('conteos.*')) active @endif" href="{{ route('conteos.index') }}"><i class="bi bi-clipboard-data"></i> Conteos</a></li>
                            @if($u->tieneRol('administrador'))
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item @if(request()->routeIs('movimientos.*')) active @endif" href="{{ route('movimientos.index') }}"><i class="bi bi-journal-text"></i> Bitácora</a></li>
                            @endif
                        </ul>
                    </li>
                @endif
                @if($u->tieneRol('administrador'))
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle @if(request()->routeIs('ordenes-compra.*', 'proveedores.*')) active @endif" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-truck"></i> Compras</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item @if(request()->routeIs('ordenes-compra.*')) active @endif" href="{{ route('ordenes-compra.index') }}"><i class="bi bi-clipboard-check"></i> Órdenes de compra</a></li>
                            <li><a class="dropdown-item @if(request()->routeIs('proveedores.*')) active @endif" href="{{ route('proveedores.index') }}"><i class="bi bi-building"></i> Proveedores</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('indicadores.*')) active @endif" href="{{ route('indicadores.index') }}"><i class="bi bi-graph-up"></i> Indicadores</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('usuarios.*')) active @endif" href="{{ route('usuarios.index') }}"><i class="bi bi-people"></i> Usuarios</a></li>
                @endif
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle"></i> {{ $u->name }}</a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">Rol: <span class="badge bg-success">{{ $u->rol }}</span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Salir</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
@endauth

<main class="container pb-5">
    @if(session('ok'))
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('ok') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('contenido')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
