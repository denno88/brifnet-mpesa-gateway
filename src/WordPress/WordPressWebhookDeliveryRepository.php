<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Domain\WebhookDelivery;
use BrifnetMpesa\Domain\WebhookDeliveryRepository;
use RuntimeException;

final class WordPressWebhookDeliveryRepository
    implements WebhookDeliveryRepository
{
    public function __construct(
        private readonly WordPressDatabase $database,
        private readonly string $tableName,
    ) {
    }

    public function save(WebhookDelivery $delivery): void
    {
        $now = new \DateTimeImmutable();

        $result = $this->database->insert(
            $this->tableName,
            [
                'event_id' => $delivery->eventId,
                'url' => $delivery->url,
                'payload' => $delivery->payload,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ],
        );

        if ($result === false) {
            throw new RuntimeException(
                'Failed to save webhook delivery.'
            );
        }
    }

    public function findByEventIdAndUrl(
        string $eventId,
        string $url
    ): ?WebhookDelivery {
        $query = $this->database->prepare(
            "SELECT
                event_id,
                url,
                payload,
                status,
                attempts
            FROM {$this->tableName}
            WHERE event_id = %s
            AND url = %s
            LIMIT 1",
            $eventId,
            $url
        );

        $row = $this->database->get_row($query);

        if (!is_object($row)) {
            return null;
        }

        return new WebhookDelivery(
            eventId: (string) $row->event_id,
            url: (string) $row->url,
            payload: (string) $row->payload,
            status: (string) $row->status,
            attempts: (int) $row->attempts,
        );
    }

    public function findPending(): array
    {
        $query = $this->database->prepare(
            "SELECT
                event_id,
                url,
                payload,
                status,
                attempts
            FROM {$this->tableName}
            WHERE status = %s",
            'pending',
        );

        $rows = $this->database->get_results($query);

        return array_map(
            fn (object $row): WebhookDelivery => new WebhookDelivery(
                eventId: (string) $row->event_id,
                url: (string) $row->url,
                payload: (string) $row->payload,
                status: (string) $row->status,
                attempts: (int) $row->attempts,
            ),
            $rows,
        );
    }

    public function update(WebhookDelivery $delivery): void
    {
        $result = $this->database->update(
            $this->tableName,
            [
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
            ],
            [
                'event_id' => $delivery->eventId,
                'url' => $delivery->url,
            ],
            [
                '%s',
                '%d',
            ],
            [
                '%s',
                '%s',
            ],
        );

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to update webhook delivery.'
            );
        }
    }
}