<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Services\WooCommerceApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderController extends Controller
{
    /**
     * Mapeo de estados y sus etiquetas legibles.
     */
    protected const STATUS_MAP = [
        'completed'  => 'Completado',
        'processing' => 'Procesando',
        'on-hold'    => 'En espera',
        'pending'    => 'Pendiente',
        'cancelled'  => 'Cancelado',
        'refunded'   => 'Reembolsado',
        'failed'     => 'Fallido',
    ];

    /**
     * Muestra la vista principal de seguimiento de ventas / pedidos.
     */
    public function index(Request $request, WooCommerceApiService $wcApi)
    {
        $currentPage = (int) $request->input('page', 1);
        $perPage = 15;
        $currentStatus = $request->input('status', 'all');
        $search = $request->input('search', '');

        $params = [
            'page'     => $currentPage,
            'per_page' => $perPage,
        ];

        if ($request->filled('status') && $request->status !== 'all') {
            $params['status'] = $request->status;
        }

        if ($request->filled('search')) {
            $params['search'] = $request->search;
        }

        try {
            $ordersResult = $wcApi->getOrdersPaginated($params);
            $rawOrders = $ordersResult['data'] ?? [];
            $total = (int) ($ordersResult['total'] ?? count($rawOrders));

            // Calcular métricas resumen
            $totalSales = 0;
            $completedCount = 0;
            $pendingProcessingCount = 0;

            foreach ($rawOrders as $raw) {
                $totalSales += (float) ($raw['total'] ?? 0);
                $st = $raw['status'] ?? '';
                if ($st === 'completed') {
                    $completedCount++;
                } elseif (in_array($st, ['pending', 'processing', 'on-hold'], true)) {
                    $pendingProcessingCount++;
                }
            }

            $metrics = [
                'total_orders'             => $total,
                'total_sales'              => $totalSales,
                'completed_count'          => $completedCount,
                'pending_processing_count' => $pendingProcessingCount,
            ];

            // Formatear pedidos para la vista
            $formattedOrders = [];
            foreach ($rawOrders as $order) {
                $formattedOrders[] = $this->formatOrderForView($order);
            }

            $orders = new LengthAwarePaginator(
                $formattedOrders,
                $total,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('orders.read', compact('orders', 'metrics', 'currentStatus', 'search'));
        } catch (\Exception $e) {
            $emptyPaginator = new LengthAwarePaginator([], 0, $perPage, 1, ['path' => $request->url()]);
            $metrics = [
                'total_orders'             => 0,
                'total_sales'              => 0,
                'completed_count'          => 0,
                'pending_processing_count' => 0,
            ];

            return view('orders.read', [
                'orders'        => $emptyPaginator,
                'metrics'       => $metrics,
                'currentStatus' => $currentStatus,
                'search'        => $search,
            ])->with('error', 'Error al consultar pedidos en WooCommerce API: ' . $e->getMessage());
        }
    }

    /**
     * Actualiza el estado de un pedido en WooCommerce.
     */
    public function updateStatus(Request $request, $id, WooCommerceApiService $wcApi)
    {
        $request->validate([
            'status' => 'required|string|in:completed,processing,on-hold,pending,cancelled,refunded,failed',
        ]);

        try {
            $wcApi->updateOrderStatus((int) $id, $request->status);
            $statusLabel = self::STATUS_MAP[$request->status] ?? $request->status;

            return back()->with('success', "Estado del pedido #{$id} actualizado a '{$statusLabel}'.");
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar el estado del pedido: ' . $e->getMessage());
        }
    }

    /**
     * Formatea los datos de un pedido para consumo en las vistas Blade.
     */
    protected function formatOrderForView(array $order): object
    {
        $id = (int) ($order['id'] ?? 0);
        $number = (string) ($order['number'] ?? $id);
        $status = (string) ($order['status'] ?? 'pending');
        $currencySymbol = (string) ($order['currency_symbol'] ?? 'Q');
        $total = (float) ($order['total'] ?? 0);

        // Formatear fecha
        $dateFormatted = '-';
        if (!empty($order['date_created'])) {
            try {
                $dateFormatted = Carbon::parse($order['date_created'])->format('d/m/Y H:i');
            } catch (\Exception) {
                $dateFormatted = (string) $order['date_created'];
            }
        }

        // Datos del cliente
        $billing = $order['billing'] ?? [];
        $shipping = $order['shipping'] ?? [];

        $firstName = trim($billing['first_name'] ?? '');
        $lastName = trim($billing['last_name'] ?? '');
        $customerName = trim("{$firstName} {$lastName}");

        if ($customerName === '') {
            $shipFirst = trim($shipping['first_name'] ?? '');
            $shipLast = trim($shipping['last_name'] ?? '');
            $customerName = trim("{$shipFirst} {$shipLast}");
        }

        if ($customerName === '') {
            $customerName = 'Cliente Invitado';
        }

        $customerEmail = !empty($billing['email']) ? $billing['email'] : ($order['email'] ?? 'Sin correo');
        $customerPhone = !empty($billing['phone']) ? $billing['phone'] : 'Sin teléfono';

        // Dirección completa formateada
        $addressParts = array_filter([
            $billing['address_1'] ?? '',
            $billing['address_2'] ?? '',
            $billing['city'] ?? '',
            $billing['state'] ?? '',
            $billing['country'] ?? '',
        ]);
        $customerAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Dirección no especificada';

        // Badge class y label según estado
        $statusLabel = self::STATUS_MAP[$status] ?? ucfirst($status);
        $statusBadgeClass = match ($status) {
            'completed'  => 'status-completed',
            'processing' => 'status-processing',
            'on-hold'    => 'status-on-hold',
            'pending'    => 'status-pending',
            'cancelled'  => 'status-cancelled',
            'refunded'   => 'status-refunded',
            'failed'     => 'status-failed',
            default      => 'status-pending',
        };

        // Formatear desglose de line items
        $lineItems = [];
        foreach ($order['line_items'] ?? [] as $item) {
            $variations = [];
            foreach ($item['meta_data'] ?? [] as $meta) {
                $key = $meta['key'] ?? '';
                if (str_starts_with($key, '_')) {
                    continue;
                }
                $label = $meta['display_key'] ?? $meta['key'] ?? '';
                $value = $meta['display_value'] ?? $meta['value'] ?? '';
                $variations[] = "{$label}: {$value}";
            }

            $itemQty = (int) ($item['quantity'] ?? 1);
            $itemPrice = (float) ($item['price'] ?? 0);
            $itemTotal = (float) ($item['total'] ?? ($itemPrice * $itemQty));

            $lineItems[] = (object) [
                'id'              => (int) ($item['id'] ?? 0),
                'name'            => $item['name'] ?? 'Producto',
                'sku'             => $item['sku'] ?? '-',
                'quantity'        => $itemQty,
                'price'           => $itemPrice,
                'formatted_price' => $currencySymbol . ' ' . number_format($itemPrice, 2),
                'total'           => $itemTotal,
                'formatted_total' => $currencySymbol . ' ' . number_format($itemTotal, 2),
                'variations'      => $variations,
                'variations_text' => !empty($variations) ? implode(' | ', $variations) : '',
            ];
        }

        return (object) [
            'id'                   => $id,
            'number'               => $number,
            'status'               => $status,
            'status_label'         => $statusLabel,
            'status_badge_class'   => $statusBadgeClass,
            'date_created'         => $order['date_created'] ?? null,
            'formatted_date'       => $dateFormatted,
            'customer_name'        => $customerName,
            'customer_email'       => $customerEmail,
            'customer_phone'       => $customerPhone,
            'customer_address'     => $customerAddress,
            'payment_method_title' => !empty($order['payment_method_title']) ? $order['payment_method_title'] : 'No especificado',
            'currency'             => $order['currency'] ?? 'GTQ',
            'currency_symbol'      => $currencySymbol,
            'total'                => $total,
            'formatted_total'      => $currencySymbol . ' ' . number_format($total, 2),
            'shipping_total'       => (float) ($order['shipping_total'] ?? 0),
            'discount_total'       => (float) ($order['discount_total'] ?? 0),
            'total_tax'            => (float) ($order['total_tax'] ?? 0),
            'line_items'           => $lineItems,
        ];
    }
}
