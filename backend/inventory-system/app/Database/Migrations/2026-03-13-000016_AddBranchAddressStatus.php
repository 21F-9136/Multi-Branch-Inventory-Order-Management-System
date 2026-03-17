<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBranchAddressStatus extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('branches')) {
            return;
        }

        if (!$this->db->fieldExists('address', 'branches')) {
            $this->forge->addColumn('branches', [
                'address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'name',
                ],
            ]);
        }

        if (!$this->db->fieldExists('status', 'branches')) {
            $this->forge->addColumn('branches', [
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'manager_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('branches') && $this->db->fieldExists('address', 'branches')) {
            $this->forge->dropColumn('branches', 'address');
        }

        if ($this->db->tableExists('branches') && $this->db->fieldExists('status', 'branches')) {
            $this->forge->dropColumn('branches', 'status');
        }
    }
}
