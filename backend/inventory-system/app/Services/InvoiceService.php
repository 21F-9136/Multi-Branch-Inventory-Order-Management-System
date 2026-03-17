<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class InvoiceService
{
    protected BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInvoices(string $role, ?int $userId, ?int $branchId, ?int $requestedBranchId = null): array
    {
        $q = $this->db
            ->table('orders o')
            ->select('o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.created_at')
            ->join('branches b', 'b.id = o.branch_id', 'left')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('o.deleted_at IS NULL');

        if ($requestedBranchId !== null && $requestedBranchId > 0 && ($role === 'admin' || $role === 'super_admin')) {
            $q->where('o.branch_id', $requestedBranchId);
        }

        $this->applyInvoiceScope($q, $role, $userId, $branchId);

        $rows = $q->orderBy('o.id', 'DESC')->get()->getResultArray();

        return array_map(static function (array $row): array {
            $orderId = (int) ($row['id'] ?? 0);
            $row['invoice_id'] = 'INV-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
            return $row;
        }, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInvoice(int $orderId, string $role, ?int $userId, ?int $branchId): array
    {
        $base = $this->db
            ->table('orders o')
            ->select('o.id, o.branch_id, b.name as branch_name, o.user_id, u.name as creator_name, o.status, o.subtotal, o.tax_amount, o.grand_total, o.created_at')
            ->join('branches b', 'b.id = o.branch_id', 'left')
            ->join('users u', 'u.id = o.user_id', 'left')
            ->where('o.id', $orderId)
            ->where('o.deleted_at IS NULL');

        $exists = (clone $base)->get()->getRowArray();
        if ($exists === null) {
            throw new RuntimeException('Invoice source order not found.');
        }

        $scoped = clone $base;
        $this->applyInvoiceScope($scoped, $role, $userId, $branchId);
        $order = $scoped->get()->getRowArray();
        if ($order === null) {
            throw new RuntimeException('Forbidden.');
        }

        $order['invoice_id'] = 'INV-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
        $order['items'] = $this->db
            ->table('order_lines ol')
            ->select('ol.id, ol.order_id, ol.sku_id, ol.quantity, ol.unit_price, ol.line_total, s.sku_code, s.name as sku_name, s.product_id, p.name as product_name')
            ->join('skus s', 's.id = ol.sku_id', 'left')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('ol.order_id', $orderId)
            ->get()
            ->getResultArray();

        return $order;
    }

    private function applyInvoiceScope(BaseBuilder $q, string $role, ?int $userId, ?int $branchId): void
    {
        if ($role === 'super_admin' || $role === 'admin') {
            return;
        }

        if ($role === 'manager') {
            if ($branchId === null) {
                throw new RuntimeException('User is not assigned to a branch.');
            }

            $q->where('o.branch_id', $branchId);
            return;
        }

        if ($role === 'sales') {
            if ($branchId === null) {
                throw new RuntimeException('User is not assigned to a branch.');
            }

            $q->where('o.branch_id', $branchId);
            return;
        }

        throw new RuntimeException('Forbidden.');
    }
}
