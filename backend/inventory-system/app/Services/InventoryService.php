<?php

namespace App\Services;

use App\Models\InventoryBalanceModel;
use App\Models\ReservationModel;
use App\Models\SkuModel;
use App\Models\StockMovementModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class InventoryService
{
    protected BaseConnection $db;

    public function __construct(
        protected InventoryBalanceModel $balances = new InventoryBalanceModel(),
        protected StockMovementModel $movements = new StockMovementModel(),
        protected ReservationModel $reservations = new ReservationModel(),
        protected SkuModel $skus = new SkuModel(),
    ) {
        $this->db = Database::connect();
    }

    public function receiveStock(
        int $branchId,
        int $skuId,
        $quantity,
        ?int $createdByUserId = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $qty = $this->normalizeQuantity($quantity);
        if ($qty <= 0) {
            throw new RuntimeException('Receive quantity must be > 0.');
        }

        $this->assertSkuExists($skuId);

        $this->db->transException(true)->transStart();

        $this->ensureBalanceRow($branchId, $skuId);
        $this->incrementOnHand($branchId, $skuId, $qty);

        $this->movements->insert([
            'branch_id'       => $branchId,
            'sku_id'          => $skuId,
            'movement_type'   => 'receive',
            'quantity'        => $qty,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'notes'           => $notes,
            'created_by'      => $createdByUserId,
        ]);

        $this->db->transComplete();
    }

    /**
     * Adjustment can be positive (add stock) or negative (remove stock).
     */
    public function adjustStock(
        int $branchId,
        int $skuId,
        $quantityDelta,
        ?int $createdByUserId = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $delta = $this->normalizeQuantity($quantityDelta);
        if ($delta == 0.0) {
            throw new RuntimeException('Adjustment delta cannot be 0.');
        }

        $this->assertSkuExists($skuId);

        $this->db->transException(true)->transStart();

        $this->ensureBalanceRow($branchId, $skuId);

        if ($delta > 0) {
            $this->incrementOnHand($branchId, $skuId, $delta);
        } else {
            $abs = abs($delta);
            $this->db->query(
                'UPDATE `inventory_balances` '
                . 'SET `qty_on_hand` = `qty_on_hand` - ? '
                . 'WHERE `branch_id` = ? AND `sku_id` = ? '
                . 'AND (`qty_on_hand` - `qty_reserved`) >= ?',
                [$abs, $branchId, $skuId, $abs]
            );

            if ($this->db->affectedRows() !== 1) {
                throw new RuntimeException('Insufficient available stock to adjust down.');
            }
        }

        $this->movements->insert([
            'branch_id'      => $branchId,
            'sku_id'         => $skuId,
            'movement_type'  => 'adjustment',
            'quantity'       => $delta,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'created_by'     => $createdByUserId,
        ]);

        $this->db->transComplete();
    }

    public function transferStock(
        int $fromBranchId,
        int $toBranchId,
        int $skuId,
        $quantity,
        ?int $createdByUserId = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        if ($fromBranchId === $toBranchId) {
            throw new RuntimeException('From and to branches must differ.');
        }

        $qty = $this->normalizeQuantity($quantity);
        if ($qty <= 0) {
            throw new RuntimeException('Transfer quantity must be > 0.');
        }

        $this->assertSkuExists($skuId);

        $this->db->transException(true)->transStart();

        $this->ensureBalanceRow($fromBranchId, $skuId);
        $this->ensureBalanceRow($toBranchId, $skuId);

        $this->db->query(
            'UPDATE `inventory_balances` '
            . 'SET `qty_on_hand` = `qty_on_hand` - ? '
            . 'WHERE `branch_id` = ? AND `sku_id` = ? '
            . 'AND (`qty_on_hand` - `qty_reserved`) >= ?',
            [$qty, $fromBranchId, $skuId, $qty]
        );

        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('Insufficient available stock to transfer.');
        }

        $this->incrementOnHand($toBranchId, $skuId, $qty);

        $this->movements->insert([
            'branch_id'      => $fromBranchId,
            'sku_id'         => $skuId,
            'movement_type'  => 'transfer_out',
            'quantity'       => -$qty,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'created_by'     => $createdByUserId,
        ]);

        $this->movements->insert([
            'branch_id'      => $toBranchId,
            'sku_id'         => $skuId,
            'movement_type'  => 'transfer_in',
            'quantity'       => $qty,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'created_by'     => $createdByUserId,
        ]);

        $this->db->transComplete();
    }

    /**
     * Creates a reservation row and increases qty_reserved.
     *
     * @return int|string reservation id
     */
    public function reserveStock(
        int $orderId,
        int $branchId,
        int $skuId,
        $quantity,
        ?string $expiresAt = null,
    ) {
        $qty = $this->normalizeQuantity($quantity);
        if ($qty <= 0) {
            throw new RuntimeException('Reservation quantity must be > 0.');
        }

        $this->assertSkuExists($skuId);

        $this->db->transException(true)->transStart();

        $this->ensureBalanceRow($branchId, $skuId);

        // Atomic reserve: only succeed when available stock is sufficient.
        $this->db->query(
            'UPDATE `inventory_balances` '
            . 'SET `qty_reserved` = `qty_reserved` + ? '
            . 'WHERE `branch_id` = ? AND `sku_id` = ? '
            . 'AND (`qty_on_hand` - `qty_reserved`) >= ?',
            [$qty, $branchId, $skuId, $qty]
        );

        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('Insufficient stock to reserve.');
        }

        $effectiveExpiresAt = $expiresAt;
        if ($effectiveExpiresAt === null || trim($effectiveExpiresAt) === '') {
            $effectiveExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        }

        $this->reservations->insert([
            'order_id'    => $orderId,
            'branch_id'   => $branchId,
            'sku_id'      => $skuId,
            'quantity'    => $qty,
            'status'      => 'active',
            'expires_at'  => $effectiveExpiresAt,
        ]);

        $reservationId = $this->reservations->getInsertID();

        $this->db->transComplete();

        return $reservationId;
    }

    /**
     * Deducts stock from on-hand and releases the reserved amount.
     */
    public function deductReservedStock(
        int $branchId,
        int $skuId,
        $quantity,
        ?int $createdByUserId = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $qty = $this->normalizeQuantity($quantity);
        if ($qty <= 0) {
            throw new RuntimeException('Deduction quantity must be > 0.');
        }

        $this->db->transException(true)->transStart();

        $this->ensureBalanceRow($branchId, $skuId);

        // Atomic deduction: ensure we never go negative.
        $this->db->query(
            'UPDATE `inventory_balances` '
            . 'SET `qty_reserved` = `qty_reserved` - ?, `qty_on_hand` = `qty_on_hand` - ? '
            . 'WHERE `branch_id` = ? AND `sku_id` = ? '
            . 'AND `qty_reserved` >= ? AND `qty_on_hand` >= ?',
            [$qty, $qty, $branchId, $skuId, $qty, $qty]
        );

        if ($this->db->affectedRows() !== 1) {
            throw new RuntimeException('Insufficient reserved/on-hand stock to deduct.');
        }

        $this->movements->insert([
            'branch_id'      => $branchId,
            'sku_id'         => $skuId,
            'movement_type'  => 'deduct',
            'quantity'       => -$qty,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'notes'          => $notes,
            'created_by'     => $createdByUserId,
        ]);

        $this->db->transComplete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInventoryBalances(?int $branchId = null): array
    {
        if ($branchId !== null) {
            return $this->db
                ->table('skus s')
                ->select('b.id as branch_id, b.name as branch_name, s.id as sku_id, s.sku_code, p.name as product_name, COALESCE(ib.qty_on_hand, 0) as qty_on_hand, COALESCE(ib.qty_reserved, 0) as qty_reserved, COALESCE(ib.updated_at, s.updated_at) as updated_at', false)
                ->join('products p', 'p.id = s.product_id', 'left')
                ->join('branches b', 'b.id = ' . (int) $branchId, 'left')
                ->join('inventory_balances ib', 'ib.sku_id = s.id AND ib.branch_id = ' . (int) $branchId . ' AND ib.deleted_at IS NULL', 'left')
                ->where('s.deleted_at IS NULL')
                ->where('p.deleted_at IS NULL')
                ->orderBy('p.name', 'ASC')
                ->orderBy('s.sku_code', 'ASC')
                ->get()
                ->getResultArray();
        }

        $q = $this->db
            ->table('inventory_balances ib')
            ->select('ib.branch_id, b.name as branch_name, ib.sku_id, s.sku_code, p.name as product_name, ib.qty_on_hand, ib.qty_reserved, ib.updated_at')
            ->join('branches b', 'b.id = ib.branch_id', 'left')
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('ib.deleted_at IS NULL');

        return $q
            ->orderBy('p.name', 'ASC')
            ->orderBy('s.sku_code', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function listStockLedger(
        ?int $branchId = null,
        ?int $productId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $builder = $this->buildStockLedgerBaseQuery($branchId, $productId, $dateFrom, $dateTo);

        $countBuilder = clone $builder;
        $total = (int) $countBuilder->select('sm.id')->countAllResults();

        $rows = $builder
            ->select('sm.id, sm.created_at, sm.branch_id, b.name as branch_name, s.product_id, p.name as product_name, s.sku_code, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.created_by, u.name as user_name')
            ->orderBy('sm.created_at', 'DESC')
            ->orderBy('sm.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $this->mapLedgerRows($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exportStockLedger(
        ?int $branchId = null,
        ?int $productId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        $rows = $this->buildStockLedgerBaseQuery($branchId, $productId, $dateFrom, $dateTo)
            ->select('sm.id, sm.created_at, sm.branch_id, b.name as branch_name, s.product_id, p.name as product_name, s.sku_code, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.created_by, u.name as user_name')
            ->orderBy('sm.created_at', 'DESC')
            ->orderBy('sm.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->mapLedgerRows($rows);
    }

    private function buildStockLedgerBaseQuery(
        ?int $branchId,
        ?int $productId,
        ?string $dateFrom,
        ?string $dateTo,
    ) {
        $builder = $this->db
            ->table('stock_movements sm')
            ->join('skus s', 's.id = sm.sku_id')
            ->join('products p', 'p.id = s.product_id')
            ->join('branches b', 'b.id = sm.branch_id')
            ->join('users u', 'u.id = sm.created_by', 'left');

        if ($branchId !== null) {
            $builder->where('sm.branch_id', $branchId);
        }

        if ($productId !== null) {
            $builder->where('p.id', $productId);
        }

        if ($dateFrom !== null) {
            $builder->where('sm.created_at >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== null) {
            $builder->where('sm.created_at <=', $dateTo . ' 23:59:59');
        }

        return $builder;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapLedgerRows(array $rows): array
    {
        $items = array_map(function (array $row): array {
            $qty = (float) ($row['quantity'] ?? 0);
            $movementType = (string) ($row['movement_type'] ?? '');
            $referenceType = isset($row['reference_type']) ? (string) $row['reference_type'] : null;

            $row['quantity'] = $qty;
            $row['movement_group'] = $this->mapMovementGroup($movementType, $qty);
            $row['reference_label'] = $this->mapReferenceLabel($referenceType, $movementType);
            $row['user_name'] = trim((string) ($row['user_name'] ?? '')) !== '' ? (string) $row['user_name'] : 'System';

            return $row;
        }, $rows);

        return $items;
    }

    private function assertSkuExists(int $skuId): void
    {
        if ($this->skus->find($skuId) === null) {
            throw new RuntimeException('SKU not found.');
        }
    }

    private function ensureBalanceRow(int $branchId, int $skuId): void
    {
        // Avoid a race between concurrent requests by relying on the unique index.
        // INSERT IGNORE ensures idempotency if another transaction inserts first.
        $this->db->query(
            'INSERT IGNORE INTO `inventory_balances` (`branch_id`, `sku_id`, `qty_on_hand`, `qty_reserved`, `created_at`, `updated_at`) '
            . 'VALUES (?, ?, 0, 0, ?, ?)',
            [$branchId, $skuId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
    }

    private function incrementOnHand(int $branchId, int $skuId, float $delta): void
    {
        $this->db->query(
            'UPDATE `inventory_balances` '
            . 'SET `qty_on_hand` = `qty_on_hand` + ? '
            . 'WHERE `branch_id` = ? AND `sku_id` = ?',
            [$delta, $branchId, $skuId]
        );
    }

    private function incrementReserved(int $branchId, int $skuId, float $delta): void
    {
        $this->db->query(
            'UPDATE `inventory_balances` '
            . 'SET `qty_reserved` = `qty_reserved` + ? '
            . 'WHERE `branch_id` = ? AND `sku_id` = ?',
            [$delta, $branchId, $skuId]
        );
    }

    private function mapMovementGroup(string $movementType, float $quantity): string
    {
        return match ($movementType) {
            'transfer_in', 'transfer_out' => 'TRANSFER',
            'adjustment' => 'ADJUSTMENT',
            'receive' => 'IN',
            'deduct' => 'OUT',
            default => $quantity < 0 ? 'OUT' : 'IN',
        };
    }

    private function mapReferenceLabel(?string $referenceType, string $movementType): string
    {
        if ($referenceType !== null && trim($referenceType) !== '') {
            return ucwords(str_replace(['_', '-'], ' ', strtolower($referenceType)));
        }

        return match ($movementType) {
            'transfer_in', 'transfer_out' => 'Transfer',
            'adjustment' => 'Adjustment',
            'deduct' => 'Order',
            'receive' => 'Receive',
            default => 'Manual',
        };
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
}
