<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Domain\WebhookEndpoint;
use BrifnetMpesa\Domain\WebhookEndpointRepository;

final class WordPressWebhookEndpointRepository
    implements WebhookEndpointRepository
{
    public function __construct(
        private readonly WordPressDatabase $database,
        private readonly string $tableName,
    ) {
    }

    public function save(WebhookEndpoint $endpoint): void
    {
        $result = $this->database->insert(
            $this->tableName,
            [
                'url' => $endpoint->url,
                'active' => $endpoint->active ? 1 : 0,
                'events' => json_encode(
                    $endpoint->events,
                    JSON_THROW_ON_ERROR
                ),
            ],
            [
                '%s',
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to save webhook endpoint.'
            );
        }
    }

    public function findByUrl(string $url): ?WebhookEndpoint
    {
        $query = $this->database->prepare(
            "SELECT
                url,
                active,
                events
            FROM {$this->tableName}
            WHERE url = %s
            LIMIT 1",
            $url
        );

        $row = $this->database->get_row($query);

        if (!is_object($row)) {
            return null;
        }

        return new WebhookEndpoint(
            url: (string) $row->url,
            active: (bool) $row->active,
            events: json_decode(
                (string) $row->events,
                true,
                512,
                JSON_THROW_ON_ERROR
            ),
        );
    }

    public function update(WebhookEndpoint $endpoint): void
    {
        $result = $this->database->update(
            $this->tableName,
            [
                'active' => $endpoint->active ? 1 : 0,
                'events' => json_encode(
                    $endpoint->events,
                    JSON_THROW_ON_ERROR
                ),
            ],
            [
                'url' => $endpoint->url,
            ],
            [
                '%d',
                '%s',
            ],
            [
                '%s',
            ]
        );

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to update webhook endpoint.'
            );
        }
    }

    public function findActiveForEvent(string $event): array
    {
        $query = $this->database->prepare(
            "SELECT
                url,
                active,
                events
            FROM {$this->tableName}
            WHERE active = 1
            AND JSON_CONTAINS(events, %s)
            ",
            json_encode($event, JSON_THROW_ON_ERROR)
        );

        $rows = $this->database->get_results($query);

        return array_map(
            fn (array $row): WebhookEndpoint => new WebhookEndpoint(
                url: (string) $row['url'],
                active: (bool) $row['active'],
                events: json_decode(
                    (string) $row['events'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
            ),
            $rows
        );
    }
}