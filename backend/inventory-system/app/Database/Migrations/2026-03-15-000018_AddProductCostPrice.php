<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductCostPrice extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products')) {
            return;
        }

        if (!$this->db->fieldExists('cost_price', 'products')) {
            $this->forge->addColumn('products', [
                'cost_price' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '13,2',
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'status',
                ],
            ]);
        }

        // Backfill product cost from existing SKU costs so dashboard expenses can be computed immediately.
        $rows = $this->db
            ->table('skus s')
            ->select('s.product_id, AVG(s.cost_price) as avg_cost', false)
            ->where('s.deleted_at', null)
            ->where('s.cost_price IS NOT NULL', null, false)
            ->groupBy('s.product_id')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $avgCost = $row['avg_cost'] ?? null;

            if ($productId <= 0 || $avgCost === null) {
                continue;
            }

            $this->db->table('products')
                ->where('id', $productId)
                ->where('cost_price IS NULL', null, false)
                ->update([
                    'cost_price' => (float) $avgCost,
                ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('products') && $this->db->fieldExists('cost_price', 'products')) {
            $this->forge->dropColumn('products', 'cost_price');
        }
    }
}
