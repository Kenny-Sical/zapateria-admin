@extends('layouts.app')

@section('title', 'Inventario - Lizz Glamour')
@section('header_title', 'Inventario')

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
</style>

<div class="card">
    <div class="inventory-header">
        <div class="inventory-title">
            <i class='bx bx-package'></i>
            <span>Gestión de Inventario</span>
        </div>
        <div class="inventory-actions">
            <button class="btn btn-outline" id="btn_export">
                <i class='bx bx-export'></i> Exportar
            </button>
            <a href="{{ route('inventory.create') }}" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> Nuevo Producto
            </a>
        </div>
    </div>

    <form name="filter-data" method="GET" action="{{ route('inventory.index') }}">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Buscar producto</label>
                <div style="position: relative;">
                    <i class='bx bx-search' style="position: absolute; left: 10px; top: 10px; color: var(--text-secondary); font-size: 1.25rem;"></i>
                    <input type="text" class="form-control" name="search" value="{{ request('search') }}" placeholder="Nombre o SKU..." style="padding-left: 2.5rem;">
                </div>
            </div>
            
            <div class="filter-group">
                <label>Color</label>
                <select class="form-control select2-multiple" name="colors[]" multiple data-placeholder="Seleccionar colores...">
                    @foreach($colors as $color)
                        <option value="{{ $color->id }}" {{ in_array($color->id, request('colors', [])) ? 'selected' : '' }}>{{ $color->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="filter-group">
                <label>Talla</label>
                <select class="form-control select2-multiple" name="sizes[]" multiple data-placeholder="Seleccionar tallas...">
                    @foreach($sizes as $size)
                        <option value="{{ $size->id }}" {{ in_array($size->id, request('sizes', [])) ? 'selected' : '' }}>{{ $size->size }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="filter-group">
                <label>Categoría</label>
                <select class="form-control select2-multiple" name="categories[]" multiple data-placeholder="Seleccionar categorías...">
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ in_array($category->id, request('categories', [])) ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="filter-group" style="display: flex; align-items: flex-end; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class='bx bx-search'></i> Filtrar</button>
                <a href="{{ route('inventory.index') }}" class="btn btn-outline" style="flex: 1; text-align: center; padding: 0.75rem;"><i class='bx bx-x'></i> Limpiar</a>
            </div>
        </div>
    </form>
    
    <div class="table-responsive">
        <table class="table" style="margin-top: 1.5rem;">
            <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th style="width: 60px;">Imagen</th>
                    <th>SKU</th>
                    <th>Categoría</th>
                    <th>Total Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <!-- Fila Principal (Producto) -->
                <tr class="main-row" data-product-id="{{ $product->id }}" style="cursor: pointer; transition: background-color 0.2s; {{ !$product->is_active ? 'opacity: 0.6;' : '' }}">
                    <td style="text-align: center; vertical-align: middle;">
                        <i class='bx bx-chevron-right toggle-icon' id="icon-{{ $product->id }}" style="font-size: 1.5rem; color: var(--text-secondary);"></i>
                    </td>
                    <td>
                        @if($product->image)
                            <img src="{{ asset($product->image) }}" alt="SKU {{ $product->sku }}" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                        @else
                            <div style="width: 45px; height: 45px; background-color: var(--border-color); border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                <i class='bx bx-image' style="color: var(--text-secondary);"></i>
                            </div>
                        @endif
                    </td>
                    <td style="font-weight: 600; vertical-align: middle;">
                        {{ $product->sku }}
                        @if(!$product->is_active)
                            <span style="font-size: 0.75rem; background-color: var(--border-color); color: var(--text-secondary); padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.5rem; font-weight: normal;">Inactivo</span>
                        @endif
                    </td>
                    <td style="vertical-align: middle;">
                        <span style="background-color: var(--primary-soft); color: var(--primary-color); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.85rem; font-weight: 500;">
                            {{ $product->category_name }}
                        </span>
                    </td>
                    <td style="vertical-align: middle; font-weight: 600; color: {{ $product->total_stock > 0 ? 'var(--success)' : 'var(--error)' }};">
                        {{ $product->total_stock }} uds
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="{{ route('inventory.edit', $product->id) }}" class="btn btn-outline" style="padding: 0.4rem 0.6rem;" onclick="event.stopPropagation();" title="Editar"><i class='bx bx-edit' style="margin:0;"></i></a>
                            
                            <form action="{{ route('inventory.toggle-status', $product->id) }}" method="POST" style="margin: 0;" onclick="event.stopPropagation();">
                                @csrf
                                @method('PUT')
                                @if($product->is_active)
                                    <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.6rem; color: var(--warning);" title="Deshabilitar">
                                        <i class='bx bx-hide' style="margin:0;"></i>
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.6rem; color: var(--success);" title="Habilitar">
                                        <i class='bx bx-show' style="margin:0;"></i>
                                    </button>
                                @endif
                            </form>

                            @if(!$product->is_active)
                            <form action="{{ route('inventory.destroy', $product->id) }}" method="POST" style="margin: 0;" onclick="event.stopPropagation();" onsubmit="return confirm('¿Eliminar producto permanentemente?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.6rem; color: var(--error);" title="Eliminar"><i class='bx bx-trash' style="margin:0;"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                
                <!-- Fila Detalle (Inventario) -->
                <tr class="detail-row" id="detail-{{ $product->id }}" style="display: none;">
                    <td colspan="6" style="padding: 0; border-top: none; border-bottom: 2px solid var(--border-color);">
                        <div style="padding: 1.5rem 2rem; background-color: var(--bg-color); box-shadow: inset 0 3px 5px -5px rgba(0,0,0,0.1);">
                            <div style="display: flex; align-items: center; margin-bottom: 1rem; gap: 0.5rem;">
                                <i class='bx bx-list-ul' style="color: var(--primary-color); font-size: 1.25rem;"></i>
                                <h5 style="margin: 0; font-size: 0.95rem; color: var(--text-primary); font-weight: 600;">Desglose de Inventario</h5>
                            </div>
                            
                            <table class="table" style="background-color: #fff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; margin: 0; text-align: center;">
                                <thead>
                                    <tr style="background-color: #FAFAFA;">
                                        <th style="padding: 0.75rem 1rem; text-align: left; width: 120px;">Color \ Talla</th>
                                        @foreach($product->matrix_sizes as $size_id => $size_name)
                                            <th style="padding: 0.75rem 1rem; text-align: center;">{{ $size_name }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($product->matrix_data as $color_name => $sizes_data)
                                    <tr>
                                        <td style="padding: 0.75rem 1rem; text-align: left; font-weight: 600;">{{ $color_name }}</td>
                                        @foreach($product->matrix_sizes as $size_id => $size_name)
                                            <td style="padding: 0.75rem 1rem; text-align: center; color: {{ isset($sizes_data[$size_id]) && $sizes_data[$size_id] > 0 ? 'var(--text-primary)' : 'var(--text-secondary)' }};">
                                                {{ isset($sizes_data[$size_id]) ? $sizes_data[$size_id] . ' uds' : '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="100%" style="text-align: center; padding: 1rem; color: var(--text-secondary);">
                                            No hay stock registrado que coincida con estos filtros.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 3rem;">
                        <i class='bx bx-info-circle' style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: var(--primary-color);"></i>
                        No hay registros para mostrar. Utiliza los filtros o agrega un nuevo producto.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('.select2-multiple').select2({
            width: '100%'
        });

        // Lógica para expandir/contraer filas
        $('.main-row').on('click', function() {
            const productId = $(this).data('product-id');
            const detailRow = $('#detail-' + productId);
            const icon = $('#icon-' + productId);

            if (detailRow.is(':visible')) {
                detailRow.hide();
                icon.removeClass('bx-chevron-down').addClass('bx-chevron-right');
                $(this).css('background-color', '');
            } else {
                // Opcional: Ocultar las otras filas si solo quieres una abierta a la vez
                // $('.detail-row').hide();
                // $('.toggle-icon').removeClass('bx-chevron-down').addClass('bx-chevron-right');
                // $('.main-row').css('background-color', '');

                detailRow.show();
                icon.removeClass('bx-chevron-right').addClass('bx-chevron-down');
                $(this).css('background-color', 'var(--primary-soft)');
            }
        });
    });
</script>
@endsection
