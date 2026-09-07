<?php

declare(strict_types=1);

namespace BrifnetMpesa\Application;

use BrifnetMpesa\Domain\WebhookDeliveryRepository;
use BrifnetMpesa\Domain\WebhookHttpClient;
use BrifnetMpesa\Domain\WebhookSignature;

final class WebhookDeliveryWorker
{
    public function __construct(
        private readonly WebhookDeliveryRepository $repository,
        private readonly WebhookHttpClient $httpClient,
        private readonly WebhookSignature $signature,
        private readonly string $webhookSecret,
        private readonly int $maxAttempts = 3,
    ) {
    }

    public function run(): void
{
    
    $deliveries = $this->repository->findPending();

    foreach ($deliveries as $delivery) {

        if (!$delivery->isRetryable($this->maxAttempts)) {

            $this->repository->update(
                $delivery->markPermanentlyFailed()
            );

            continue;
        }

        try {

            $this->httpClient->send(
                $delivery->url,
                $delivery->payload,
                [
                    'X-Webhook-Signature' => $this->signature->generate(
                        $delivery->payload,
                        $this->webhookSecret,
                    ),
                ],
            );


            $this->repository->update(
                $delivery->markDelivered()
            );


        } catch (\Throwable $exception) {


            $this->repository->update(
                $delivery->markFailed()
            );

            continue;
        }
    }

}
}