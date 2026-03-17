<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderTotalsColumns extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('orders')) {
            return;
        }

        if (!$this->db->fieldExists('subtotal', 'orders')) {
            $this->forge->addColumn('orders', [
                'subtotal' => [
                    'type' => 'DECIMAL',
                    'constraint' => '13,2',
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'status',
                ],
            ]);
        }

        if (!$this->db->fieldExists('tax_amount', 'orders')) {
            $this->forge->addColumn('orders', [
                'tax_amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '13,2',
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'subtotal',
                ],
            ]);
        }

        if (!$this->db->fieldExists('grand_total', 'orders')) {
            $this->forge->addColumn('orders', [
                'grand_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '13,2',
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'tax_amount',
                ],
            ]);
        }

        $this->db->query('UPDATE `orders` SET `subtotal` = COALESCE(`subtotal`, 0)');
        $this->db->query('UPDATE `orders` SET `tax_amount` = COALESCE(`tax_amount`, 0)');
        $this->db->query('UPDATE `orders` SET `grand_total` = COALESCE(`grand_total`, 0)');

        // Backfill existing rows: default subtotal from legacy total_amount, then apply 10% tax.
        $this->db->query('UPDATE `orders` SET `subtotal` = `total_amount` WHERE `subtotal` = 0 AND `total_amount` > 0');
        $this->db->query('UPDATE `orders` SET `tax_amount` = ROUND(`subtotal` * 0.10, 2) WHERE `tax_amount` = 0 AND `subtotal` > 0');
        $this->db->query('UPDATE `orders` SET `grand_total` = ROUND(`subtotal` + `tax_amount`, 2) WHERE `grand_total` = 0 AND `subtotal` > 0');
        $this->db->query('UPDATE `orders` SET `total_amount` = `grand_total` WHERE `grand_total` > 0');
    }

    public function down()
    {
        if ($this->db->tableExists('orders') && $this->db->fieldExists('grand_total', 'orders')) {
            $this->forge->dropColumn('orders', 'grand_total');
        }

        if ($this->db->tableExists('orders') && $this->db->fieldExists('tax_amount', 'orders')) {
            $this->forge->dropColumn('orders', 'tax_amount');
        }

        if ($this->db->tableExists('orders') && $this->db->fieldExists('subtotal', 'orders')) {
            $this->forge->dropColumn('orders', 'subtotal');
        }
    }
}
