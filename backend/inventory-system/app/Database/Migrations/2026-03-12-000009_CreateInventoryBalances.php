<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryBalances extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'sku_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'qty_on_hand' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'qty_reserved' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('branch_id');
        $this->forge->addKey('sku_id');
        $this->forge->addKey(['branch_id', 'sku_id'], false, true);

        $this->forge->addForeignKey('branch_id', 'branches', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sku_id', 'skus', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('inventory_balances', true, [
            'ENGINE'         => 'InnoDB',
            'DEFAULT CHARSET'=> 'utf8mb4',
            'COLLATE'        => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('inventory_balances', true);
    }
}
