@extends('layouts.app')

@section('title', 'Seguimiento de Ventas - Lizz Glamour')
@section('header_title', 'Seguimiento de Ventas')

@section('content')
<style>
    /* KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 1200px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 576px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }

    .kpi-card {
        background-color: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .kpi-icon {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }
    .kpi-icon-pink {
        background-color: var(--primary-soft);
        color: var(--primary-color);
    }
    .kpi-icon-green {
        background-color: #E8F5E9;
        color: var(--success);
    }
    .kpi-icon-warning {
        background-color: #FFF8E1;
        color: var(--warning);
    }
    .kpi-info {
        display: flex;
        flex-direction: column;
    }
    .kpi-label {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.5px;
        margin-top: 0.2rem;
    }

    /* Orders Section Header */
    .orders-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
    }
    .orders-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }
    .orders-title i {
        color: var(--primary-color);
        font-size: 1.5rem;
    }

    /* Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
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
    .btn-sm {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
        border-radius: 6px;
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
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    /* Filters */
    .filters-grid {
        display: grid;
        grid-template-columns: 2fr 1.5fr auto;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: flex-end;
    }
    @media (max-width: 768px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }
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

    /* Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.925rem;
    }
    .table th, .table td {
        padding: 1rem 0.85rem;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    .table th {
        font-weight: 600;
        color: var(--text-secondary);
        background-color: var(--bg-color);
        white-space: nowrap;
    }
    .table tbody tr.order-main-row {
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .table tbody tr.order-main-row:hover {
        background-color: #FAFAFA;
    }
    .table tbody tr.order-main-row.row-expanded {
        background-color: var(--primary-soft);
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        white-space: nowrap;
    }
    .status-completed {
        background-color: #E8F5E9;
        color: var(--success);
        border: 1px solid rgba(34, 160, 107, 0.25);
    }
    .status-processing {
        background-color: var(--primary-soft);
        color: var(--primary-color);
        border: 1px solid rgba(217, 20, 112, 0.25);
    }
    .status-on-hold, .status-pending {
        background-color: #FFF8E1;
        color: var(--warning);
        border: 1px solid rgba(230, 162, 26, 0.25);
    }
    .status-cancelled, .status-failed {
        background-color: #FFEBEE;
        color: var(--error);
        border: 1px solid rgba(214, 69, 69, 0.25);
    }
    .status-refunded {
        background-color: #F7F7F8;
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }

    /* Accordion Row Details */
    .order-detail-row {
        display: none;
        background-color: #FCFCFD;
    }
    .order-detail-row.is-open {
        display: table-row;
    }
    .order-detail-card {
        padding: 1.25rem;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background-color: #FFFFFF;
        margin: 0.5rem 0 1rem 0;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .detail-item h4 {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-secondary);
        margin-bottom: 0.35rem;
    }
    .detail-item p {
        font-size: 0.9rem;
        color: var(--text-primary);
        line-height: 1.4;
        margin: 0.2rem 0;
    }

    /* Line items table inside details */
    .line-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        margin-top: 0.75rem;
    }
    .line-items-table th, .line-items-table td {
        padding: 0.6rem 0.75rem;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
    }
    .line-items-table th {
        background-color: var(--bg-color);
        color: var(--text-secondary);
        font-weight: 600;
    }

    /* Quick status form inside detail */
    .status-update-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-top: 1rem;
        margin-top: 1rem;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
    }

    /* Modal Styles */
    .custom-modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(31, 31, 31, 0.5);
        z-index: 1050;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        box-sizing: border-box;
    }
    .custom-modal-backdrop.show {
        display: flex;
    }
    .custom-modal {
        background-color: var(--surface-color);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        width: 100%;
        max-width: 720px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
        animation: modalFadeIn 0.2s ease-out;
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.96); }
        to { opacity: 1; transform: scale(1); }
    }
    .custom-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .custom-modal-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-close-modal:hover {
        background-color: var(--bg-color);
        color: var(--primary-color);
    }
    .custom-modal-body {
        padding: 1.5rem;
        overflow-y: auto;
    }
    .custom-modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: var(--bg-color);
        border-radius: 0 0 12px 12px;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1.5rem;
        margin-top: 1.5rem;
        border-top: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 1rem;
    }
    .pagination-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
    .pagination-info strong {
        color: var(--text-primary);
        font-weight: 600;
    }
    .pagination-list {
        display: flex;
        list-style: none;
        gap: 0.35rem;
        align-items: center;
        margin: 0;
        padding: 0;
    }
    .pagination-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 0.5rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--surface-color);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    .pagination-btn:hover:not(.disabled):not(.active) {
        border-color: var(--primary-color);
        color: var(--primary-color);
        background-color: var(--primary-soft);
    }
    .pagination-btn.active {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #FFFFFF;
        font-weight: 600;
    }
    .pagination-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
</style>

<!-- Tarjetas KPI Resumen -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-pink">
            <i class='bx bx-shopping-bag'></i>
        </div>
        <div class="kpi-info">
            <span class="kpi-label">Total Pedidos</span>
            <span class="kpi-value">{{ number_format($metrics['total_orders'] ?? 0) }}</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-green">
            <i class='bx bx-dollar-circle'></i>
        </div>
        <div class="kpi-info">
            <span class="kpi-label">Total Ingresos (Pág.)</span>
            <span class="kpi-value">Q {{ number_format($metrics['total_sales'] ?? 0, 2) }}</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-green">
            <i class='bx bx-check-circle'></i>
        </div>
        <div class="kpi-info">
            <span class="kpi-label">Completados</span>
            <span class="kpi-value">{{ number_format($metrics['completed_count'] ?? 0) }}</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon kpi-icon-warning">
            <i class='bx bx-time-five'></i>
        </div>
        <div class="kpi-info">
            <span class="kpi-label">Pendientes / En Proceso</span>
            <span class="kpi-value">{{ number_format($metrics['pending_processing_count'] ?? 0) }}</span>
        </div>
    </div>
</div>

<!-- Card Principal de Pedidos -->
<div class="card">
    <div class="orders-header">
        <div class="orders-title">
            <i class='bx bx-cart'></i>
            <span>Historial y Seguimiento de Pedidos</span>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" action="{{ route('orders.index') }}">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Buscar pedido o cliente</label>
                <div style="position: relative;">
                    <i class='bx bx-search' style="position: absolute; left: 10px; top: 10px; color: var(--text-secondary); font-size: 1.25rem;"></i>
                    <input type="text" class="form-control" name="search" value="{{ $search ?? '' }}" placeholder="ID de pedido, nombre o correo..." style="padding-left: 2.5rem;">
                </div>
            </div>

            <div class="filter-group">
                <label>Estado del pedido</label>
                <select class="form-control" name="status">
                    <option value="all" {{ ($currentStatus ?? 'all') === 'all' ? 'selected' : '' }}>Todos los estados</option>
                    <option value="completed" {{ ($currentStatus ?? '') === 'completed' ? 'selected' : '' }}>Completado</option>
                    <option value="processing" {{ ($currentStatus ?? '') === 'processing' ? 'selected' : '' }}>Procesando</option>
                    <option value="on-hold" {{ ($currentStatus ?? '') === 'on-hold' ? 'selected' : '' }}>En espera</option>
                    <option value="pending" {{ ($currentStatus ?? '') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                    <option value="cancelled" {{ ($currentStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                    <option value="refunded" {{ ($currentStatus ?? '') === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
                    <option value="failed" {{ ($currentStatus ?? '') === 'failed' ? 'selected' : '' }}>Fallido</option>
                </select>
            </div>

            <div class="filter-group" style="display: flex; gap: 0.5rem; flex-direction: row;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class='bx bx-search'></i> Filtrar
                </button>
                <a href="{{ route('orders.index') }}" class="btn btn-outline" style="flex: 1;" title="Limpiar filtros">
                    <i class='bx bx-x'></i> Limpiar
                </a>
            </div>
        </div>
    </form>

    <!-- Tabla de Pedidos -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"></th>
                    <th style="width: 100px;"># Pedido</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Método de Pago</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th style="text-align: right; width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <!-- Fila Principal del Pedido -->
                <tr class="order-main-row" data-order-id="{{ $order->id }}">
                    <td style="text-align: center; vertical-align: middle;">
                        <i class='bx bx-chevron-right toggle-icon' id="icon-order-{{ $order->id }}" style="font-size: 1.3rem; color: var(--text-secondary); transition: transform 0.2s ease; display: inline-block;"></i>
                    </td>
                    <td style="font-weight: 700; color: var(--primary-color); vertical-align: middle;">
                        #{{ $order->number }}
                    </td>
                    <td style="vertical-align: middle; color: var(--text-secondary); font-size: 0.875rem; white-space: nowrap;">
                        {{ $order->formatted_date }}
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="font-weight: 600; color: var(--text-primary);">
                            {{ $order->customer_name }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                            {{ $order->customer_email }}
                            @if(!empty($order->customer_phone) && $order->customer_phone !== 'Sin teléfono')
                                &bull; {{ $order->customer_phone }}
                            @endif
                        </div>
                    </td>
                    <td style="vertical-align: middle; font-size: 0.875rem; color: var(--text-secondary);">
                        {{ $order->payment_method_title }}
                    </td>
                    <td style="vertical-align: middle; font-weight: 700; color: var(--text-primary); font-size: 0.95rem; white-space: nowrap;">
                        {{ $order->formatted_total }}
                    </td>
                    <td style="vertical-align: middle;">
                        <span class="status-badge {{ $order->status_badge_class }}">
                            {{ $order->status_label }}
                        </span>
                    </td>
                    <td style="vertical-align: middle; text-align: right; white-space: nowrap;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-outline btn-sm btn-open-modal" data-target="modal-order-{{ $order->id }}" title="Ver detalle completo">
                                <i class='bx bx-show'></i> Detalle
                            </button>
                        </div>
                    </td>
                </tr>

                <!-- Fila Acordeón con Desglose Rápido -->
                <tr class="order-detail-row" id="detail-order-{{ $order->id }}">
                    <td colspan="8" style="padding: 0 1rem 1rem 1rem;">
                        <div class="order-detail-card">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <h4>Cliente</h4>
                                    <p><strong>{{ $order->customer_name }}</strong></p>
                                    <p>{{ $order->customer_email }}</p>
                                    <p>{{ $order->customer_phone }}</p>
                                </div>
                                <div class="detail-item">
                                    <h4>Dirección de Entrega</h4>
                                    <p>{{ $order->customer_address }}</p>
                                </div>
                                <div class="detail-item">
                                    <h4>Resumen de Pago</h4>
                                    <p>Método: <strong>{{ $order->payment_method_title }}</strong></p>
                                    @if($order->shipping_total > 0)
                                        <p>Envío: Q {{ number_format($order->shipping_total, 2) }}</p>
                                    @endif
                                    @if($order->discount_total > 0)
                                        <p>Descuento: -Q {{ number_format($order->discount_total, 2) }}</p>
                                    @endif
                                    <p style="font-weight: 700; color: var(--primary-color); margin-top: 4px;">Total: {{ $order->formatted_total }}</p>
                                </div>
                            </div>

                            <!-- Tabla de Artículos del Pedido -->
                            <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                Artículos del Pedido ({{ count($order->line_items) }})
                            </h4>
                            <div class="table-responsive">
                                <table class="line-items-table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>SKU</th>
                                            <th>Variación</th>
                                            <th style="text-align: center;">Cantidad</th>
                                            <th style="text-align: right;">Precio Unit.</th>
                                            <th style="text-align: right;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($order->line_items as $item)
                                        <tr>
                                            <td style="font-weight: 500;">{{ $item->name }}</td>
                                            <td style="font-family: monospace; color: var(--text-secondary);">{{ $item->sku }}</td>
                                            <td>
                                                @if(!empty($item->variations_text))
                                                    <span style="background-color: var(--primary-soft); color: var(--primary-color); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.775rem;">
                                                        {{ $item->variations_text }}
                                                    </span>
                                                @else
                                                    <span style="color: var(--text-secondary); font-size: 0.8rem;">-</span>
                                                @endif
                                            </td>
                                            <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                                            <td style="text-align: right;">{{ $item->formatted_price }}</td>
                                            <td style="text-align: right; font-weight: 600;">{{ $item->formatted_total }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-secondary);">Sin artículos registrados en este pedido.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <!-- Formulario de Cambio Rápido de Estado -->
                            <div class="status-update-box">
                                <form action="{{ route('orders.update-status', $order->id) }}" method="POST" style="display: flex; align-items: center; gap: 0.75rem; width: 100%; flex-wrap: wrap;">
                                    @csrf
                                    @method('PUT')
                                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin: 0;">
                                        Cambiar Estado:
                                    </label>
                                    <select name="status" class="form-control" style="width: auto; min-width: 180px; padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                                        <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Completado</option>
                                        <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Procesando</option>
                                        <option value="on-hold" {{ $order->status === 'on-hold' ? 'selected' : '' }}>En espera</option>
                                        <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                        <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                                        <option value="refunded" {{ $order->status === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
                                        <option value="failed" {{ $order->status === 'failed' ? 'selected' : '' }}>Fallido</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class='bx bx-check'></i> Actualizar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                        <i class='bx bx-cart-alt' style="font-size: 2.5rem; color: var(--border-color); display: block; margin-bottom: 0.5rem;"></i>
                        No se encontraron pedidos registrados con los filtros aplicados.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Controles de Paginación -->
    @if(isset($orders) && $orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->total() > 0)
    <div class="pagination-container">
        <div class="pagination-info">
            Mostrando <strong>{{ $orders->firstItem() ?? 0 }}</strong> a <strong>{{ $orders->lastItem() ?? 0 }}</strong> de <strong>{{ $orders->total() }}</strong> pedidos
        </div>

        @if($orders->hasPages())
        <ul class="pagination-list">
            {{-- Botón Anterior --}}
            @if ($orders->onFirstPage())
                <li>
                    <span class="pagination-btn disabled" aria-disabled="true">
                        <i class='bx bx-chevron-left'></i>
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $orders->previousPageUrl() }}" class="pagination-btn" rel="prev" title="Página anterior">
                        <i class='bx bx-chevron-left'></i>
                    </a>
                </li>
            @endif

            {{-- Páginas numéricas --}}
            @foreach ($orders->getUrlRange(max(1, $orders->currentPage() - 2), min($orders->lastPage(), $orders->currentPage() + 2)) as $page => $url)
                @if ($page == $orders->currentPage())
                    <li>
                        <span class="pagination-btn active" aria-current="page">{{ $page }}</span>
                    </li>
                @else
                    <li>
                        <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                    </li>
                @endif
            @endforeach

            {{-- Botón Siguiente --}}
            @if ($orders->hasMorePages())
                <li>
                    <a href="{{ $orders->nextPageUrl() }}" class="pagination-btn" rel="next" title="Página siguiente">
                        <i class='bx bx-chevron-right'></i>
                    </a>
                </li>
            @else
                <li>
                    <span class="pagination-btn disabled" aria-disabled="true">
                        <i class='bx bx-chevron-right'></i>
                    </span>
                </li>
            @endif
        </ul>
        @endif
    </div>
    @endif
</div>

<!-- Modales de Detalle (Fuera de la tabla y del card para garantizar validez del DOM) -->
@if(isset($orders))
    @foreach($orders as $order)
    <div class="custom-modal-backdrop" id="modal-order-{{ $order->id }}">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <div class="custom-modal-title">
                    <i class='bx bx-receipt' style="color: var(--primary-color);"></i>
                    <span>Pedido #{{ $order->number }}</span>
                    <span class="status-badge {{ $order->status_badge_class }}" style="margin-left: 0.5rem;">
                        {{ $order->status_label }}
                    </span>
                </div>
                <button type="button" class="btn-close-modal" data-close="modal-order-{{ $order->id }}" title="Cerrar modal">
                    <i class='bx bx-x'></i>
                </button>
            </div>
            <div class="custom-modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <h4>Cliente & Contacto</h4>
                        <p><strong>{{ $order->customer_name }}</strong></p>
                        <p><i class='bx bx-envelope'></i> {{ $order->customer_email }}</p>
                        <p><i class='bx bx-phone'></i> {{ $order->customer_phone }}</p>
                    </div>
                    <div class="detail-item">
                        <h4>Envío & Facturación</h4>
                        <p><i class='bx bx-map'></i> {{ $order->customer_address }}</p>
                        <p style="margin-top: 0.35rem;"><i class='bx bx-calendar'></i> {{ $order->formatted_date }}</p>
                    </div>
                    <div class="detail-item">
                        <h4>Método de Pago</h4>
                        <p><strong>{{ $order->payment_method_title }}</strong></p>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.25rem;">Moneda: {{ $order->currency }}</p>
                    </div>
                </div>

                <h4 style="font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 0.5rem;">
                    Desglose de Productos
                </h4>
                <div class="table-responsive">
                    <table class="line-items-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>SKU</th>
                                <th>Variación</th>
                                <th style="text-align: center;">Cant.</th>
                                <th style="text-align: right;">Precio</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->line_items as $item)
                            <tr>
                                <td style="font-weight: 500;">{{ $item->name }}</td>
                                <td style="font-family: monospace; color: var(--text-secondary);">{{ $item->sku }}</td>
                                <td>
                                    @if(!empty($item->variations_text))
                                        <span style="background-color: var(--primary-soft); color: var(--primary-color); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.775rem;">
                                            {{ $item->variations_text }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">-</span>
                                    @endif
                                </td>
                                <td style="text-align: center; font-weight: 600;">{{ $item->quantity }}</td>
                                <td style="text-align: right;">{{ $item->formatted_price }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ $item->formatted_total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totales Financieros -->
                <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
                    <div style="width: 250px; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.875rem;">
                        @if($order->shipping_total > 0)
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary);">
                            <span>Envío:</span>
                            <span>Q {{ number_format($order->shipping_total, 2) }}</span>
                        </div>
                        @endif
                        @if($order->discount_total > 0)
                        <div style="display: flex; justify-content: space-between; color: var(--error);">
                            <span>Descuento:</span>
                            <span>-Q {{ number_format($order->discount_total, 2) }}</span>
                        </div>
                        @endif
                        @if($order->total_tax > 0)
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary);">
                            <span>Impuestos:</span>
                            <span>Q {{ number_format($order->total_tax, 2) }}</span>
                        </div>
                        @endif
                        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.05rem; color: var(--primary-color); border-top: 1px solid var(--border-color); padding-top: 0.5rem; margin-top: 0.25rem;">
                            <span>Total:</span>
                            <span>{{ $order->formatted_total }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <form action="{{ route('orders.update-status', $order->id) }}" method="POST" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    @csrf
                    @method('PUT')
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Estado:</span>
                    <select name="status" class="form-control" style="width: auto; min-width: 170px; padding: 0.4rem 0.8rem; font-size: 0.875rem;">
                        <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>Completado</option>
                        <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>Procesando</option>
                        <option value="on-hold" {{ $order->status === 'on-hold' ? 'selected' : '' }}>En espera</option>
                        <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                        <option value="refunded" {{ $order->status === 'refunded' ? 'selected' : '' }}>Reembolsado</option>
                        <option value="failed" {{ $order->status === 'failed' ? 'selected' : '' }}>Fallido</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class='bx bx-check'></i> Guardar Estado
                    </button>
                </form>
                <button type="button" class="btn btn-outline btn-sm btn-close-modal" data-close="modal-order-{{ $order->id }}">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    @endforeach
@endif
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Toggle de fila acordeón al hacer clic en la fila principal
        $('.order-main-row').on('click', function(e) {
            // Evitar toggle si se hizo clic en un botón, enlace o elemento interactivo
            if ($(e.target).closest('button, a, select, input, label').length) {
                return;
            }

            const orderId = $(this).data('order-id');
            const detailRow = $('#detail-order-' + orderId);
            const icon = $('#icon-order-' + orderId);

            if (detailRow.hasClass('is-open')) {
                detailRow.removeClass('is-open');
                icon.css('transform', 'rotate(0deg)');
                $(this).removeClass('row-expanded');
            } else {
                detailRow.addClass('is-open');
                icon.css('transform', 'rotate(90deg)');
                $(this).addClass('row-expanded');
            }
        });

        // Apertura de modales
        $(document).on('click', '.btn-open-modal', function(e) {
            e.stopPropagation();
            const targetId = $(this).data('target');
            $('#' + targetId).addClass('show');
            $('body').css('overflow', 'hidden');
        });

        // Cierre de modales con botón de cerrar
        $(document).on('click', '.btn-close-modal', function(e) {
            e.stopPropagation();
            const targetId = $(this).data('close');
            $('#' + targetId).removeClass('show');
            $('body').css('overflow', '');
        });

        // Cerrar modal al hacer clic en el backdrop
        $(document).on('click', '.custom-modal-backdrop', function(e) {
            if ($(e.target).hasClass('custom-modal-backdrop')) {
                $(this).removeClass('show');
                $('body').css('overflow', '');
            }
        });

        // Cerrar modal con la tecla Escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.custom-modal-backdrop.show').removeClass('show');
                $('body').css('overflow', '');
            }
        });
    });
</script>
@endsection
