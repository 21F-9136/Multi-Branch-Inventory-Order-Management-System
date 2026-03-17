<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Seeder;

class OrderSeeder extends Seeder
{
    private const TARGET_SALES_ORDERS = 2000;
    private const TAX_RATE = 0.10;

    public function run()
    {
        $now = date('Y-m-d H:i:s');
        /** @var BaseConnection $db */
        $db = $this->db;

        $salesUsers = $db->table('users')
            ->select('id, branch_id')
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->like('email', 'sales.', 'after')
            ->like('email', '@erp.com', 'before')
            ->get()
            ->getResultArray();

        if ($salesUsers === []) {
            return;
        }

        $salesUserIds = array_map(static fn ($u) => (int) $u['id'], $salesUsers);

        $existingCount = (int) $db->table('orders')
            ->whereIn('user_id', $salesUserIds)
            ->where('deleted_at', null)
            ->countAllResults();

        $remaining = max(0, self::TARGET_SALES_ORDERS - $existingCount);
        if ($remaining === 0) {
            return;
        }

        $skuRows = $db->table('skus')
            ->select('id')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        if ($skuRows === []) {
            return;
        }
        $allSkuIds = array_map(static fn ($s) => (int) $s['id'], $skuRows);

        $nextOrderId = (int) ($db->table('orders')->selectMax('id')->get()->getRow('id') ?? 0) + 1;
        $nextLineId = (int) ($db->table('order_lines')->selectMax('id')->get()->getRow('id') ?? 0) + 1;

        $batchSize = 100;
        $created = 0;

        while ($created < $remaining) {
            $ordersBatch = [];
            $linesBatch = [];
            $movementsBatch = [];
            $successInBatch = 0;
            $attempts = 0;

            $db->transException(true)->transStart();

            while ($successInBatch < $batchSize && $created + $successInBatch < $remaining && $attempts < ($batchSize * 15)) {
                $attempts++;

                $user = $salesUsers[array_rand($salesUsers)];
                $userId = (int) $user['id'];
                $branchId = isset($user['branch_id']) ? (int) $user['branch_id'] : 0;
                if ($branchId <= 0) {
                    continue;
                }

                $itemCount = random_int(1, 5);
                shuffle($allSkuIds);
                $selectedSkuIds = array_slice($allSkuIds, 0, $itemCount);

                if ($selectedSkuIds === []) {
                    continue;
                }

                $orderId = $nextOrderId;
                $lineRows = [];
                $movementRows = [];
                $subtotal = 0.0;
                $orderOk = true;

                $db->query('SAVEPOINT seed_order_try');

                foreach ($selectedSkuIds as $skuId) {
                    $qty = (float) random_int(1, 4);

                    $locked = $db->query(
                        'SELECT ib.qty_on_hand, COALESCE(NULLIF(s.sale_price,0), NULLIF(s.unit_price,0), NULLIF(s.cost_price,0), 0) as unit_price '
                        . 'FROM inventory_balances ib '
                        . 'JOIN skus s ON s.id = ib.sku_id '
                        . 'WHERE ib.branch_id = ? AND ib.sku_id = ? AND ib.deleted_at IS NULL '
                        . 'FOR UPDATE',
                        [$branchId, $skuId]
                    )->getRowArray();

                    if (!$locked) {
                        $orderOk = false;
                        break;
                    }

                    $available = (float) ($locked['qty_on_hand'] ?? 0);
                    $unitPrice = (float) ($locked['unit_price'] ?? 0);

                    if ($available < $qty || $unitPrice <= 0) {
                        $orderOk = false;
                        break;
                    }

                    $db->query(
                        'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ?, updated_at = ? '
                        . 'WHERE branch_id = ? AND sku_id = ? AND qty_on_hand >= ? AND deleted_at IS NULL',
                        [$qty, $now, $branchId, $skuId, $qty]
                    );

                    if ($db->affectedRows() !== 1) {
                        $orderOk = false;
                        break;
                    }

                    $lineTotal = round($unitPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    $lineRows[] = [
                        'id' => $nextLineId++,
                        'order_id' => $orderId,
                        'sku_id' => $skuId,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'created_at' => $this->randomHistoricalDate(),
                        'updated_at' => $now,
                    ];

                    $movementRows[] = [
                        'branch_id' => $branchId,
                        'sku_id' => $skuId,
                        'movement_type' => 'deduct',
                        'quantity' => -$qty,
                        'reference_type' => 'order',
                        'reference_id' => $orderId,
                        'notes' => 'Demo order stock deduction',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!$orderOk || $lineRows === []) {
                    $db->query('ROLLBACK TO SAVEPOINT seed_order_try');
                    continue;
                }

                $tax = round($subtotal * self::TAX_RATE, 2);
                $grand = round($subtotal + $tax, 2);
                $orderedAt = $this->randomHistoricalDate();

                $ordersBatch[] = [
                    'id' => $orderId,
                    'branch_id' => $branchId,
                    'user_id' => $userId,
                    'status' => 'placed',
                    'subtotal' => $subtotal,
                    'tax_amount' => $tax,
                    'grand_total' => $grand,
                    'total_amount' => $grand,
                    'placed_at' => $orderedAt,
                    'created_at' => $orderedAt,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                $linesBatch = array_merge($linesBatch, $lineRows);
                $movementsBatch = array_merge($movementsBatch, $movementRows);

                $nextOrderId++;
                $successInBatch++;
            }

            if ($ordersBatch !== []) {
                foreach (array_chunk($ordersBatch, 200) as $chunk) {
                    $db->table('orders')->insertBatch($chunk);
                }
                foreach (array_chunk($linesBatch, 500) as $chunk) {
                    $db->table('order_lines')->insertBatch($chunk);
                }
                foreach (array_chunk($movementsBatch, 500) as $chunk) {
                    $db->table('stock_movements')->insertBatch($chunk);
                }
            }

            $db->transComplete();

            if ($successInBatch === 0) {
                // Stop if no order can be created from current stock.
                break;
            }

            $created += $successInBatch;
        }
    }

    private function randomHistoricalDate(): string
    {
        $daysAgo = random_int(1, 180);
        $hours = random_int(8, 20);
        $minutes = random_int(0, 59);
        $seconds = random_int(0, 59);

        return date('Y-m-d H:i:s', strtotime(sprintf('-%d days %02d:%02d:%02d', $daysAgo, $hours, $minutes, $seconds)));
    }
}
