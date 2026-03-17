<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class ManagerDashboardService
{
    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(int $branchId, int $trendDays = 30): array
    {
        $trendDays = max(7, min(30, $trendDays));

        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $tomorrowStart = $todayStart->modify('+1 day');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $trendStart = $todayStart->modify('-' . ($trendDays - 1) . ' days');

        $todaySalesRow = $this->db->table('order_lines ol')
            ->select('COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
            ->join('orders o', 'o.id = ol.order_id')
            ->where('o.deleted_at IS NULL')
            ->where('o.status', 'placed')
            ->where('o.branch_id', $branchId)
            ->where('o.placed_at >=', $todayStart->format('Y-m-d H:i:s'))
            ->where('o.placed_at <', $tomorrowStart->format('Y-m-d H:i:s'))
            ->get()
            ->getFirstRow('array');

        $monthlySalesRow = $this->db->table('order_lines ol')
            ->select('COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
            ->join('orders o', 'o.id = ol.order_id')
            ->where('o.deleted_at IS NULL')
            ->where('o.status', 'placed')
            ->where('o.branch_id', $branchId)
            ->where('o.placed_at >=', $monthStart->format('Y-m-d H:i:s'))
            ->get()
            ->getFirstRow('array');

        $totalOrdersRow = $this->db->table('orders o')
            ->select('COUNT(*) as count', false)
            ->where('o.deleted_at IS NULL')
            ->where('o.status', 'placed')
            ->where('o.branch_id', $branchId)
            ->get()
            ->getFirstRow('array');

        $inventoryValueRow = $this->db->table('inventory_balances ib')
            ->select('COALESCE(SUM((ib.qty_on_hand - ib.qty_reserved) * COALESCE(s.cost_price, 0)), 0) as total', false)
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->where('ib.deleted_at IS NULL')
            ->where('ib.branch_id', $branchId)
            ->get()
            ->getFirstRow('array');

        $lowStockItems = $this->db->table('inventory_balances ib')
            ->select('ib.sku_id, s.sku_code, p.name as product_name, (ib.qty_on_hand - ib.qty_reserved) as available_quantity, COALESCE(s.reorder_level, 10) as reorder_level', false)
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('ib.deleted_at IS NULL')
            ->where('ib.branch_id', $branchId)
            ->where('(ib.qty_on_hand - ib.qty_reserved) <= COALESCE(s.reorder_level, 10)', null, false)
            ->orderBy('available_quantity', 'ASC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $salesTrendRows = $this->db->table('order_lines ol')
            ->select('DATE(o.placed_at) as day, COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
            ->join('orders o', 'o.id = ol.order_id')
            ->where('o.deleted_at IS NULL')
            ->where('o.status', 'placed')
            ->where('o.branch_id', $branchId)
            ->where('o.placed_at >=', $trendStart->format('Y-m-d H:i:s'))
            ->groupBy('day')
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $trendMap = [];
        foreach ($salesTrendRows as $row) {
            $trendMap[(string) $row['day']] = (float) ($row['total'] ?? 0);
        }

        $salesTrend = [];
        for ($i = 0; $i < $trendDays; $i++) {
            $day = $trendStart->modify('+' . $i . ' days')->format('Y-m-d');
            $salesTrend[] = [
                'day' => $day,
                'total' => $trendMap[$day] ?? 0.0,
            ];
        }

        $topSellingProducts = $this->db->table('order_lines ol')
            ->select('ol.sku_id, s.sku_code, p.name as product_name, COALESCE(SUM(ol.quantity), 0) as qty_sold, COALESCE(SUM(ol.line_total), 0) as sales_total', false)
            ->join('orders o', 'o.id = ol.order_id')
            ->join('skus s', 's.id = ol.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('o.deleted_at IS NULL')
            ->where('o.status', 'placed')
            ->where('o.branch_id', $branchId)
            ->groupBy('ol.sku_id')
            ->orderBy('qty_sold', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $branchRow = $this->db->table('branches')
            ->select('id, name')
            ->where('id', $branchId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getFirstRow('array');

        return [
            'branch_id' => $branchId,
            'branch_name' => $branchRow['name'] ?? ('Branch ' . $branchId),
            'today_sales' => (float) ($todaySalesRow['total'] ?? 0),
            'monthly_sales' => (float) ($monthlySalesRow['total'] ?? 0),
            'total_orders' => (int) ($totalOrdersRow['count'] ?? 0),
            'inventory_value' => (float) ($inventoryValueRow['total'] ?? 0),
            'low_stock_count' => count($lowStockItems),
            'low_stock_items' => $lowStockItems,
            'sales_trend' => $salesTrend,
            'top_selling_products' => $topSellingProducts,
        ];
    }
}
