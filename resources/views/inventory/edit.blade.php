@extends('layouts.app')

@section('title', 'Editar Producto - Lizz Glamour')
@section('header_title', 'Inventario > Editar Producto')

@section('content')
<style>
    /* Estilos del Wizard */
    .wizard-header {
        display: flex;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 2rem;
    }
    .wizard-step {
        flex: 1;
        text-align: center;
        padding: 1rem;
        font-weight: 600;
        color: var(--text-secondary);
        position: relative;
        transition: color 0.3s;
    }
    .wizard-step.active {
        color: var(--primary-color);
    }
    .wizard-step.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: var(--primary-color);
    }

    /* Ocultar pasos inactivos */
    .step-content {
        display: none;
        animation: fadeIn 0.4s ease;
    }
    .step-content.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Estilos de formulario */
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-primary);
        font-size: 0.875rem;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        font-family: inherit;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    
    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    /* Estilos de Tabla Excel */
    .excel-container {
        overflow-x: auto;
        border: 1px solid var(--border-color);
        border-radius: 8px;
    }
    .excel-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Inter', sans-serif;
    }
    .excel-table th, .excel-table td {
        border: 1px solid var(--border-color);
        padding: 0;
        min-width: 100px;
    }
    .excel-table th {
        background-color: var(--bg-color);
        color: var(--text-secondary);
        font-weight: 600;
        padding: 0.75rem;
        text-align: center;
        font-size: 0.875rem;
    }
    .excel-table td:first-child {
        background-color: var(--bg-color);
        color: var(--text-primary);
        font-weight: 600;
        padding: 0.75rem;
        text-align: center;
        width: 150px;
        font-size: 0.875rem;
    }
    .excel-input {
        width: 100%;
        height: 100%;
        min-height: 45px;
        border: none;
        padding: 0.5rem;
        text-align: right;
        outline: none;
        font-size: 0.95rem;
        color: var(--text-primary);
        background: transparent;
        transition: background-color 0.2s;
    }
    .excel-input:focus {
        background-color: var(--primary-soft);
        box-shadow: inset 0 0 0 2px var(--primary-color);
    }
    .excel-input:hover:not(:focus) {
        background-color: #FAFAFA;
    }

    /* Botones */
    .wizard-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
    }
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }
    .btn-outline {
        background-color: transparent;
        border: 1px solid var(--border-color);
        color: var(--text-primary);
    }
    .btn-outline:hover {
        background-color: var(--bg-color);
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: #fff;
    }
    .btn-primary:hover {
        background-color: var(--primary-hover);
    }
    .btn-success {
        background-color: var(--success);
        color: #fff;
    }
    .btn-success:hover {
        background-color: #1a8055;
    }
</style>

<div class="card">
    <div class="wizard-header">
        <div class="wizard-step active" id="header-step-1">1. Información del Producto</div>
        <div class="wizard-step" id="header-step-2">2. Cantidades (Inventario)</div>
    </div>

    <form id="productForm" action="{{ route('inventory.update', $product->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <!-- PASO 1 -->
        <div class="step-content active" id="step-1">
            <h3 style="margin-bottom: 1.5rem; font-size: 1.125rem;">Datos Generales</h3>
            
            <div class="grid-2">
                <div class="form-group">
                    <label for="sku">SKU del Producto</label>
                    <input type="text" class="form-control" id="sku" name="sku" value="{{ $product->sku }}" placeholder="Ej: ZAP-1234" required>
                </div>
                <div class="form-group">
                    <label for="category_id">Categoría</label>
                    <select class="form-control" id="category_id" name="category_id" required>
                        <option value="">Selecciona una categoría...</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ $product->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="image">Imagen del Producto (Opcional - sube una para reemplazarla)</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*" style="padding: 0.5rem;">
                @if($product->image)
                    <div style="margin-top: 0.5rem;">
                        <img src="{{ asset($product->image) }}" alt="Imagen actual" style="height: 60px; border-radius: 4px; border: 1px solid var(--border-color);">
                    </div>
                @endif
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="colors">Colores Disponibles (Selección múltiple)</label>
                    <select class="form-control select2-multiple" id="colors" name="colors[]" multiple required data-placeholder="Agrega colores...">
                        @foreach($colors as $color)
                            <option value="{{ $color->id }}" {{ in_array($color->id, $selectedColors) ? 'selected' : '' }}>{{ $color->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sizes">Tallas Disponibles (Selección múltiple)</label>
                    <select class="form-control select2-multiple" id="sizes" name="sizes[]" multiple required data-placeholder="Agrega tallas...">
                        @foreach($sizes as $size)
                            <option value="{{ $size->id }}" {{ in_array($size->id, $selectedSizes) ? 'selected' : '' }}>{{ $size->size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- PASO 2 -->
        <div class="step-content" id="step-2">
            <h3 style="margin-bottom: 0.5rem; font-size: 1.125rem;">Ingreso de Inventario</h3>
            <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.875rem;">
                Revisa y actualiza las cantidades.
            </p>
            
            <div class="excel-container">
                <table class="excel-table">
                    <thead>
                        <tr id="excel-header-row">
                            <!-- Se generará dinámicamente -->
                        </tr>
                    </thead>
                    <tbody id="excel-body">
                        <!-- Se generará dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wizard-actions">
            <div>
                <button type="button" class="btn btn-outline" id="btnPrev" style="display: none;">Anterior</button>
            </div>
            <div>
                <button type="button" class="btn btn-primary" id="btnNext">Siguiente</button>
                <button type="submit" class="btn btn-success" id="btnSubmit" style="display: none;">Guardar Cambios</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    <script>
        // Inyectar el inventario existente a Javascript
        window.existingInventory = @json($existingInventory);
    </script>
    <script src="{{ asset('js/inventory/edit.js') }}?v={{ time() }}"></script>
@endsection
