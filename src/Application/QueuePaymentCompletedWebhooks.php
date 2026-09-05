<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\WebhookDelivery;
use BrifnetMpesa\Domain\WebhookDeliveryRepository;
use BrifnetMpesa\Domain\WebhookEndpointRepository;

final class QueuePaymentCompletedWebhooks
{
    public function __construct(
        private readonly WebhookEndpointRepository $endpointRepository,
        private readonly WebhookDeliveryRepository $deliveryRepository,
        private readonly PaymentCompletedPayloadBuilder $payloadBuilder,
    ) {
    }

    public function queue(PaymentCompleted $event): void
    {
        $payload = json_encode(
            $this->payloadBuilder->build($event),
            JSON_THROW_ON_ERROR
        );

        $endpoints = $this->endpointRepository->findActiveForEvent(
            $event->name()
        );

        foreach ($endpoints as $endpoint) {
            $this->deliveryRepository->save(
                new WebhookDelivery(
                    eventId: $event->eventId,
                    url: $endpoint->url,
                    payload: $payload,
                )
            );
        }
    }
}