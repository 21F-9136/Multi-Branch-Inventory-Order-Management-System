<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSkuReorderLevel extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('reorder_level', 'skus')) {
            $this->forge->addColumn('skus', [
                'reorder_level' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '13,2',
                    'unsigned'   => true,
                    'default'    => 10,
                    'after'      => 'unit_price',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('reorder_level', 'skus')) {
            $this->forge->dropColumn('skus', 'reorder_level');
        }
    }
}
