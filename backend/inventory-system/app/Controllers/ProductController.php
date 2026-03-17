<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\ProductService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class ProductController extends BaseController
{
    public function __construct(protected ?ProductService $productService = null)
    {
        $this->productService ??= new ProductService();
    }

    public function show(int $id): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $item = $this->productService->getProduct($id);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'item' => $item,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function listSkus(int $productId): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $items = array_map(static function (array $sku): array {
                $salePrice = isset($sku['sale_price']) ? (float) $sku['sale_price'] : 0.0;
                $unitPrice = isset($sku['unit_price']) ? (float) $sku['unit_price'] : 0.0;
                $costPrice = isset($sku['cost_price']) ? (float) $sku['cost_price'] : 0.0;

                $price = $salePrice > 0
                    ? $salePrice
                    : ($unitPrice > 0 ? $unitPrice : $costPrice);

                $sku['sku_id'] = (int) ($sku['id'] ?? 0);
                $sku['price'] = $price;

                return $sku;
            }, $this->productService->listSkusForProduct($productId));

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function createProduct(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin']);

        $data = $this->getJsonBody();

        $rules = [
            'name'        => 'required|min_length[2]',
            'brand_id'    => 'permit_empty|is_natural_no_zero',
            'category_id' => 'permit_empty|is_natural_no_zero',
            'description' => 'permit_empty',
            'is_active'   => 'permit_empty|in_list[0,1]',
            'status'      => 'permit_empty|max_length[50]',
            'cost_price'  => 'permit_empty|decimal',
            'sale_price'  => 'permit_empty|decimal',
            'tax_percent' => 'permit_empty|decimal',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $productId = $this->productService->createProduct([
                'name'        => $data['name'],
                'brand_id'    => array_key_exists('brand_id', $data) ? ($data['brand_id'] === '' ? null : (int) $data['brand_id']) : null,
                'category_id' => array_key_exists('category_id', $data) ? ($data['category_id'] === '' ? null : (int) $data['category_id']) : null,
                'description' => $data['description'] ?? null,
                'is_active'   => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'status'      => $data['status'] ?? null,
                'tax_percent' => array_key_exists('tax_percent', $data) ? ($data['tax_percent'] === '' ? null : $data['tax_percent']) : null,
            ]);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'product_id' => $productId,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function index(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $items = $this->productService->listProducts();

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function updateProduct(int $id): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin']);

        $data = $this->getJsonBody();

        $rules = [
            'name'        => 'permit_empty|min_length[2]',
            'brand_id'    => 'permit_empty|is_natural_no_zero',
            'category_id' => 'permit_empty|is_natural_no_zero',
            'description' => 'permit_empty',
            'is_active'   => 'permit_empty|in_list[0,1]',
            'status'      => 'permit_empty|max_length[50]',
            'cost_price'  => 'permit_empty|decimal',
            'sale_price'  => 'permit_empty|decimal',
            'tax_percent' => 'permit_empty|decimal',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $this->productService->updateProduct($id, [
                'name'        => $data['name'] ?? null,
                'brand_id'    => array_key_exists('brand_id', $data) ? ($data['brand_id'] === '' ? null : (int) $data['brand_id']) : null,
                'category_id' => array_key_exists('category_id', $data) ? ($data['category_id'] === '' ? null : (int) $data['category_id']) : null,
                'description' => array_key_exists('description', $data) ? ($data['description'] === '' ? null : $data['description']) : null,
                'is_active'   => array_key_exists('is_active', $data) ? (int) $data['is_active'] : null,
                'status'      => array_key_exists('status', $data) ? ($data['status'] === '' ? null : $data['status']) : null,
                'cost_price'  => array_key_exists('cost_price', $data) ? ($data['cost_price'] === '' ? null : $data['cost_price']) : null,
                'sale_price'  => array_key_exists('sale_price', $data) ? ($data['sale_price'] === '' ? null : $data['sale_price']) : null,
                'tax_percent' => array_key_exists('tax_percent', $data) ? ($data['tax_percent'] === '' ? null : $data['tax_percent']) : null,
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function deleteProduct(int $id): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin']);

        try {
            $this->productService->deleteProduct($id);
            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function createSku(): ResponseInterface
    {
        $this->assertRoleAllowed(['super_admin', 'admin']);

        $data = $this->getJsonBody();

        $rules = [
            'product_id' => 'required|is_natural_no_zero',
            'sku_code'   => 'required|min_length[1]|max_length[80]',
            'barcode'    => 'permit_empty|max_length[80]',
            'name'       => 'permit_empty|max_length[200]',
            'unit_price' => 'permit_empty|decimal',
            'cost_price' => 'permit_empty|decimal',
            'sale_price' => 'permit_empty|decimal',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $skuId = $this->productService->createSku([
                'product_id' => (int) $data['product_id'],
                'sku_code'   => $data['sku_code'],
                'barcode'    => $data['barcode'] ?? null,
                'name'       => $data['name'] ?? null,
                'cost_price' => array_key_exists('cost_price', $data) ? ($data['cost_price'] === '' ? null : $data['cost_price']) : null,
                'sale_price' => array_key_exists('sale_price', $data) ? ($data['sale_price'] === '' ? null : $data['sale_price']) : null,
                'unit_price' => $data['unit_price'] ?? ($data['sale_price'] ?? 0),
            ]);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'sku_id' => $skuId,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
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
}
