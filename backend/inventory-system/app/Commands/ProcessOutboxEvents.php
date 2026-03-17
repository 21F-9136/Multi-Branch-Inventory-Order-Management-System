<?php

namespace App\Commands;

use App\Models\OutboxEventModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

class ProcessOutboxEvents extends BaseCommand
{
    protected $group       = 'Outbox';
    protected $name        = 'outbox:process';
    protected $description = 'Process pending outbox events (batch size: 50).';

    protected $usage = 'outbox:process';

    public function run(array $params)
    {
        $model = new OutboxEventModel();

        $events = $model
            ->where('status', 'pending')
            ->orderBy('id', 'ASC')
            ->findAll(50);

        if (empty($events)) {
            CLI::write('No pending outbox events found.', 'yellow');
            return;
        }

        CLI::write('Processing ' . count($events) . ' outbox event(s)...', 'green');

        foreach ($events as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            $eventType = (string) ($event['event_type'] ?? '');
            $payloadRaw = $event['payload'] ?? '';

            try {
                $payload = json_decode((string) $payloadRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON payload: ' . json_last_error_msg());
                }

                // "Handle" the event (placeholder): log event type and payload.
                $msg = sprintf('OutboxEvent #%d %s payload=%s', $eventId, $eventType, json_encode($payload, JSON_UNESCAPED_UNICODE));
                CLI::write($msg, 'white');
                log_message('info', $msg);

                $model->update($eventId, [
                    'status'       => 'processed',
                    'processed_at' => date('Y-m-d H:i:s'),
                    'last_error'   => null,
                ]);
            } catch (Throwable $e) {
                $attempts = (int) ($event['attempts'] ?? 0) + 1;

                $errorMsg = sprintf('Failed OutboxEvent #%d %s: %s', $eventId, $eventType, $e->getMessage());
                CLI::write($errorMsg, 'red');
                log_message('error', $errorMsg);

                $model->update($eventId, [
                    'status'     => 'failed',
                    'attempts'   => $attempts,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        CLI::write('Done.', 'green');
    }
}
