<?php

namespace App\Services;

use App\Models\OutboxEventModel;
use RuntimeException;

class EventService
{
    public function __construct(
        protected OutboxEventModel $outbox = new OutboxEventModel(),
    ) {
    }

    /**
     * @param array|string $payload
     * @return int|string
     */
    public function publish(
        string $aggregateType,
        ?int $aggregateId,
        string $eventType,
        $payload,
        ?string $availableAt = null,
    ) {
        $jsonPayload = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('Unable to encode payload as JSON.');
        }

        $this->outbox->insert([
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => $jsonPayload,
            'status'         => 'pending',
            'available_at'   => $availableAt,
            'processed_at'   => null,
            'attempts'       => 0,
            'last_error'     => null,
        ]);

        return $this->outbox->getInsertID();
    }

    public function markProcessed(int $outboxEventId): void
    {
        $this->outbox->update($outboxEventId, [
            'status'       => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $outboxEventId, string $errorMessage): void
    {
        $this->outbox->update($outboxEventId, [
            'status'      => 'failed',
            'attempts'    => 1,
            'last_error'  => $errorMessage,
        ]);
    }
}
