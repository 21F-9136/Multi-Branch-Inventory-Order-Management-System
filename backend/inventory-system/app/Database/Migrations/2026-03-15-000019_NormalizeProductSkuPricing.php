<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeProductSkuPricing extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products') || !$this->db->tableExists('skus')) {
            return;
        }

        // Backfill missing SKU prices from product cost before dropping product pricing.
        if ($this->db->fieldExists('cost_price', 'products')) {
            $this->db->query(
                'UPDATE skus s '
                . 'JOIN products p ON p.id = s.product_id '
                . 'SET s.cost_price = COALESCE(s.cost_price, p.cost_price) '
                . 'WHERE s.deleted_at IS NULL AND p.cost_price IS NOT NULL'
            );

            $this->forge->dropColumn('products', 'cost_price');
        }
    }

    public function down()
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
    }
}
