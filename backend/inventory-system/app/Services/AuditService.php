<?php

namespace App\Services;

use App\Models\AuditLogModel;

class AuditService
{
    public function __construct(protected AuditLogModel $audit = new AuditLogModel())
    {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function log(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $this->audit->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'metadata'    => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
        ]);
    }
}
