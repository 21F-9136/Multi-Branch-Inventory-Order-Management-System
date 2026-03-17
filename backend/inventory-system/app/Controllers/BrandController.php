<?php

namespace App\Controllers;

use App\Services\BrandService;
use CodeIgniter\HTTP\ResponseInterface;

class BrandController extends BaseController
{
    public function __construct(protected ?BrandService $brandService = null)
    {
        $this->brandService ??= new BrandService();
    }

    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'items' => $this->brandService->listBrands(),
            ],
        ]);
    }
}
