<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DeriveBrandsCategoriesDynamically extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products') || !$this->db->tableExists('brands') || !$this->db->tableExists('categories')) {
            return;
        }

        $products = $this->db->table('products')
            ->select('id, name, brand_id, category_id')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);
            $name = trim((string) ($product['name'] ?? ''));
            if ($productId <= 0 || $name === '') {
                continue;
            }

            $updates = [];

            $currentBrandId = $product['brand_id'] ?? null;
            if ($currentBrandId === null || $currentBrandId === '') {
                $brandName = $this->deriveBrandName($name);
                $updates['brand_id'] = $this->getOrCreateIdByName('brands', $brandName);
            }

            $currentCategoryId = $product['category_id'] ?? null;
            if ($currentCategoryId === null || $currentCategoryId === '') {
                $categoryName = $this->deriveCategoryName($name);
                $updates['category_id'] = $this->getOrCreateIdByName('categories', $categoryName);
            }

            if ($updates !== []) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                $this->db->table('products')->where('id', $productId)->update($updates);
            }
        }
    }

    public function down()
    {
        // Intentionally no-op: this migration enriches reference links only.
    }

    private function deriveBrandName(string $productName): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9\s\-]/', '', $productName) ?? $productName;
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $candidate = $parts[0] ?? '';
        if ($candidate === '') {
            return 'Unknown Brand';
        }

        return ucfirst(strtolower($candidate));
    }

    private function deriveCategoryName(string $productName): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9\s\-]/', '', $productName) ?? $productName;
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        if (count($parts) >= 2) {
            $candidate = $parts[count($parts) - 1];
            if ($candidate !== '') {
                return ucfirst(strtolower($candidate));
            }
        }

        return 'Uncategorized';
    }

    private function getOrCreateIdByName(string $table, string $name): int
    {
        $existing = $this->db->table($table)
            ->select('id')
            ->where('name', $name)
            ->get()
            ->getFirstRow('array');

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->db->table($table)->insert(['name' => $name]);
        return (int) $this->db->insertID();
    }
}
