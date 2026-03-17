<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDashboardPerformanceIndexes extends Migration
{
    public function up()
    {
        // Composite index to speed up dashboard order filtering by status/soft-delete/branch/date.
        try {
            $this->db->query('ALTER TABLE orders ADD INDEX idx_orders_status_deleted_branch_placed (status, deleted_at, branch_id, placed_at)');
        } catch (\Throwable $e) {
        }

        // Composite index to accelerate joins and grouping from order lines.
        try {
            $this->db->query('ALTER TABLE order_lines ADD INDEX idx_order_lines_order_sku (order_id, sku_id)');
        } catch (\Throwable $e) {
        }

        // Product/category lookups in category-sales aggregates.
        try {
            $this->db->query('ALTER TABLE products ADD INDEX idx_products_deleted_category (deleted_at, category_id)');
        } catch (\Throwable $e) {
        }

        // Inventory valuation and low-stock scans with branch filtering.
        try {
            $this->db->query('ALTER TABLE inventory_balances ADD INDEX idx_inventory_deleted_branch_sku (deleted_at, branch_id, sku_id)');
        } catch (\Throwable $e) {
        }

        // SKU -> product join path for top products/category sales.
        try {
            $this->db->query('ALTER TABLE skus ADD INDEX idx_skus_deleted_product (deleted_at, product_id)');
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        try {
            $this->db->query('ALTER TABLE orders DROP INDEX idx_orders_status_deleted_branch_placed');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE order_lines DROP INDEX idx_order_lines_order_sku');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE products DROP INDEX idx_products_deleted_category');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE inventory_balances DROP INDEX idx_inventory_deleted_branch_sku');
        } catch (\Throwable $e) {
        }

        try {
            $this->db->query('ALTER TABLE skus DROP INDEX idx_skus_deleted_product');
        } catch (\Throwable $e) {
        }
    }
}
