<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderCompletedAt extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('orders')) {
            return;
        }

        if (!$this->db->fieldExists('completed_at', 'orders')) {
            $this->forge->addColumn('orders', [
                'completed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'placed_at',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('orders') && $this->db->fieldExists('completed_at', 'orders')) {
            $this->forge->dropColumn('orders', 'completed_at');
        }
    }
}
