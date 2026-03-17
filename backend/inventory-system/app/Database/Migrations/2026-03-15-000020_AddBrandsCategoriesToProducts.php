<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBrandsCategoriesToProducts extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('brands')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('name', false, true);
            $this->forge->createTable('brands', true, [
                'ENGINE' => 'InnoDB',
                'DEFAULT CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_unicode_ci',
            ]);
        }

        if (!$this->db->tableExists('categories')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('name', false, true);
            $this->forge->createTable('categories', true, [
                'ENGINE' => 'InnoDB',
                'DEFAULT CHARSET' => 'utf8mb4',
                'COLLATE' => 'utf8mb4_unicode_ci',
            ]);
        }

        $brandRows = ['Dell', 'HP', 'Apple', 'Logitech'];
        foreach ($brandRows as $name) {
            $exists = $this->db->table('brands')->where('name', $name)->get()->getFirstRow('array');
            if ($exists === null) {
                $this->db->table('brands')->insert(['name' => $name]);
            }
        }

        $categoryRows = ['Laptop', 'Accessories'];
        foreach ($categoryRows as $name) {
            $exists = $this->db->table('categories')->where('name', $name)->get()->getFirstRow('array');
            if ($exists === null) {
                $this->db->table('categories')->insert(['name' => $name]);
            }
        }

        if ($this->db->tableExists('products')) {
            if (!$this->db->fieldExists('brand_id', 'products')) {
                $this->forge->addColumn('products', [
                    'brand_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'name',
                    ],
                ]);
            }

            if (!$this->db->fieldExists('category_id', 'products')) {
                $this->forge->addColumn('products', [
                    'category_id' => [
                        'type'       => 'BIGINT',
                        'constraint' => 20,
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => 'brand_id',
                    ],
                ]);
            }

            try {
                $this->db->query('ALTER TABLE products ADD INDEX idx_products_brand_id (brand_id)');
            } catch (\Throwable $e) {
            }

            try {
                $this->db->query('ALTER TABLE products ADD INDEX idx_products_category_id (category_id)');
            } catch (\Throwable $e) {
            }

            // MySQL may throw if FK already exists; ignore those failures for idempotency.
            try {
                $this->db->query('ALTER TABLE products ADD CONSTRAINT fk_products_brand FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $e) {
            }

            try {
                $this->db->query('ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $e) {
            }

            $this->backfillProductMasterLinks();
        }
    }

    public function down()
    {
        if ($this->db->tableExists('products')) {
            try {
                $this->db->query('ALTER TABLE products DROP FOREIGN KEY fk_products_brand');
            } catch (\Throwable $e) {
            }
            try {
                $this->db->query('ALTER TABLE products DROP FOREIGN KEY fk_products_category');
            } catch (\Throwable $e) {
            }
            if ($this->db->fieldExists('category_id', 'products')) {
                $this->forge->dropColumn('products', 'category_id');
            }
            if ($this->db->fieldExists('brand_id', 'products')) {
                $this->forge->dropColumn('products', 'brand_id');
            }
        }

        if ($this->db->tableExists('categories')) {
            $this->forge->dropTable('categories', true);
        }

        if ($this->db->tableExists('brands')) {
            $this->forge->dropTable('brands', true);
        }
    }

    private function backfillProductMasterLinks(): void
    {
        $brands = $this->db->table('brands')->get()->getResultArray();
        $categories = $this->db->table('categories')->get()->getResultArray();

        $brandMap = [];
        foreach ($brands as $b) {
            $brandMap[strtolower((string) $b['name'])] = (int) $b['id'];
        }

        $categoryMap = [];
        foreach ($categories as $c) {
            $categoryMap[strtolower((string) $c['name'])] = (int) $c['id'];
        }

        $products = $this->db->table('products')->where('deleted_at', null)->get()->getResultArray();
        $invalidGeneric = ['dell', 'hp', 'apple', 'laptop', 'accessories'];

        foreach ($products as $p) {
            $name = trim((string) ($p['name'] ?? ''));
            $nameLc = strtolower($name);

            $brandId = null;
            if (str_contains($nameLc, 'dell') && isset($brandMap['dell'])) $brandId = $brandMap['dell'];
            if (str_contains($nameLc, 'hp') && isset($brandMap['hp'])) $brandId = $brandMap['hp'];
            if (str_contains($nameLc, 'mac') && isset($brandMap['apple'])) $brandId = $brandMap['apple'];
            if (str_contains($nameLc, 'apple') && isset($brandMap['apple'])) $brandId = $brandMap['apple'];
            if (str_contains($nameLc, 'logitech') && isset($brandMap['logitech'])) $brandId = $brandMap['logitech'];

            $categoryId = null;
            if (str_contains($nameLc, 'laptop') || str_contains($nameLc, 'macbook')) {
                $categoryId = $categoryMap['laptop'] ?? null;
            } else {
                $categoryId = $categoryMap['accessories'] ?? null;
            }

            $newName = $name;
            if (in_array($nameLc, $invalidGeneric, true)) {
                $sku = $this->db->table('skus')
                    ->select('name, sku_code')
                    ->where('product_id', (int) $p['id'])
                    ->where('deleted_at', null)
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getFirstRow('array');

                if ($sku !== null && trim((string) ($sku['name'] ?? '')) !== '') {
                    $newName = trim((string) $sku['name']);
                } elseif ($sku !== null && trim((string) ($sku['sku_code'] ?? '')) !== '') {
                    $newName = trim((string) $sku['sku_code']) . ' Item';
                } else {
                    $newName = ucfirst($nameLc) . ' Item';
                }
            }

            $this->db->table('products')
                ->where('id', (int) $p['id'])
                ->update([
                    'name' => $newName,
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }
}
