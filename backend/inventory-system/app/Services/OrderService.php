<?php

namespace App\Services;

use App\Models\OrderLineModel;
use App\Models\OrderModel;
use App\Models\ReservationModel;
use App\Models\SkuModel;
use App\Models\StockMovementModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class OrderService
{
    private const TAX_RATE = 0.10;
    private ?bool $supportsCompletedAt = null;

    protected BaseConnection $db;

    public function __construct(
        protected OrderModel $orders = new OrderModel(),
        protected OrderLineModel $orderLines = new OrderLineModel(),
        protected ReservationModel $reservations = new ReservationModel(),
        protected SkuModel $skus = new SkuModel(),
        protected StockMovementModel $movements = new StockMovementModel(),
        protected InventoryService $inventory = new InventoryService(),
        protected EventService $events = new EventService(),
        protected AuditService $audit = new AuditService(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * Creates an order with lines and reserves stock per line.
     *
     * @param array{branch_id:int,user_id?:int|null,status?:string,placed_at?:string|null} $orderData
     * @param array<int, array{sku_id:int,quantity:string|float|int,unit_price?:string|float|int}> $lines
     * @return int|string order id
     */
    public function createOrder(array $orderData, array $lines)
    {
        if (empty($lines)) {
            throw new RuntimeException('Order must contain at least one line.');
        }

        $this->db->transException(true)->transStart();

        $status = $orderData['status'] ?? 'draft';

        $this->orders->insert([
            'branch_id'    => (int) $orderData['branch_id'],
            'user_id'      => $orderData['user_id'] ?? null,
            'status'       => $status,
            'subtotal'     => 0,
            'tax_amount'   => 0,
            'grand_total'  => 0,
            'total_amount' => 0,
            'placed_at'    => $orderData['placed_at'] ?? null,
        ]);

        $orderId = $this->orders->getInsertID();

        $total = 0.0;
        foreach ($lines as $line) {
            $skuId = (int) $line['sku_id'];
            $qty = $this->normalizeQuantity($line['quantity']);
            if ($qty <= 0) {
                throw new RuntimeException('Line quantity must be > 0.');
            }

            if ($this->skus->find($skuId) === null) {
                throw new RuntimeException('SKU not found.');
            }

            $unitPrice = $this->resolveUnitPrice($skuId, $line);
            $lineTotal = $unitPrice * $qty;
            $total += $lineTotal;

            $this->orderLines->insert([
                'order_id'   => $orderId,
                'sku_id'     => $skuId,
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            $this->inventory->reserveStock(
                (int) $orderId,
                (int) $orderData['branch_id'],
                $skuId,
                $qty,
                null
            );
        }

        $taxAmount = $total * self::TAX_RATE;
        $grandTotal = $total + $taxAmount;

        $this->orders->update((int) $orderId, [
            'subtotal'     => $total,
            'tax_amount'   => $taxAmount,
            'grand_total'  => $grandTotal,
            'total_amount' => $grandTotal,
        ]);

        $this->events->publish('order', (int) $orderId, 'order.created', [
            'order_id'   => (int) $orderId,
            'branch_id'  => (int) $orderData['branch_id'],
            'user_id'    => $orderData['user_id'] ?? null,
            'status'     => $status,
            'total'      => $total,
            'lines'      => array_map(static function (array $l) {
                return [
                    'sku_id' => (int) $l['sku_id'],
                    'quantity' => (float) $l['quantity'],
                    'unit_price' => isset($l['unit_price']) ? (float) $l['unit_price'] : 0.0,
                ];
            }, $lines),
        ]);

        $this->audit->log(
            isset($orderData['user_id']) ? (int) $orderData['user_id'] : null,
            'order.create',
            'order',
            (int) $orderId,
            [
                'subtotal' => $total,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'total_amount' => $grandTotal,
            ]
        );

        $this->db->transComplete();

        return $orderId;
    }

    /**
     * Places the order: deducts reserved stock and marks reservations consumed.
     */
    public function placeOrder(int $orderId, ?int $actingUserId = null): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new RuntimeException('Order not found.');
        }

        if (!empty($order['deleted_at'])) {
            throw new RuntimeException('Order is deleted.');
        }

        $branchId = (int) $order['branch_id'];

        $this->db->transException(true)->transStart();

        $activeReservations = $this->reservations
            ->where('order_id', $orderId)
            ->where('status', 'active')
            ->findAll();

        if (empty($activeReservations)) {
            throw new RuntimeException('No active reservations found for order.');
        }

        foreach ($activeReservations as $reservation) {
            $skuId = (int) $reservation['sku_id'];
            $qty = $this->normalizeQuantity($reservation['quantity'] ?? 0);

            $this->inventory->deductReservedStock(
                $branchId,
                $skuId,
                $qty,
                $actingUserId,
                'Order placed',
                'order',
                $orderId
            );

            $this->reservations->update((int) $reservation['id'], [
                'status' => 'consumed',
            ]);
        }

        $this->orders->update($orderId, [
            'status'    => 'placed',
            'placed_at' => $order['placed_at'] ?? date('Y-m-d H:i:s'),
        ]);

        $this->events->publish('order', $orderId, 'order.placed', [
            'order_id'  => $orderId,
            'branch_id' => $branchId,
            'user_id'   => $order['user_id'] ?? null,
            'acting_user_id' => $actingUserId,
        ]);

        $this->audit->log(
            $actingUserId,
            'order.place',
            'order',
            $orderId,
            null
        );

        $this->db->transComplete();
    }

    /**
     * Cancels an order and releases any active reservations back to available stock.
     */
    public function cancelOrder(int $orderId, ?int $actingUserId = null): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new RuntimeException('Order not found.');
        }

        if (!empty($order['deleted_at'])) {
            throw new RuntimeException('Order is deleted.');
        }

        $status = strtolower((string) ($order['status'] ?? ''));
        if ($status === 'cancelled') {
            throw new RuntimeException('Order is already cancelled.');
        }

        if (!in_array($status, ['draft', 'placed'], true)) {
            throw new RuntimeException('Only draft or placed orders can be cancelled.');
        }

        $branchId = (int) $order['branch_id'];

        $this->db->transException(true)->transStart();

        $activeReservations = $this->reservations
            ->where('order_id', $orderId)
            ->where('status', 'active')
            ->findAll();

        foreach ($activeReservations as $reservation) {
            $skuId = (int) ($reservation['sku_id'] ?? 0);
            $qty = $this->normalizeQuantity($reservation['quantity'] ?? 0);

            if ($skuId <= 0 || $qty <= 0) {
                continue;
            }

            // Release reserved quantity back to available quantity.
            $this->db->query(
                'UPDATE `inventory_balances` '
                . 'SET `qty_reserved` = `qty_reserved` - ? '
                . 'WHERE `branch_id` = ? AND `sku_id` = ? AND `qty_reserved` >= ?',
                [$qty, $branchId, $skuId, $qty]
            );

            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Failed to release reserved stock for cancellation.');
            }

            $this->movements->insert([
                'branch_id'      => $branchId,
                'sku_id'         => $skuId,
                'movement_type'  => 'order_cancel',
                'quantity'       => $qty,
                'reference_type' => 'order',
                'reference_id'   => $orderId,
                'notes'          => 'Order cancelled, reservation released',
                'created_by'     => $actingUserId,
            ]);
        }

        // Mark all reservations for this order as released.
        $this->reservations
            ->where('order_id', $orderId)
            ->set(['status' => 'released'])
            ->update();

        $this->orders->update($orderId, [
            'status' => 'cancelled',
        ]);

        $this->events->publish('order', $orderId, 'order.cancelled', [
            'order_id' => $orderId,
            'branch_id' => $branchId,
            'user_id' => $order['user_id'] ?? null,
            'acting_user_id' => $actingUserId,
        ]);

        $this->audit->log(
            $actingUserId,
            'order.cancel',
            'order',
            $orderId,
            null
        );

        $this->db->transComplete();
    }

    public function completeOrder(
        int $orderId,
        ?int $actingUserId = null,
        string $actingRole = '',
        ?int $actingBranchId = null,
    ): void {
        $this->db->transException(true)->transStart();

        // Lock order row to prevent duplicate completion under concurrent requests.
        $lockedOrder = $this->db
            ->query(
                'SELECT * FROM `orders` WHERE `id` = ? AND `deleted_at` IS NULL FOR UPDATE',
                [$orderId]
            )
            ->getFirstRow('array');

        if ($lockedOrder === null) {
            throw new RuntimeException('Order not found.');
        }

        $status = strtolower((string) ($lockedOrder['status'] ?? ''));
        if ($status === 'completed') {
            throw new RuntimeException('Order is already completed.');
        }

        if ($status === 'cancelled') {
            throw new RuntimeException('Cancelled orders cannot be completed.');
        }

        if ($status !== 'placed') {
            throw new RuntimeException('Only placed orders can be completed.');
        }

        $orderBranchId = (int) ($lockedOrder['branch_id'] ?? 0);
        $orderUserId = isset($lockedOrder['user_id']) ? (int) $lockedOrder['user_id'] : null;
        $role = strtolower(trim($actingRole));

        if ($role === 'manager') {
            if ($actingBranchId === null || $actingBranchId !== $orderBranchId) {
                throw new RuntimeException('Forbidden.');
            }
        } elseif ($role === 'sales') {
            if ($actingUserId === null || $orderUserId === null || $actingUserId !== $orderUserId) {
                throw new RuntimeException('Forbidden.');
            }
        }

        $activeReservations = $this->reservations
            ->where('order_id', $orderId)
            ->where('status', 'active')
            ->findAll();

        foreach ($activeReservations as $reservation) {
            $skuId = (int) ($reservation['sku_id'] ?? 0);
            $qty = $this->normalizeQuantity($reservation['quantity'] ?? 0);

            if ($skuId <= 0 || $qty <= 0) {
                continue;
            }

            // Convert reserved quantity to consumed quantity without reducing on-hand again.
            $this->db->query(
                'UPDATE `inventory_balances` '
                . 'SET `qty_reserved` = `qty_reserved` - ? '
                . 'WHERE `branch_id` = ? AND `sku_id` = ? AND `qty_reserved` >= ?',
                [$qty, $orderBranchId, $skuId, $qty]
            );

            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Failed to consume reserved stock for completion.');
            }

            $this->movements->insert([
                'branch_id'      => $orderBranchId,
                'sku_id'         => $skuId,
                'movement_type'  => 'order_complete',
                'quantity'       => -$qty,
                'reference_type' => 'order',
                'reference_id'   => $orderId,
                'notes'          => 'Order completed',
                'created_by'     => $actingUserId,
            ]);

            $this->reservations->update((int) ($reservation['id'] ?? 0), [
                'status' => 'consumed',
            ]);
        }

        $completedAt = date('Y-m-d H:i:s');
        $orderUpdate = [
            'status' => 'completed',
        ];
        if ($this->hasCompletedAtColumn()) {
            $orderUpdate['completed_at'] = $completedAt;
        }
        $this->orders->update($orderId, $orderUpdate);

        $this->events->publish('order', $orderId, 'order.completed', [
            'order_id' => $orderId,
            'branch_id' => $orderBranchId,
            'user_id' => $orderUserId,
            'acting_user_id' => $actingUserId,
            'completed_at' => $completedAt,
        ]);

        $this->audit->log(
            $actingUserId,
            'order.complete',
            'order',
            $orderId,
            null
        );

        $this->db->transComplete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOrders(?int $branchId = null): array
    {
        $select = 'o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.total_amount, o.placed_at, o.created_at';
        if ($this->hasCompletedAtColumn()) {
            $select = 'o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.total_amount, o.placed_at, o.completed_at, o.created_at';
        }

        $q = $this->db
            ->table('orders o')
            ->select($select)
            ->join('branches b', 'b.id = o.branch_id', 'left')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('o.deleted_at IS NULL');

        if ($branchId !== null) {
            $q->where('o.branch_id', $branchId);
        }

        $orders = $q->orderBy('o.id', 'DESC')->get()->getResultArray();

        // Fetch order items for each order
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems((int) $order['id']);
            $order['subtotal'] = isset($order['subtotal']) ? (float) $order['subtotal'] : 0.0;
            $order['tax_amount'] = isset($order['tax_amount']) ? (float) $order['tax_amount'] : 0.0;
            $order['grand_total'] = isset($order['grand_total']) ? (float) $order['grand_total'] : 0.0;
            $order['total_amount'] = isset($order['total_amount']) ? (float) $order['total_amount'] : $order['grand_total'];
        }

        return $orders;
    }

    /**
     * Get order items with SKU and product details.
     * @return array<int, array<string, mixed>>
     */
    private function getOrderItems(int $orderId): array
    {
        return $this->db
            ->table('order_lines ol')
            ->select('ol.id, ol.order_id, ol.sku_id, ol.quantity, ol.unit_price, ol.line_total, s.name as sku_name, s.sku_code, s.product_id, p.name as product_name')
            ->join('skus s', 's.id = ol.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('ol.order_id', $orderId)
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProductSkus(int $productId): array
    {
        return $this->db
            ->table('skus s')
            ->select('s.id, s.sku_code, s.unit_price, s.sale_price')
            ->where('s.product_id', $productId)
            ->where('s.deleted_at IS NULL')
            ->orderBy('s.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get a single order with items and details.
     * @return array<string, mixed>
     */
    public function getOrder(int $orderId): array
    {
        $select = 'o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.total_amount, o.placed_at, o.created_at';
        if ($this->hasCompletedAtColumn()) {
            $select = 'o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.total_amount, o.placed_at, o.completed_at, o.created_at';
        }

        $order = $this->db
            ->table('orders o')
            ->select($select)
            ->join('branches b', 'b.id = o.branch_id', 'left')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('o.id', $orderId)
            ->where('o.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($order === null) {
            throw new RuntimeException('Order not found.');
        }

        // Fetch order items
        $order['items'] = $this->getOrderItems($orderId);
        $order['subtotal'] = isset($order['subtotal']) ? (float) $order['subtotal'] : 0.0;
        $order['tax_amount'] = isset($order['tax_amount']) ? (float) $order['tax_amount'] : 0.0;
        $order['grand_total'] = isset($order['grand_total']) ? (float) $order['grand_total'] : 0.0;
        $order['total_amount'] = isset($order['total_amount']) ? (float) $order['total_amount'] : $order['grand_total'];

        return $order;
    }

    /**
     * @param array<string, mixed> $line
     */
    private function resolveUnitPrice(int $skuId, array $line): float
    {
        if (array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '') {
            $provided = (float) $line['unit_price'];
            if ($provided > 0) {
                return $provided;
            }
        }

        $sku = $this->skus->find($skuId);
        if ($sku === null) {
            throw new RuntimeException('SKU not found.');
        }

        $salePrice = isset($sku['sale_price']) ? (float) $sku['sale_price'] : 0.0;
        if ($salePrice > 0) {
            return $salePrice;
        }

        $unitPrice = isset($sku['unit_price']) ? (float) $sku['unit_price'] : 0.0;
        if ($unitPrice > 0) {
            return $unitPrice;
        }

        $costPrice = isset($sku['cost_price']) ? (float) $sku['cost_price'] : 0.0;
        if ($costPrice > 0) {
            return $costPrice;
        }

        return 0.0;
    }

    private function normalizeQuantity($quantity): float
    {
        if (is_string($quantity)) {
            $quantity = trim($quantity);
        }

        if (!is_numeric($quantity)) {
            throw new RuntimeException('Quantity must be numeric.');
        }

        return (float) $quantity;
    }

    private function hasCompletedAtColumn(): bool
    {
        if ($this->supportsCompletedAt !== null) {
            return $this->supportsCompletedAt;
        }

        $this->supportsCompletedAt = $this->db->fieldExists('completed_at', 'orders');
        return $this->supportsCompletedAt;
    }
}
