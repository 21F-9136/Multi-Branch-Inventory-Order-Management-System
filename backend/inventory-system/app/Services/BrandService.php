<?php

namespace App\Services;

use App\Models\BrandModel;

class BrandService
{
    public function __construct(protected BrandModel $brands = new BrandModel())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBrands(): array
    {
        return $this->brands->orderBy('name', 'ASC')->findAll();
    }
}
