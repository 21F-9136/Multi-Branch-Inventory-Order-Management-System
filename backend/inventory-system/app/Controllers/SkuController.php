<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\SkuService;
use RuntimeException;

class SkuController extends BaseController
{
    public function __construct(protected SkuService $skus = new SkuService())
    {
    }

    public function index()
    {
        $productId = $this->request->getGet('product_id');
        $search = $this->request->getGet('q');

        return $this->response->setJSON([
            'data' => $this->skus->listSkus(
                $productId !== null ? (int) $productId : null,
                $search !== null ? (string) $search : null
            ),
        ]);
    }

    public function update($id = null)
    {
        if ($id === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'SKU id required']);
        }

        $payload = $this->request->getJSON(true) ?? [];

        try {
            $this->skus->updateSku((int) $id, is_array($payload) ? $payload : []);
            return $this->response->setJSON(['ok' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => $e->getMessage()]);
        }
    }

    public function delete($id = null)
    {
        if ($id === null) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'SKU id required']);
        }

        try {
            $this->skus->deleteSku((int) $id);
            return $this->response->setJSON(['ok' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(404)->setJSON(['error' => $e->getMessage()]);
        }
    }
}
