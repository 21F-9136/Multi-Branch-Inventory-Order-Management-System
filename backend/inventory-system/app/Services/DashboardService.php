<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

class DashboardService
{
    protected BaseConnection $db;
    protected int $cacheTtlSeconds = 120;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(?int $branchId = null, int $lowStockThreshold = 10, int $trendDays = 30): array
    {
        $trendDays = max(7, min(30, $trendDays));

        $cache = service('cache');
        $cacheKey = sprintf(
            'dashboard_summary_v1_branch_%s_threshold_%d_days_%d',
            $branchId === null ? 'all' : (string) $branchId,
            $lowStockThreshold,
            $trendDays
        );

        try {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $e) {
            // Cache errors must not break dashboard API responses.
        }

        $now = new \DateTimeImmutable('now');
        $todayStart = $now->setTime(0, 0, 0);
        $tomorrowStart = $todayStart->modify('+1 day');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $trendStart = $todayStart->modify('-' . ($trendDays - 1) . ' days');
        $monthlyStart = $monthStart->modify('-11 months');

        $totalSalesRow = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed'),
            $branchId,
            'o'
        )
            ->get()
            ->getFirstRow('array');

        $totalCogsRow = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('COALESCE(SUM(ol.quantity * COALESCE(s.cost_price, 0)), 0) as total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->join('skus s', 's.id = ol.sku_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed'),
            $branchId,
            'o'
        )
            ->get()
            ->getFirstRow('array');

        $inventoryValueBuilder = $this->db->table('inventory_balances ib')
            ->select('COALESCE(SUM((ib.qty_on_hand - ib.qty_reserved) * COALESCE(s.cost_price, 0)), 0) as total_value', false)
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->where('ib.deleted_at IS NULL');
        if ($branchId !== null) {
            $inventoryValueBuilder->where('ib.branch_id', $branchId);
        }
        $inventoryValueRow = $inventoryValueBuilder
            ->get()
            ->getFirstRow('array');

        $totalSales = (float) ($totalSalesRow['total'] ?? 0);
        $totalCogs = (float) ($totalCogsRow['total'] ?? 0);
        $totalInventoryValue = (float) ($inventoryValueRow['total_value'] ?? 0);
        $totalProfit = $totalSales - $totalCogs;

        $todaySalesRow = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->where('o.placed_at >=', $todayStart->format('Y-m-d H:i:s'))
                ->where('o.placed_at <', $tomorrowStart->format('Y-m-d H:i:s')),
            $branchId,
            'o'
        )
            ->get()
            ->getFirstRow('array');

        $monthSalesRow = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->where('o.placed_at >=', $monthStart->format('Y-m-d H:i:s')),
            $branchId,
            'o'
        )
            ->get()
            ->getFirstRow('array');

        $ordersCountRow = $this->applyBranchFilter(
            $this->db->table('orders o')
                ->select('COUNT(*) as count', false)
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed'),
            $branchId,
            'o'
        )
            ->get()
            ->getFirstRow('array');

        $productsCountRow = $this->db->table('products p')
            ->select('COUNT(*) as count', false)
            ->where('p.deleted_at IS NULL')
            ->get()
            ->getFirstRow('array');

        $inventoryBuilder = $this->db->table('inventory_balances ib')
            ->select('COALESCE(SUM(ib.qty_on_hand), 0) as total', false)
            ->where('ib.deleted_at IS NULL');
        if ($branchId !== null) {
            $inventoryBuilder->where('ib.branch_id', $branchId);
        }
        $inventoryRow = $inventoryBuilder
            ->get()
            ->getFirstRow('array');

        $branchesBuilder = $this->db->table('branches b')
            ->select('COUNT(*) as count', false)
            ->where('b.deleted_at IS NULL')
            ->where('b.status', 'active');
        if ($branchId !== null) {
            $branchesBuilder->where('b.id', $branchId);
        }
        $activeBranchesRow = $branchesBuilder
            ->get()
            ->getFirstRow('array');

        $topProducts = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('p.id as product_id, p.name as product_name, COALESCE(SUM(ol.quantity), 0) as qty_sold, COALESCE(SUM(ol.line_total), 0) as sales_total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->join('skus s', 's.id = ol.sku_id')
                ->join('products p', 'p.id = s.product_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->groupBy('p.id')
                ->orderBy('qty_sold', 'DESC')
                ->limit(5),
            $branchId,
            'o'
        )
            ->get()
            ->getResultArray();

        $lowStock = $this->db
            ->table('inventory_balances ib')
            ->select('ib.branch_id, b.name as branch_name, ib.sku_id, s.sku_code, p.name as product_name, ib.qty_on_hand, ib.qty_reserved, (ib.qty_on_hand - ib.qty_reserved) as qty_available', false)
            ->join('branches b', 'b.id = ib.branch_id', 'left')
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('ib.deleted_at IS NULL')
            ->where('(ib.qty_on_hand - ib.qty_reserved) <=', $lowStockThreshold, false);

        if ($branchId !== null) {
            $lowStock->where('ib.branch_id', $branchId);
        }

        $lowStockItems = $lowStock
            ->orderBy('qty_available', 'ASC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $trendRows = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select('DATE(o.placed_at) as day, COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total', false)
                ->join('orders o', 'o.id = ol.order_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->where('o.placed_at >=', $trendStart->format('Y-m-d H:i:s'))
                ->groupBy('day')
                ->orderBy('day', 'ASC'),
            $branchId,
            'o'
        )
            ->get()
            ->getResultArray();

        $trendMap = [];
        foreach ($trendRows as $r) {
            $trendMap[(string) $r['day']] = (float) $r['total'];
        }

        $trend = [];
        for ($i = 0; $i < $trendDays; $i++) {
            $d = $trendStart->modify('+' . $i . ' days')->format('Y-m-d');
            $trend[] = ['day' => $d, 'total' => $trendMap[$d] ?? 0.0];
        }

        $ordersTrendRows = $this->applyBranchFilter(
            $this->db->table('orders o')
                ->select('DATE(o.placed_at) as day, COUNT(*) as count', false)
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->where('o.placed_at >=', $trendStart->format('Y-m-d H:i:s'))
                ->groupBy('day')
                ->orderBy('day', 'ASC'),
            $branchId,
            'o'
        )
            ->get()
            ->getResultArray();

        $ordersTrendMap = [];
        foreach ($ordersTrendRows as $r) {
            $ordersTrendMap[(string) $r['day']] = (int) $r['count'];
        }

        $ordersTrend = [];
        for ($i = 0; $i < $trendDays; $i++) {
            $d = $trendStart->modify('+' . $i . ' days')->format('Y-m-d');
            $ordersTrend[] = ['day' => $d, 'count' => $ordersTrendMap[$d] ?? 0];
        }

        $monthlyRows = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select("DATE_FORMAT(o.placed_at, '%Y-%m') as ym, COALESCE(SUM(ol.quantity * ol.unit_price), 0) as total", false)
                ->join('orders o', 'o.id = ol.order_id')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->where('o.placed_at >=', $monthlyStart->format('Y-m-d H:i:s'))
                ->groupBy('ym')
                ->orderBy('ym', 'ASC'),
            $branchId,
            'o'
        )
            ->get()
            ->getResultArray();

        $monthlyMap = [];
        foreach ($monthlyRows as $r) {
            $monthlyMap[(string) $r['ym']] = (float) $r['total'];
        }

        $monthlySales = [];
        for ($i = 0; $i < 12; $i++) {
            $monthDate = $monthlyStart->modify('+' . $i . ' months');
            $monthKey = $monthDate->format('Y-m');
            $monthlySales[] = [
                'month_key' => $monthKey,
                'month' => $monthDate->format('M Y'),
                'total' => $monthlyMap[$monthKey] ?? 0.0,
            ];
        }

        $categorySales = $this->applyBranchFilter(
            $this->db->table('order_lines ol')
                ->select("COALESCE(NULLIF(TRIM(c.name), ''), 'Uncategorized') as category, COALESCE(SUM(ol.line_total), 0) as total", false)
                ->join('orders o', 'o.id = ol.order_id')
                ->join('skus s', 's.id = ol.sku_id')
                ->join('products p', 'p.id = s.product_id')
                ->join('categories c', 'c.id = p.category_id', 'left')
                ->where('o.deleted_at IS NULL')
                ->where('o.status', 'placed')
                ->groupBy('category')
                ->orderBy('total', 'DESC'),
            $branchId,
            'o'
        )
            ->get()
            ->getResultArray();

        $branchInventoryBuilder = $this->db->table('inventory_balances ib')
            ->select('b.id as branch_id, b.name as branch_name, COALESCE(SUM((ib.qty_on_hand - ib.qty_reserved) * COALESCE(s.cost_price, 0)), 0) as total_value', false)
            ->join('branches b', 'b.id = ib.branch_id')
            ->join('skus s', 's.id = ib.sku_id', 'left')
            ->where('ib.deleted_at IS NULL')
            ->where('b.deleted_at IS NULL')
            ->groupBy('b.id')
            ->groupBy('b.name')
            ->orderBy('total_value', 'DESC');
        if ($branchId !== null) {
            $branchInventoryBuilder->where('ib.branch_id', $branchId);
        }
        $branchInventoryValue = $branchInventoryBuilder
            ->get()
            ->getResultArray();

        $result = [
            'total_sales'    => $totalSales,
            'total_cogs'     => $totalCogs,
            'total_expenses' => $totalCogs,
            'total_inventory_value' => $totalInventoryValue,
            'total_profit'   => $totalProfit,
            'total_orders'   => (int) ($ordersCountRow['count'] ?? 0),
            'total_products' => (int) ($productsCountRow['count'] ?? 0),
            'total_inventory'=> (float) ($inventoryRow['total'] ?? 0),
            'active_branches'=> (int) ($activeBranchesRow['count'] ?? 0),
            'low_stock_count'=> count($lowStockItems),
            'today_sales'   => (float) ($todaySalesRow['total'] ?? 0),
            'monthly_sales' => (float) ($monthSalesRow['total'] ?? 0),
            'top_products'  => $topProducts,
            'low_stock'     => $lowStockItems,
            'sales_chart'   => $trend,
            'sales_trend'   => $trend,
            'orders_trend'  => $ordersTrend,
            'monthly_sales_chart' => $monthlySales,
            'category_sales' => $categorySales,
            'branch_inventory_value' => $branchInventoryValue,
            'top_selling_products' => $topProducts,
        ];

        try {
            $cache->save($cacheKey, $result, $this->cacheTtlSeconds);
        } catch (Throwable $e) {
            // Cache write failure should not fail the request.
        }

        return $result;
    }

    private function applyBranchFilter($builder, ?int $branchId, string $alias = 'o')
    {
        if ($branchId !== null) {
            $builder->where($alias . '.branch_id', $branchId);
        }

        return $builder;
    }
}
