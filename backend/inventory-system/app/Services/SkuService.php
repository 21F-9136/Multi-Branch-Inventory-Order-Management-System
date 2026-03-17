<?php

namespace App\Services;

use App\Models\SkuModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class SkuService
{
    protected BaseConnection $db;

    public function __construct(protected SkuModel $skus = new SkuModel())
    {
        $this->db = Database::connect();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSkus(?int $productId = null, ?string $search = null): array
    {
        $q = $this->db
            ->table('skus s')
            ->select('s.id, s.product_id, p.name as product_name, s.sku_code, s.barcode, s.name, s.cost_price, s.sale_price, s.unit_price, s.created_at, s.updated_at, s.deleted_at')
            ->join('products p', 'p.id = s.product_id', 'left')
            ->where('s.deleted_at IS NULL');

        if ($productId !== null) {
            $q->where('s.product_id', $productId);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $q->groupStart()
                ->like('s.sku_code', $term)
                ->orLike('s.barcode', $term)
                ->orLike('s.name', $term)
                ->groupEnd();
        }

        return $q->orderBy('s.id', 'DESC')->get()->getResultArray();
    }

    /**
        * @param array{sku_code?:string|null,barcode?:string|null,name?:string|null,unit_price?:string|float|int|null,cost_price?:string|float|int|null,sale_price?:string|float|int|null,product_id?:int|null} $data
     */
    public function updateSku(int $skuId, array $data): void
    {
        $sku = $this->skus->find($skuId);
        if ($sku === null || !empty($sku['deleted_at'])) {
            throw new RuntimeException('SKU not found.');
        }

        $update = [];
        if (array_key_exists('product_id', $data) && $data['product_id'] !== null) {
            $update['product_id'] = (int) $data['product_id'];
        }
        if (array_key_exists('sku_code', $data) && $data['sku_code'] !== null) {
            $update['sku_code'] = $data['sku_code'];
        }
        if (array_key_exists('barcode', $data)) {
            $update['barcode'] = $data['barcode'];
        }
        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('unit_price', $data) && $data['unit_price'] !== null) {
            $update['unit_price'] = (float) $data['unit_price'];
        }
        if (array_key_exists('cost_price', $data)) {
            $update['cost_price'] = $data['cost_price'] === null ? null : (float) $data['cost_price'];
        }
        if (array_key_exists('sale_price', $data)) {
            $salePrice = $data['sale_price'] === null ? null : (float) $data['sale_price'];
            $update['sale_price'] = $salePrice;
            if ($salePrice !== null && !array_key_exists('unit_price', $data)) {
                $update['unit_price'] = $salePrice;
            }
        }

        if ($update === []) return;

        $this->skus->update($skuId, $update);
    }

    public function deleteSku(int $skuId): void
    {
        $sku = $this->skus->find($skuId);
        if ($sku === null || !empty($sku['deleted_at'])) {
            throw new RuntimeException('SKU not found.');
        }

        $this->skus->delete($skuId);
    }
}
