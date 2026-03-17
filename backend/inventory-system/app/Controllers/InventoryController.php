<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\InventoryService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class InventoryController extends BaseController
{
    public function __construct(protected ?InventoryService $inventoryService = null)
    {
        $this->inventoryService ??= new InventoryService();
    }

    public function receive(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager']);

        $data = $this->getJsonBody();

        $rules = [
            'branch_id'      => 'permit_empty|is_natural_no_zero',
            'sku_id'         => 'required|is_natural_no_zero',
            'quantity'       => 'required|numeric|greater_than[0]',
            'notes'          => 'permit_empty',
            'reference_type' => 'permit_empty|max_length[50]',
            'reference_id'   => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $branchId = $this->resolveBranchFromInput($data, 'branch_id');

            $this->inventoryService->receiveStock(
                $branchId,
                (int) $data['sku_id'],
                $data['quantity'],
                AuthContext::id(),
                $data['notes'] ?? null,
                $data['reference_type'] ?? null,
                isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            );

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            $status = 400;
            if (in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true)) {
                $status = 403;
            } elseif (
                str_starts_with($e->getMessage(), 'Invalid date format')
                || str_contains($e->getMessage(), 'must be a positive integer')
                || str_contains($e->getMessage(), 'must be before or equal to')
            ) {
                $status = 422;
            }
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function index(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager']);

            $role = AuthContext::role();
            $branchId = null;

            if ($role === 'manager' || $role === 'sales') {
                $branchId = AuthContext::branchId();
                if ($branchId === null) {
                    throw new RuntimeException('User is not assigned to a branch.');
                }
            } else {
                $requested = $this->request->getGet('branch_id');
                if ($requested !== null && $requested !== '') {
                    $branchId = (int) $requested;
                }
            }

            $items = $this->inventoryService->listInventoryBalances($branchId);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function ledger(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager']);
            [$branchId, $productId, $dateFrom, $dateTo, $page, $perPage] = $this->resolveLedgerFilters();

            $result = $this->inventoryService->listStockLedger(
                $branchId,
                $productId,
                $dateFrom,
                $dateTo,
                $page,
                $perPage,
            );

            return $this->response->setJSON([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function ledgerCsv(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager']);
            [$branchId, $productId, $dateFrom, $dateTo] = $this->resolveLedgerFilters(includePagination: false);

            $items = $this->inventoryService->exportStockLedger(
                $branchId,
                $productId,
                $dateFrom,
                $dateTo,
            );

            $handle = fopen('php://temp', 'w+');
            if ($handle === false) {
                throw new RuntimeException('Unable to generate CSV output.');
            }

            fputcsv($handle, [
                'Date',
                'Branch',
                'Product',
                'SKU Code',
                'Movement Type',
                'Quantity',
                'Reference Type',
                'Reference ID',
                'User',
            ]);

            foreach ($items as $row) {
                fputcsv($handle, [
                    $row['created_at'] ?? '',
                    $row['branch_name'] ?? '',
                    $row['product_name'] ?? '',
                    $row['sku_code'] ?? '',
                    $row['movement_group'] ?? '',
                    (string) ($row['quantity'] ?? 0),
                    $row['reference_label'] ?? '',
                    $row['reference_id'] ?? '',
                    $row['user_name'] ?? 'System',
                ]);
            }

            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            if ($csvContent === false) {
                throw new RuntimeException('Unable to read CSV output.');
            }

            $filename = 'stock-ledger-' . date('Ymd-His') . '.csv';

            return $this->response
                ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody($csvContent);
        } catch (RuntimeException $e) {
            $status = 400;
            if (in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true)) {
                $status = 403;
            } elseif (
                str_starts_with($e->getMessage(), 'Invalid date format')
                || str_contains($e->getMessage(), 'must be a positive integer')
                || str_contains($e->getMessage(), 'must be before or equal to')
            ) {
                $status = 422;
            }
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function adjust(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager']);

        $data = $this->getJsonBody();

        $rules = [
            'branch_id'      => 'permit_empty|is_natural_no_zero',
            'sku_id'         => 'required|is_natural_no_zero',
            'quantity_delta' => 'required|numeric',
            'notes'          => 'permit_empty',
            'reference_type' => 'permit_empty|max_length[50]',
            'reference_id'   => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $branchId = $this->resolveBranchFromInput($data, 'branch_id');

            $this->inventoryService->adjustStock(
                $branchId,
                (int) $data['sku_id'],
                $data['quantity_delta'],
                AuthContext::id(),
                $data['notes'] ?? null,
                $data['reference_type'] ?? null,
                isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            );

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function transfer(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'from_branch_id' => 'required|is_natural_no_zero',
            'to_branch_id'   => 'required|is_natural_no_zero',
            'sku_id'         => 'required|is_natural_no_zero',
            'quantity'       => 'required|numeric|greater_than[0]',
            'notes'          => 'permit_empty',
            'reference_type' => 'permit_empty|max_length[50]',
            'reference_id'   => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $fromBranchId = (int) $data['from_branch_id'];
            $toBranchId = (int) $data['to_branch_id'];

            // Managers can only transfer involving their own branch.
            $role = AuthContext::role();
            if ($role === 'manager') {
                $own = AuthContext::branchId();
                if ($own === null || ($fromBranchId !== $own && $toBranchId !== $own)) {
                    throw new RuntimeException('Forbidden.');
                }
            } else if ($role === 'sales') {
                throw new RuntimeException('Forbidden.');
            }

            $this->inventoryService->transferStock(
                $fromBranchId,
                $toBranchId,
                (int) $data['sku_id'],
                $data['quantity'],
                AuthContext::id(),
                $data['notes'] ?? null,
                $data['reference_type'] ?? null,
                isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            );

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function reserve(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'order_id'    => 'required|is_natural_no_zero',
            'branch_id'   => 'permit_empty|is_natural_no_zero',
            'sku_id'      => 'required|is_natural_no_zero',
            'quantity'    => 'required|numeric|greater_than[0]',
            'expires_at'  => 'permit_empty',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $branchId = $this->resolveBranchFromInput($data, 'branch_id');

            $reservationId = $this->inventoryService->reserveStock(
                (int) $data['order_id'],
                $branchId,
                (int) $data['sku_id'],
                $data['quantity'],
                $data['expires_at'] ?? null,
            );

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'reservation_id' => $reservationId,
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function getJsonBody(): array
    {
        $body = $this->request->getJSON(true);
        if (is_array($body)) {
            return $body;
        }

        return (array) $this->request->getPost();
    }

    private function failValidation(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'success' => false,
            'error'   => 'Validation failed',
            'details' => $this->validator?->getErrors() ?? [],
        ]);
    }

    private function assertBranchAccess(int $branchId): void
    {
        $role = AuthContext::role();
        if ($role === 'manager' || $role === 'sales') {
            $own = AuthContext::branchId();
            if ($own === null || $own !== $branchId) {
                throw new RuntimeException('Forbidden.');
            }
        }
    }

    private function resolveBranchFromInput(array $data, string $key): int
    {
        $role = AuthContext::role();
        if ($role === 'manager' || $role === 'sales') {
            $branchId = AuthContext::branchId();
            if ($branchId === null) {
                throw new RuntimeException('User is not assigned to a branch.');
            }
            return $branchId;
        }

        $value = $data[$key] ?? null;
        if ($value === null || $value === '' || !is_numeric($value) || (int) $value <= 0) {
            throw new RuntimeException($key . ' must be a positive integer.');
        }

        return (int) $value;
    }

    private function normalizeDateFilter($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dt === false || $dt->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid date format. Expected YYYY-MM-DD.');
        }

        return $value;
    }

    /**
     * @param array<int, string> $roles
     */
    private function assertRoleAllowed(array $roles): void
    {
        $role = AuthContext::role();
        if (!in_array($role, $roles, true)) {
            throw new RuntimeException('Forbidden.');
        }
    }

    /**
     * @return array{0:?int,1:?int,2:?string,3:?string,4?:int,5?:int}
     */
    private function resolveLedgerFilters(bool $includePagination = true): array
    {
        $role = AuthContext::role();
        $branchId = null;

        if ($role === 'manager' || $role === 'sales') {
            $branchId = AuthContext::branchId();
            if ($branchId === null) {
                throw new RuntimeException('User is not assigned to a branch.');
            }
        } else {
            $requestedBranch = $this->request->getGet('branch_id');
            if ($requestedBranch !== null && $requestedBranch !== '') {
                if (!ctype_digit((string) $requestedBranch) || (int) $requestedBranch <= 0) {
                    throw new RuntimeException('branch_id must be a positive integer.');
                }
                $branchId = (int) $requestedBranch;
            }
        }

        $productId = null;
        $requestedProduct = $this->request->getGet('product_id');
        if ($requestedProduct !== null && $requestedProduct !== '') {
            if (!ctype_digit((string) $requestedProduct) || (int) $requestedProduct <= 0) {
                throw new RuntimeException('product_id must be a positive integer.');
            }
            $productId = (int) $requestedProduct;
        }

        $dateFrom = $this->normalizeDateFilter($this->request->getGet('date_from'));
        $dateTo = $this->normalizeDateFilter($this->request->getGet('date_to'));

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new RuntimeException('date_from must be before or equal to date_to.');
        }

        if (!$includePagination) {
            return [$branchId, $productId, $dateFrom, $dateTo];
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = max(1, min(100, (int) ($this->request->getGet('per_page') ?? 20)));

        return [$branchId, $productId, $dateFrom, $dateTo, $page, $perPage];
    }
}
