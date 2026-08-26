@extends('layouts.app')

@section('title', 'Categorías - Lizz Glamour')
@section('header_title', 'Categorías')

@section('content')
<style>
    .inventory-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
    }
    .inventory-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .inventory-title i {
        color: var(--primary-color);
        font-size: 1.5rem;
    }
    .inventory-actions {
        display: flex;
        gap: 0.75rem;
    }
    .btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        text-decoration: none;
        border: none;
        transition: all 0.2s ease;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: #FFFFFF;
    }
    .btn-primary:hover {
        background-color: var(--primary-hover);
    }
    .btn-outline {
        background-color: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    .btn-outline:hover {
        background-color: var(--bg-color);
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .filter-group label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
    }
    .form-control {
        width: 100%;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
        font-family: inherit;
        color: var(--text-primary);
        background-color: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }
    .table th, .table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    .table th {
        font-weight: 600;
        color: var(--text-secondary);
        background-color: var(--bg-color);
    }
    .table tbody tr {
        transition: background-color 0.2s ease;
    }
    .table tbody tr:hover {
        background-color: #FAFAFA;
    }
    .alert-error {
        background-color: #fde8e8;
        color: #D64545;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-weight: 500;
    }
</style>

<div class="card">
    @if(session('error'))
        <div class="alert-error">
            <i class='bx bx-error-circle'></i> {{ session('error') }}
        </div>
    @endif

    <div class="inventory-header">
        <div class="inventory-title">
            <i class='bx bx-category'></i>
            <span>Gestión de Categorías</span>
        </div>
        <div class="inventory-actions">
            <a href="{{ route('categories.create') }}" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> Nueva Categoría
            </a>
        </div>
    </div>

    <form name="filter-data" method="GET" action="{{ route('categories.index') }}">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Buscar categoría</label>
                <div style="position: relative;">
                    <i class='bx bx-search' style="position: absolute; left: 10px; top: 10px; color: var(--text-secondary); font-size: 1.25rem;"></i>
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Nombre..." style="padding-left: 2.5rem;">
                </div>
            </div>
            
            <div class="filter-group" style="justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="width: fit-content; padding: 0.6rem 1.5rem;">
                    <i class='bx bx-filter-alt'></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nombre</th>
                    <th>Fecha de Creación</th>
                    <th style="text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td style="font-weight: 600; color: var(--text-secondary);">#{{ $category->id }}</td>
                    <td style="font-weight: 600;">{{ $category->name }}</td>
                    <td style="color: var(--text-secondary);">
                        {{ \Carbon\Carbon::parse($category->created_at)->format('d/m/Y') }}
                    </td>
                    <td style="text-align: center;">
                        <a href="{{ route('categories.edit', $category->id) }}" style="color: var(--text-secondary); margin-right: 10px; text-decoration: none;" title="Editar">
                            <i class='bx bx-edit' style="font-size: 1.25rem;"></i>
                        </a>
                        <form action="{{ route('categories.destroy', $category->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta categoría? Solo podrás hacerlo si no tiene productos asociados.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:none; border:none; color: #D64545; cursor:pointer;" title="Eliminar">
                                <i class='bx bx-trash' style="font-size: 1.25rem;"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                        <i class='bx bx-info-circle' style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--border-color);"></i>
                        <br>
                        No se encontraron categorías.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
