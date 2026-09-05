<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use InvalidArgumentException;
use Throwable;
use JsonException;

final class WebhookEndpointController
{
    public function __construct(
        private readonly RegisterWebhookEndpoint $registrar,
        private readonly ReactivateWebhookEndpoint $reactivator,
        private readonly DeactivateWebhookEndpoint $deactivator,
    ) {
    }

    public function handle(
        string $payload,
    ): RestResponse {
        try {
            $data = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data['events'] ?? null)) {
                return new RestResponse(
                    statusCode: 400,
                    body: [
                        'message' => 'Webhook events must be an array.',
                    ],
                );
            }

            foreach ($data['events'] as $event) {
                if (!is_string($event)) {
                    return new RestResponse(
                        statusCode: 400,
                        body: [
                            'message' => 'Webhook event names must be strings.',
                        ],
                    );
                }
            }

            $endpoint = $this->registrar->execute(
                url: (string) ($data['url'] ?? ''),
                events: $data['events'],
            );

            return new RestResponse(
                statusCode: 200,
                body: [
                    'url' => $endpoint->url,
                    'active' => $endpoint->active,
                    'events' => $endpoint->events,
                ],
            );
        } catch (JsonException) {
            return new RestResponse(
                statusCode: 400,
                body: [
                    'message' => 'Invalid JSON payload.',
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return new RestResponse(
                statusCode: 400,
                body: [
                    'message' => $exception->getMessage(),
                ],
            );
        } catch (Throwable) {
            return new RestResponse(
                statusCode: 500,
                body: [
                    'message' => 'Internal server error.',
                ],
            );
        }
    }

    public function activate(
        string $payload,
    ): RestResponse {
        return $this->changeActivationState(
            payload: $payload,
            action: $this->reactivator,
        );
    }

    public function deactivate(
        string $payload,
    ): RestResponse {
        return $this->changeActivationState(
            payload: $payload,
            action: $this->deactivator,
        );
    }

    private function changeActivationState(
        string $payload,
        ReactivateWebhookEndpoint|DeactivateWebhookEndpoint $action,
    ): RestResponse {
        try {
            $data = json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $url = $data['url'] ?? null;

            if (!is_string($url) || $url === '') {
                return new RestResponse(
                    statusCode: 400,
                    body: [
                        'message' => 'Webhook URL is required.',
                    ],
                );
            }

            $endpoint = $action->execute($url);

            return new RestResponse(
                statusCode: 200,
                body: [
                    'url' => $endpoint->url,
                    'active' => $endpoint->active,
                    'events' => $endpoint->events,
                ],
            );
        } catch (JsonException) {
            return new RestResponse(
                statusCode: 400,
                body: [
                    'message' => 'Invalid JSON payload.',
                ],
            );
        } catch (InvalidArgumentException $exception) {
            return new RestResponse(
                statusCode: 400,
                body: [
                    'message' => $exception->getMessage(),
                ],
            );
        } catch (Throwable) {
            return new RestResponse(
                statusCode: 500,
                body: [
                    'message' => 'Internal server error.',
                ],
            );
        }
    }
}