<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $branches = $this->db->table('branches')
            ->select('id, code')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $skus = $this->db->table('skus')
            ->select('id, sku_code')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if ($branches === [] || $skus === []) {
            return;
        }

        $insertRows = [];
        $movementRows = [];

        foreach ($branches as $branch) {
            $branchId = (int) $branch['id'];
            $branchCode = (string) ($branch['code'] ?? '');

            foreach ($skus as $sku) {
                $skuId = (int) $sku['id'];
                $skuCode = (string) ($sku['sku_code'] ?? '');

                $exists = $this->db->table('inventory_balances')
                    ->where('branch_id', $branchId)
                    ->where('sku_id', $skuId)
                    ->where('deleted_at', null)
                    ->get()
                    ->getFirstRow('array');

                if ($exists) {
                    continue;
                }

                $qty = $this->seedQuantity($branchCode, $skuCode);

                $insertRows[] = [
                    'branch_id' => $branchId,
                    'sku_id' => $skuId,
                    'qty_on_hand' => $qty,
                    'qty_reserved' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                $movementRows[] = [
                    'branch_id' => $branchId,
                    'sku_id' => $skuId,
                    'movement_type' => 'receive',
                    'quantity' => $qty,
                    'reference_type' => 'seed_initial',
                    'reference_id' => $skuId,
                    'notes' => 'Initial stock seeded',
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($insertRows, 500) as $chunk) {
            if ($chunk !== []) {
                $this->db->table('inventory_balances')->insertBatch($chunk);
            }
        }

        foreach (array_chunk($movementRows, 500) as $chunk) {
            if ($chunk !== []) {
                $this->db->table('stock_movements')->insertBatch($chunk);
            }
        }

        // Add deterministic adjustment history without duplicates.
        $targetAdjustments = 150;
        $existingAdjustments = (int) $this->db->table('stock_movements')
            ->where('reference_type', 'seed_adjustment')
            ->countAllResults();

        $needed = max(0, $targetAdjustments - $existingAdjustments);
        if ($needed === 0) {
            return;
        }

        $balances = $this->db->table('inventory_balances')
            ->select('id, branch_id, sku_id, qty_on_hand')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if ($balances === []) {
            return;
        }

        $adjustMovements = [];

        for ($i = 0; $i < $needed; $i++) {
            $balance = $balances[$i % count($balances)];
            $branchId = (int) $balance['branch_id'];
            $skuId = (int) $balance['sku_id'];
            $qtyOnHand = (float) $balance['qty_on_hand'];

            $delta = (($i % 2) === 0) ? 2.0 : -1.0;
            if ($qtyOnHand < 2 && $delta < 0) {
                $delta = 1.0;
            }

            $this->db->query(
                'UPDATE `inventory_balances` SET `qty_on_hand` = GREATEST(0, `qty_on_hand` + ?), `updated_at` = ? WHERE `branch_id` = ? AND `sku_id` = ? AND `deleted_at` IS NULL',
                [$delta, $now, $branchId, $skuId]
            );

            $adjustMovements[] = [
                'branch_id' => $branchId,
                'sku_id' => $skuId,
                'movement_type' => 'adjustment',
                'quantity' => $delta,
                'reference_type' => 'seed_adjustment',
                'reference_id' => $existingAdjustments + $i + 1,
                'notes' => 'Periodic stock adjustment seeded',
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($adjustMovements, 500) as $chunk) {
            if ($chunk !== []) {
                $this->db->table('stock_movements')->insertBatch($chunk);
            }
        }
    }

    private function seedQuantity(string $branchCode, string $skuCode): int
    {
        $seed = abs(crc32($branchCode . '|' . $skuCode));
        return 10 + ($seed % 191); // 10..200
    }
}
