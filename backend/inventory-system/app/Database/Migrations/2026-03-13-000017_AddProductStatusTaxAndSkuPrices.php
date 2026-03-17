<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductStatusTaxAndSkuPrices extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('products')) {
            if (!$this->db->fieldExists('status', 'products')) {
                $this->forge->addColumn('products', [
                    'status' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                        'after'      => 'description',
                    ],
                ]);
            }

            if (!$this->db->fieldExists('tax_percent', 'products')) {
                $this->forge->addColumn('products', [
                    'tax_percent' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '5,2',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'status',
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('skus')) {
            if (!$this->db->fieldExists('cost_price', 'skus')) {
                $this->forge->addColumn('skus', [
                    'cost_price' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '13,2',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'barcode',
                    ],
                ]);
            }

            if (!$this->db->fieldExists('sale_price', 'skus')) {
                $this->forge->addColumn('skus', [
                    'sale_price' => [
                        'type'       => 'DECIMAL',
                        'constraint' => '13,2',
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'cost_price',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('products')) {
            if ($this->db->fieldExists('tax_percent', 'products')) {
                $this->forge->dropColumn('products', 'tax_percent');
            }
            if ($this->db->fieldExists('status', 'products')) {
                $this->forge->dropColumn('products', 'status');
            }
        }

        if ($this->db->tableExists('skus')) {
            if ($this->db->fieldExists('sale_price', 'skus')) {
                $this->forge->dropColumn('skus', 'sale_price');
            }
            if ($this->db->fieldExists('cost_price', 'skus')) {
                $this->forge->dropColumn('skus', 'cost_price');
            }
        }
    }
}
