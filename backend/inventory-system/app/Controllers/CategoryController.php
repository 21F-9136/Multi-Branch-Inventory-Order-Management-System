<?php

namespace App\Controllers;

use App\Services\CategoryService;
use CodeIgniter\HTTP\ResponseInterface;

class CategoryController extends BaseController
{
    public function __construct(protected ?CategoryService $categoryService = null)
    {
        $this->categoryService ??= new CategoryService();
    }

    public function index(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'items' => $this->categoryService->listCategories(),
            ],
        ]);
    }
}
