<?php

namespace App\Models;

use CodeIgniter\Model;

class SkuModel extends Model
{
    protected $table            = 'skus';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'product_id',
        'sku_code',
        'barcode',
        'cost_price',
        'sale_price',
        'name',
        'unit_price',
        'reorder_level',
    ];

    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
}
