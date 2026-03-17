<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class OrderController extends BaseController
{
    public function __construct(protected ?OrderService $orderService = null)
    {
        $this->orderService ??= new OrderService();
    }

    public function create(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

        $data = $this->getJsonBody();

        $rules = [
            'branch_id'  => 'required|is_natural_no_zero',
            'status'     => 'permit_empty|max_length[30]',
            'placed_at'  => 'permit_empty',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        $lines = $data['lines'] ?? null;
        if ((!is_array($lines) || $lines === []) && is_array($data['items'] ?? null)) {
            $lines = [];
            foreach ($data['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $qty = $item['qty'] ?? $item['quantity'] ?? null;
                if ($qty === null) {
                    continue;
                }

                $skuId = isset($item['sku_id']) ? (int) $item['sku_id'] : 0;
                if ($skuId > 0) {
                    $lines[] = [
                        'sku_id' => $skuId,
                        'quantity' => $qty,
                        'unit_price' => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                    ];
                    continue;
                }

                $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                if ($productId <= 0) {
                    continue;
                }

                try {
                    $skus = $this->orderService->listProductSkus($productId);
                } catch (RuntimeException) {
                    $skus = [];
                }

                if ($skus === []) {
                    continue;
                }

                $sku = $skus[0];
                $lines[] = [
                    'sku_id' => (int) $sku['id'],
                    'quantity' => $qty,
                    'unit_price' => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                ];
            }
        }

        if (!is_array($lines) || $lines === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error'   => 'Validation failed',
                'details' => ['lines' => 'Lines are required and must be a non-empty array.'],
            ]);
        }

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'error'   => 'Validation failed',
                    'details' => ['lines' => "Line #{$i} must be an object."],
                ]);
            }

            $lineRules = [
                'sku_id'     => 'required|is_natural_no_zero',
                'quantity'   => 'required|numeric|greater_than[0]',
                'unit_price' => 'permit_empty|decimal',
            ];

            if (!$this->validateData($line, $lineRules)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'error'   => 'Validation failed',
                    'details' => ["lines[{$i}]" => $this->validator?->getErrors() ?? []],
                ]);
            }
        }

        try {
            $branchId = $this->resolveBranchFromInput($data, 'branch_id');

            $orderId = $this->orderService->createOrder([
                'branch_id' => $branchId,
                'user_id'   => AuthContext::id(),
                'status'    => $data['status'] ?? 'draft',
                'placed_at' => $data['placed_at'] ?? null,
            ], $lines);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'order_id' => $orderId,
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

    public function index(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

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

            $items = $this->orderService->listOrders($branchId);

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

    public function show(int $id): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $item = $this->orderService->getOrder($id);
            $this->assertBranchAccess((int) $item['branch_id']);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'item' => $item,
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

    public function place(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

        $data = $this->getJsonBody();

        $rules = [
            'order_id'        => 'required|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $order = $this->orderService->getOrder((int) $data['order_id']);
            $this->assertBranchAccess((int) $order['branch_id']);

            $this->orderService->placeOrder(
                (int) $data['order_id'],
                AuthContext::id(),
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

    public function cancel(int $id): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

        try {
            $order = $this->orderService->getOrder($id);
            $this->assertBranchAccess((int) $order['branch_id']);

            $this->orderService->cancelOrder($id, AuthContext::id());

            return $this->response->setJSON([
                'message' => 'Order cancelled successfully',
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function complete(int $id): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

        try {
            $this->orderService->completeOrder(
                $id,
                AuthContext::id(),
                (string) (AuthContext::role() ?? ''),
                AuthContext::branchId(),
            );

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Order completed successfully',
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function items(): ResponseInterface
    {
        try {
            $db = \Config\Database::connect();
            
            // Fetch order lines with product information
            $items = $db->table('order_lines ol')
                ->select('ol.id, ol.sku_id, ol.quantity, ol.unit_price, ol.created_at, p.id as product_id, p.name as product_name')
                ->join('skus s', 's.id = ol.sku_id', 'LEFT')
                ->join('products p', 'p.id = s.product_id', 'LEFT')
                ->orderBy('ol.created_at', 'DESC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
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

    private function resolveBranchFromInput(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '' || !is_numeric($value) || (int) $value <= 0) {
            throw new RuntimeException($key . ' must be a positive integer.');
        }

        return (int) $value;
    }
}
