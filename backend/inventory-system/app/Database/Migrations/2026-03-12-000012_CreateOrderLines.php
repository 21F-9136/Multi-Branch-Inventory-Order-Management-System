<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderLines extends Migration
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
            'order_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'sku_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '18,3',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '13,2',
                'unsigned'   => true,
                'default'    => 0,
            ],
            'line_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '13,2',
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('sku_id');

        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sku_id', 'skus', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('order_lines', true, [
            'ENGINE'         => 'InnoDB',
            'DEFAULT CHARSET'=> 'utf8mb4',
            'COLLATE'        => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('order_lines', true);
    }
}
