<?php

namespace App\Models;

use CodeIgniter\Model;

class OutboxEventModel extends Model
{
    protected $table            = 'outbox_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType    = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'available_at',
        'processed_at',
        'attempts',
        'last_error',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
