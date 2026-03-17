<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Seeder;
use Config\Database;

class DemoDataSeeder extends Seeder
{
    public function run()
    {
        /** @var BaseConnection $db */
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');

        // Step 1: Create branches (Pakistani cities)
        $branches = [
            ['code' => 'LAH', 'name' => 'Lahore', 'address' => 'Mall Road, Lahore', 'status' => 'active'],
            ['code' => 'KAR', 'name' => 'Karachi', 'address' => 'Clifton, Karachi', 'status' => 'active'],
            ['code' => 'ISL', 'name' => 'Islamabad', 'address' => 'F-7, Islamabad', 'status' => 'active'],
            ['code' => 'FAI', 'name' => 'Faisalabad', 'address' => 'Ghulberg, Faisalabad', 'status' => 'active'],
            ['code' => 'MUL', 'name' => 'Multan', 'address' => 'Cantt, Multan', 'status' => 'active'],
        ];

        $branchIds = [];
        foreach ($branches as $branch) {
            $existing = $db->table('branches')->where('code', $branch['code'])->get()->getFirstRow('array');
            if (!$existing) {
                $db->table('branches')->insert([
                    'code'       => $branch['code'],
                    'name'       => $branch['name'],
                    'address'    => $branch['address'],
                    'status'     => $branch['status'],
                    'manager_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $branchIds[$branch['code']] = (int) $db->insertID();
            } else {
                $branchIds[$branch['code']] = (int) $existing['id'];
            }
        }

        // Step 2: Create products
        $brandRows = ['Dell', 'HP', 'Apple', 'Logitech'];
        $brandIds = [];
        foreach ($brandRows as $brandName) {
            $existingBrand = $db->table('brands')->where('name', $brandName)->get()->getFirstRow('array');
            if (!$existingBrand) {
                $db->table('brands')->insert(['name' => $brandName]);
                $brandIds[$brandName] = (int) $db->insertID();
            } else {
                $brandIds[$brandName] = (int) $existingBrand['id'];
            }
        }

        $categoryRows = ['Laptop', 'Accessories'];
        $categoryIds = [];
        foreach ($categoryRows as $categoryName) {
            $existingCategory = $db->table('categories')->where('name', $categoryName)->get()->getFirstRow('array');
            if (!$existingCategory) {
                $db->table('categories')->insert(['name' => $categoryName]);
                $categoryIds[$categoryName] = (int) $db->insertID();
            } else {
                $categoryIds[$categoryName] = (int) $existingCategory['id'];
            }
        }

        $products = [
            ['name' => 'Dell Laptop', 'brand' => 'Dell', 'category' => 'Laptop', 'sku' => 'DELL-LAPTOP-001', 'price' => 120000, 'cost_price' => 90000, 'tax_percent' => 17],
            ['name' => 'HP Charger', 'brand' => 'HP', 'category' => 'Accessories', 'sku' => 'HP-CHG-001', 'price' => 5000, 'cost_price' => 3500, 'tax_percent' => 17],
            ['name' => 'Logitech Mouse', 'brand' => 'Logitech', 'category' => 'Accessories', 'sku' => 'LOG-MOUSE-001', 'price' => 3000, 'cost_price' => 1900, 'tax_percent' => 17],
            ['name' => 'Wireless Keyboard', 'brand' => 'Logitech', 'category' => 'Accessories', 'sku' => 'WIRE-KB-001', 'price' => 8000, 'cost_price' => 5600, 'tax_percent' => 17],
            ['name' => 'MacBook Pro', 'brand' => 'Apple', 'category' => 'Laptop', 'sku' => 'MAC-BOOK-PRO-001', 'price' => 350000, 'cost_price' => 290000, 'tax_percent' => 17],
        ];

        $productIds = [];
        $skuIds = [];
        foreach ($products as $product) {
            $existingProduct = $db->table('products')->where('name', $product['name'])->get()->getFirstRow('array');
            if (!$existingProduct) {
                $db->table('products')->insert([
                    'name'        => $product['name'],
                    'brand_id'    => $brandIds[$product['brand']] ?? null,
                    'category_id' => $categoryIds[$product['category']] ?? null,
                    'description' => 'Premium tech product from ' . $product['name'],
                    'status'      => 'active',
                    'tax_percent' => $product['tax_percent'],
                    'is_active'   => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                $productIds[$product['name']] = (int) $db->insertID();
            } else {
                $db->table('products')
                    ->where('id', (int) $existingProduct['id'])
                    ->update([
                        'brand_id' => $brandIds[$product['brand']] ?? null,
                        'category_id' => $categoryIds[$product['category']] ?? null,
                        'updated_at' => $now,
                    ]);
                $productIds[$product['name']] = (int) $existingProduct['id'];
            }

            // Create SKU for this product
            $existingSku = $db->table('skus')->where('sku_code', $product['sku'])->get()->getFirstRow('array');
            if (!$existingSku) {
                $db->table('skus')->insert([
                    'product_id' => $productIds[$product['name']],
                    'sku_code'   => $product['sku'],
                    'name'       => $product['name'],
                    'cost_price' => $product['cost_price'],
                    'sale_price' => $product['price'],
                    'unit_price' => $product['price'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $skuIds[$product['sku']] = (int) $db->insertID();
            } else {
                $db->table('skus')
                    ->where('id', (int) $existingSku['id'])
                    ->update([
                        'cost_price' => $product['cost_price'],
                        'sale_price' => $product['price'],
                        'unit_price' => $product['price'],
                        'updated_at' => $now,
                    ]);
                $skuIds[$product['sku']] = (int) $existingSku['id'];
            }
        }

        // Step 3: Create inventory balances for each branch/SKU combination
        foreach ($branchIds as $branchCode => $branchId) {
            foreach ($skuIds as $skuCode => $skuId) {
                $existing = $db->table('inventory_balances')
                    ->where('branch_id', $branchId)
                    ->where('sku_id', $skuId)
                    ->get()
                    ->getFirstRow('array');

                if (!$existing) {
                    // vary quantities by branch for realism
                    $quantities = [
                        'DELL-LAPTOP-001' => ['LAH' => 15, 'KAR' => 12, 'ISL' => 8, 'FAI' => 5, 'MUL' => 4],
                        'HP-CHG-001' => ['LAH' => 30, 'KAR' => 25, 'ISL' => 20, 'FAI' => 15, 'MUL' => 10],
                        'LOG-MOUSE-001' => ['LAH' => 50, 'KAR' => 45, 'ISL' => 40, 'FAI' => 35, 'MUL' => 30],
                        'WIRE-KB-001' => ['LAH' => 25, 'KAR' => 20, 'ISL' => 15, 'FAI' => 10, 'MUL' => 8],
                        'MAC-BOOK-PRO-001' => ['LAH' => 6, 'KAR' => 4, 'ISL' => 3, 'FAI' => 2, 'MUL' => 1],
                    ];

                    $qty = $quantities[$skuCode][$branchCode] ?? 20;
                    $db->table('inventory_balances')->insert([
                        'branch_id'    => $branchId,
                        'sku_id'       => $skuId,
                        'qty_on_hand'  => $qty,
                        'qty_reserved' => 0,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }
        }

        // Step 4: Create multiple orders across different dates (last 30 days)
        $orderData = [
            // Format: ['date' => 'Y-m-d', 'items' => [['sku' => 'XXX', 'qty' => N, 'branch' => 'XXX'], ...]]
            ['date' => '2026-02-11', 'items' => [
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'LAH'],
                ['sku' => 'HP-CHG-001', 'qty' => 2, 'branch' => 'LAH'],
            ]],
            ['date' => '2026-02-12', 'items' => [
                ['sku' => 'LOG-MOUSE-001', 'qty' => 3, 'branch' => 'KAR'],
                ['sku' => 'WIRE-KB-001', 'qty' => 1, 'branch' => 'KAR'],
            ]],
            ['date' => '2026-02-13', 'items' => [
                ['sku' => 'HP-CHG-001', 'qty' => 4, 'branch' => 'ISL'],
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'ISL'],
            ]],
            ['date' => '2026-02-14', 'items' => [
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 2, 'branch' => 'FAI'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 5, 'branch' => 'FAI'],
            ]],
            ['date' => '2026-02-15', 'items' => [
                ['sku' => 'WIRE-KB-001', 'qty' => 2, 'branch' => 'MUL'],
                ['sku' => 'HP-CHG-001', 'qty' => 3, 'branch' => 'MUL'],
            ]],
            ['date' => '2026-02-16', 'items' => [
                ['sku' => 'LOG-MOUSE-001', 'qty' => 6, 'branch' => 'LAH'],
            ]],
            ['date' => '2026-02-17', 'items' => [
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'KAR'],
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'KAR'],
            ]],
            ['date' => '2026-02-18', 'items' => [
                ['sku' => 'WIRE-KB-001', 'qty' => 4, 'branch' => 'ISL'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 2, 'branch' => 'ISL'],
            ]],
            ['date' => '2026-02-19', 'items' => [
                ['sku' => 'HP-CHG-001', 'qty' => 5, 'branch' => 'FAI'],
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'FAI'],
            ]],
            ['date' => '2026-02-20', 'items' => [
                ['sku' => 'LOG-MOUSE-001', 'qty' => 7, 'branch' => 'MUL'],
                ['sku' => 'WIRE-KB-001', 'qty' => 1, 'branch' => 'MUL'],
            ]],
            ['date' => '2026-02-25', 'items' => [
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'LAH'],
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 2, 'branch' => 'LAH'],
            ]],
            ['date' => '2026-02-26', 'items' => [
                ['sku' => 'HP-CHG-001', 'qty' => 6, 'branch' => 'KAR'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 4, 'branch' => 'KAR'],
            ]],
            ['date' => '2026-02-27', 'items' => [
                ['sku' => 'WIRE-KB-001', 'qty' => 3, 'branch' => 'ISL'],
                ['sku' => 'HP-CHG-001', 'qty' => 2, 'branch' => 'ISL'],
            ]],
            ['date' => '2026-02-28', 'items' => [
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'FAI'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 3, 'branch' => 'FAI'],
            ]],
            ['date' => '2026-03-01', 'items' => [
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'MUL'],
                ['sku' => 'WIRE-KB-001', 'qty' => 2, 'branch' => 'MUL'],
            ]],
            ['date' => '2026-03-05', 'items' => [
                ['sku' => 'HP-CHG-001', 'qty' => 8, 'branch' => 'LAH'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 5, 'branch' => 'LAH'],
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'LAH'],
            ]],
            ['date' => '2026-03-06', 'items' => [
                ['sku' => 'WIRE-KB-001', 'qty' => 5, 'branch' => 'KAR'],
                ['sku' => 'HP-CHG-001', 'qty' => 3, 'branch' => 'KAR'],
            ]],
            ['date' => '2026-03-07', 'items' => [
                ['sku' => 'LOG-MOUSE-001', 'qty' => 8, 'branch' => 'ISL'],
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 2, 'branch' => 'ISL'],
            ]],
            ['date' => '2026-03-08', 'items' => [
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'FAI'],
                ['sku' => 'HP-CHG-001', 'qty' => 4, 'branch' => 'FAI'],
            ]],
            ['date' => '2026-03-09', 'items' => [
                ['sku' => 'WIRE-KB-001', 'qty' => 6, 'branch' => 'MUL'],
                ['sku' => 'LOG-MOUSE-001', 'qty' => 4, 'branch' => 'MUL'],
            ]],
            ['date' => '2026-03-10', 'items' => [
                ['sku' => 'HP-CHG-001', 'qty' => 5, 'branch' => 'LAH'],
                ['sku' => 'DELL-LAPTOP-001', 'qty' => 1, 'branch' => 'LAH'],
            ]],
            ['date' => '2026-03-11', 'items' => [
                ['sku' => 'LOG-MOUSE-001', 'qty' => 10, 'branch' => 'KAR'],
                ['sku' => 'WIRE-KB-001', 'qty' => 2, 'branch' => 'KAR'],
            ]],
            ['date' => '2026-03-12', 'items' => [
                ['sku' => 'MAC-BOOK-PRO-001', 'qty' => 1, 'branch' => 'ISL'],
                ['sku' => 'HP-CHG-001', 'qty' => 2, 'branch' => 'ISL'],
            ]],
        ];

        // Get admin user (created by RbacSeeder)
        $adminUser = $db->table('users')->where('email', 'admin@erp.com')->get()->getFirstRow('array');
        $adminId = $adminUser ? (int) $adminUser['id'] : 1;

        foreach ($orderData as $orderEntry) {
            $orderDate = $orderEntry['date'];
            $items = $orderEntry['items'];

            if (empty($items)) continue;

            // Calculate total amount
            $totalAmount = 0;
            foreach ($items as $item) {
                $sku = $db->table('skus')->where('sku_code', $item['sku'])->get()->getFirstRow('array');
                if ($sku) {
                    $totalAmount += (float) $sku['unit_price'] * $item['qty'];
                }
            }

            // Create order
            $db->table('orders')->insert([
                'user_id'      => $adminId,
                'branch_id'    => $branchIds[$items[0]['branch']], // use first item's branch
                'total_amount' => $totalAmount,
                'status'       => 'placed',
                'placed_at'    => $orderDate . ' ' . date('H:i:s'),
                'created_at'   => $orderDate . ' ' . date('H:i:s'),
                'updated_at'   => $orderDate . ' ' . date('H:i:s'),
            ]);
            $orderId = (int) $db->insertID();

            // Add order items
            foreach ($items as $item) {
                $sku = $db->table('skus')->where('sku_code', $item['sku'])->get()->getFirstRow('array');
                if (!$sku) continue;

                $lineTotal = (float) $sku['unit_price'] * $item['qty'];

                $db->table('order_lines')->insert([
                    'order_id'    => $orderId,
                    'sku_id'      => (int) $sku['id'],
                    'quantity'    => $item['qty'],
                    'unit_price'  => (float) $sku['unit_price'],
                    'line_total'  => $lineTotal,
                    'created_at'  => $orderDate . ' ' . date('H:i:s'),
                    'updated_at'  => $orderDate . ' ' . date('H:i:s'),
                ]);
            }
        }
    }
}
