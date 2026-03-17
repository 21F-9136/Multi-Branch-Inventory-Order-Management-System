<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryBalanceModel extends Model
{
    protected $table            = 'inventory_balances';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'branch_id',
        'sku_id',
        'qty_on_hand',
        'qty_reserved',
    ];

    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
}
