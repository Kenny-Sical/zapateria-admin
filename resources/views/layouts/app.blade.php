<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lizz Glamour')</title>
    
    <!-- Fuentes e Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* Paleta de colores Lizz Glamour */
        :root {
            --bg-color: #F7F7F8;
            --surface-color: #FFFFFF;
            --text-primary: #1F1F1F;
            --text-secondary: #6B6B6B;
            --primary-color: #D91470;
            --primary-hover: #C51668;
            --primary-soft: #FCE7F0;
            --border-color: #E5E5E7;
            --success: #22A06B;
            --warning: #E6A21A;
            --error: #D64545;
            
            --font-family: 'Inter', system-ui, -apple-system, sans-serif;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            color: var(--text-primary);
            display: flex;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Sidebar Base */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--surface-color);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: width 0.3s ease;
            overflow-x: hidden;
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .brand span {
            transition: opacity 0.3s;
        }

        /* Menú de Navegación */
        .nav-menu {
            padding: 1.5rem 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .nav-link i {
            font-size: 1.35rem;
            transition: color 0.2s ease;
            min-width: 1.35rem;
        }

        .nav-link:hover {
            background-color: var(--bg-color);
            color: var(--primary-color);
        }

        .nav-link.active {
            background-color: var(--primary-soft);
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Contenedor Principal */
        .main-wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Sidebar Colapsado */
        body.sidebar-collapsed .sidebar {
            width: var(--sidebar-collapsed-width);
        }
        body.sidebar-collapsed .main-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }
        body.sidebar-collapsed .brand span, 
        body.sidebar-collapsed .nav-link span {
            opacity: 0;
            display: none;
        }
        body.sidebar-collapsed .sidebar-header {
            justify-content: center;
            padding: 0;
        }
        body.sidebar-collapsed .nav-link {
            justify-content: center;
            padding: 0.875rem 0;
        }

        /* Header Superior */
        .header {
            height: var(--header-height);
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .toggle-sidebar:hover {
            background-color: var(--bg-color);
            color: var(--primary-color);
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background-color: var(--primary-soft);
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .btn-logout {
            background: none;
            border: none;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 0.5rem;
            border-radius: 6px;
        }

        .btn-logout:hover {
            color: var(--primary-hover);
            background-color: var(--primary-soft);
        }

        .btn-logout i {
            font-size: 1.25rem;
        }

        /* Área de contenido */
        .content {
            padding: 2rem;
            flex: 1;
        }

        /* Utilidades generales */
        .card {
            background-color: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        /* Select2 Theme Override Lizz Glamour */
        .select2-container--default .select2-selection--multiple {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-height: 42px;
            padding: 4px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: var(--primary-soft);
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 4px;
            margin-top: 4px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: var(--primary-color);
            border-right: 1px solid var(--primary-color);
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>

    <!-- Menú Lateral (Sidebar) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="{{ route('dashboard') }}" class="brand">
                <i class='bx bx-store'></i>
                <span>Lizz Glamour</span>
            </a>
        </div>
        <nav class="nav-menu">
            @if(auth()->check() && auth()->user()->role === 0)
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                <i class='bx bx-grid-alt'></i>
                <span>Dashboard</span>
            </a>
            @endif
            
            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" title="Inventario">
                <i class='bx bx-package'></i>
                <span>Inventario</span>
            </a>
            
            @if(auth()->check() && auth()->user()->role === 0)
            <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" title="Categorías">
                <i class='bx bx-category'></i>
                <span>Categorías</span>
            </a>

            <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" title="Usuarios">
                <i class='bx bx-group'></i>
                <span>Usuarios</span>
            </a>
            @endif
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <div class="main-wrapper">
        <!-- Encabezado (Header) -->
        <header class="header">
            <div class="header-title">
                <button id="toggleSidebar" class="toggle-sidebar">
                    <i class='bx bx-menu'></i>
                </button>
                @yield('header_title', 'Dashboard')
            </div>
            
            <div class="header-user">
                <div class="user-info">
                    <div class="user-avatar">
                        {{ strtoupper(substr(Auth::user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="user-details">
                        <span class="user-name">{{ Auth::user()->name ?? 'Administrador' }}</span>
                        <span class="user-role">{{ Auth::user()->email ?? 'admin@lizzglamour.com' }}</span>
                    </div>
                </div>
                
                <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-logout" title="Cerrar sesión">
                        <i class='bx bx-log-out'></i>
                    </button>
                </form>
            </div>
        </header>

        <!-- Contenido Específico de cada módulo -->
        <main class="content">
            @yield('content')
        </main>
    </div>

    <!-- Lógica del Sidebar -->
    <script>
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    </script>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    @if(session('success'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: '{{ session("success") }}',
                confirmButtonColor: 'var(--primary-color)'
            });
        });
    </script>
    @endif
    
    @yield('scripts')
</body>
</html>
