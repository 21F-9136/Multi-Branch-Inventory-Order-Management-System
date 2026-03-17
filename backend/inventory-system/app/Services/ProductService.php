<?php

namespace App\Services;

use App\Models\ProductModel;
use App\Models\SkuModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class ProductService
{
    /** @var array<int, string> */
    private array $reservedGenericNames = [
        'dell',
        'hp',
        'apple',
        'logitech',
        'laptop',
        'accessories',
    ];

    protected BaseConnection $db;

    public function __construct(
        protected ProductModel $products = new ProductModel(),
        protected SkuModel $skus = new SkuModel(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * @param array{name:string,brand_id?:int|null,category_id?:int|null,description?:string|null,is_active?:int,status?:string|null,tax_percent?:string|float|int|null} $data
     * @return int|string
     */
    public function createProduct(array $data)
    {
        $name = trim((string) $data['name']);
        $brandId = array_key_exists('brand_id', $data) ? ($data['brand_id'] === null ? null : (int) $data['brand_id']) : null;
        $categoryId = array_key_exists('category_id', $data) ? ($data['category_id'] === null ? null : (int) $data['category_id']) : null;

        $this->assertProductNameIsRealItem($name, $brandId, $categoryId);

        $this->products->insert([
            'name'        => $name,
            'brand_id'    => $brandId,
            'category_id' => $categoryId,
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? 1,
            'status'      => $data['status'] ?? null,
            'tax_percent' => array_key_exists('tax_percent', $data) ? ($data['tax_percent'] === null ? null : (float) $data['tax_percent']) : null,
        ]);

        return $this->products->getInsertID();
    }

    /**
        * @param array{name?:string|null,brand_id?:int|null,category_id?:int|null,description?:string|null,is_active?:int|null,status?:string|null,cost_price?:string|float|int|null,sale_price?:string|float|int|null,tax_percent?:string|float|int|null} $data
     */
    public function updateProduct(int $productId, array $data): void
    {
        $product = $this->products->find($productId);
        if ($product === null || !empty($product['deleted_at'])) {
            throw new RuntimeException('Product not found.');
        }

        $update = [];
        $nextName = array_key_exists('name', $data) && $data['name'] !== null
            ? trim((string) $data['name'])
            : trim((string) ($product['name'] ?? ''));
        $nextBrandId = array_key_exists('brand_id', $data)
            ? ($data['brand_id'] === null ? null : (int) $data['brand_id'])
            : (isset($product['brand_id']) && $product['brand_id'] !== null ? (int) $product['brand_id'] : null);
        $nextCategoryId = array_key_exists('category_id', $data)
            ? ($data['category_id'] === null ? null : (int) $data['category_id'])
            : (isset($product['category_id']) && $product['category_id'] !== null ? (int) $product['category_id'] : null);

        $this->assertProductNameIsRealItem($nextName, $nextBrandId, $nextCategoryId);

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $update['name'] = $nextName;
        }
        if (array_key_exists('brand_id', $data)) {
            $update['brand_id'] = $nextBrandId;
        }
        if (array_key_exists('category_id', $data)) {
            $update['category_id'] = $nextCategoryId;
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = $data['description'];
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $update['is_active'] = (int) $data['is_active'];
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }
        if (array_key_exists('tax_percent', $data)) {
            $update['tax_percent'] = $data['tax_percent'] === null ? null : (float) $data['tax_percent'];
        }

        if ($update !== []) {
            $this->products->update($productId, $update);
        }

        // Backward compatibility: if legacy clients send pricing via product update,
        // apply it to the product's primary SKU instead of product master data.
        $hasSkuPricing = array_key_exists('cost_price', $data) || array_key_exists('sale_price', $data);
        if ($hasSkuPricing) {
            $primarySku = $this->skus
                ->where('product_id', $productId)
                ->where('deleted_at', null)
                ->orderBy('id', 'ASC')
                ->first();

            if ($primarySku !== null) {
                $skuUpdate = [];

                if (array_key_exists('cost_price', $data)) {
                    $skuUpdate['cost_price'] = $data['cost_price'] === null ? null : (float) $data['cost_price'];
                }

                if (array_key_exists('sale_price', $data)) {
                    $salePrice = $data['sale_price'] === null ? null : (float) $data['sale_price'];
                    $skuUpdate['sale_price'] = $salePrice;
                    if ($salePrice !== null) {
                        $skuUpdate['unit_price'] = $salePrice;
                    }
                }

                if ($skuUpdate !== []) {
                    $this->skus->update((int) $primarySku['id'], $skuUpdate);
                }
            }
        }
    }

    public function deleteProduct(int $productId): void
    {
        $product = $this->products->find($productId);
        if ($product === null || !empty($product['deleted_at'])) {
            throw new RuntimeException('Product not found.');
        }

        $this->products->delete($productId);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProduct(int $productId): array
    {
        $product = $this->db
            ->table('products p')
            ->select('p.id, p.name, p.brand_id, b.name as brand_name, p.category_id, c.name as category_name, p.description, p.is_active, p.status, p.tax_percent, p.created_at, p.updated_at, p.deleted_at')
            ->join('brands b', 'b.id = p.brand_id', 'left')
            ->join('categories c', 'c.id = p.category_id', 'left')
            ->where('p.id', $productId)
            ->where('p.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if ($product === null || !empty($product['deleted_at'])) {
            throw new RuntimeException('Product not found.');
        }

        return $product;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSkusForProduct(int $productId): array
    {
        $product = $this->products->find($productId);
        if ($product === null || !empty($product['deleted_at'])) {
            throw new RuntimeException('Product not found.');
        }

        return $this->skus
            ->where('product_id', $productId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * @param array{product_id:int,sku_code:string,barcode?:string|null,name?:string|null,unit_price?:string|float|int,cost_price?:string|float|int|null,sale_price?:string|float|int|null} $data
     * @return int|string
     */
    public function createSku(array $data)
    {
        $product = $this->products->find($data['product_id']);
        if ($product === null) {
            throw new RuntimeException('Product not found.');
        }

        $this->skus->insert([
            'product_id' => (int) $data['product_id'],
            'sku_code'   => $data['sku_code'],
            'barcode'    => $data['barcode'] ?? null,
            'cost_price' => array_key_exists('cost_price', $data) ? ($data['cost_price'] === null ? null : (float) $data['cost_price']) : null,
            'sale_price' => array_key_exists('sale_price', $data) ? ($data['sale_price'] === null ? null : (float) $data['sale_price']) : null,
            'name'       => $data['name'] ?? null,
            // Keep unit_price for existing code paths; prefer sale_price when supplied.
            'unit_price' => array_key_exists('sale_price', $data) && $data['sale_price'] !== null
                ? (float) $data['sale_price']
                : ($data['unit_price'] ?? 0),
        ]);

            $skuId = (int) $this->skus->getInsertID();
            $this->initializeInventoryBalancesForSku($skuId);

            return $skuId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProducts(): array
    {
        return $this->db
            ->table('products p')
            ->select('p.id, p.name, p.brand_id, b.name as brand_name, p.category_id, c.name as category_name, p.description, p.is_active, p.status, p.tax_percent, p.created_at, p.updated_at, p.deleted_at')
            ->select('(SELECT s1.sku_code FROM skus s1 WHERE s1.product_id = p.id AND s1.deleted_at IS NULL ORDER BY s1.id ASC LIMIT 1) as sku', false)
            ->select('(SELECT s1.sale_price FROM skus s1 WHERE s1.product_id = p.id AND s1.deleted_at IS NULL ORDER BY s1.id ASC LIMIT 1) as sale_price', false)
            ->select('COUNT(s.id) as sku_count')
            ->join('brands b', 'b.id = p.brand_id', 'left')
            ->join('categories c', 'c.id = p.category_id', 'left')
            ->join('skus s', 's.product_id = p.id AND s.deleted_at IS NULL', 'left')
            ->where('p.deleted_at IS NULL')
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function assertProductNameIsRealItem(string $name, ?int $brandId, ?int $categoryId): void
    {
        $normalized = strtolower(trim($name));
        if ($normalized === '') {
            throw new RuntimeException('Product name is required.');
        }

        if (in_array($normalized, $this->reservedGenericNames, true)) {
            throw new RuntimeException('Product name must be a real item, not a brand or generic category.');
        }

        if ($brandId !== null) {
            $brand = $this->db->table('brands')->select('name')->where('id', $brandId)->get()->getFirstRow('array');
            if ($brand === null) {
                throw new RuntimeException('Selected brand is invalid.');
            }
            if ($normalized === strtolower(trim((string) ($brand['name'] ?? '')))) {
                throw new RuntimeException('Product name cannot be only the brand name.');
            }
        }

        if ($categoryId !== null) {
            $category = $this->db->table('categories')->select('name')->where('id', $categoryId)->get()->getFirstRow('array');
            if ($category === null) {
                throw new RuntimeException('Selected category is invalid.');
            }
            if ($normalized === strtolower(trim((string) ($category['name'] ?? '')))) {
                throw new RuntimeException('Product name cannot be only the category name.');
            }
        }
    }

    private function initializeInventoryBalancesForSku(int $skuId): void
    {
        $now = date('Y-m-d H:i:s');

        // Create zero-balance rows for every active branch so inventory is always initialized.
        $this->db->query(
            'INSERT IGNORE INTO `inventory_balances` (`branch_id`, `sku_id`, `qty_on_hand`, `qty_reserved`, `created_at`, `updated_at`) '
            . 'SELECT `b`.`id`, ?, 0, 0, ?, ? FROM `branches` `b` WHERE `b`.`deleted_at` IS NULL',
            [$skuId, $now, $now]
        );
    }
}
