<?php

namespace App\Services;

use App\Models\CategoryModel;

class CategoryService
{
    public function __construct(protected CategoryModel $categories = new CategoryModel())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listCategories(): array
    {
        return $this->categories->orderBy('name', 'ASC')->findAll();
    }
}
