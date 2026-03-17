<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderLineModel extends Model
{
    protected $table            = 'order_lines';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'order_id',
        'sku_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
