<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Domain\Amount;
use BrifnetMpesa\Domain\PaymentChannel;
use BrifnetMpesa\Domain\Payment;
use BrifnetMpesa\Domain\PaymentCompleted;
use BrifnetMpesa\Domain\PhoneNumber;
use BrifnetMpesa\Domain\PaymentReference;
use BrifnetMpesa\Domain\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class QueuePaymentCompletedWebhooksTest extends TestCase
{
    public function testItCreatesOnePendingDeliveryForEachActiveEndpoint(): void
    {
        $endpointRepository = new FakeWebhookEndpointRepository();
        $deliveryRepository = new FakeWebhookDeliveryRepository();

        $endpointRepository->save(
            new WebhookEndpoint(
                url: 'https://example.com/webhook-a',
                events: ['payment.completed']
            )
        );

        $endpointRepository->save(
            new WebhookEndpoint(
                url: 'https://example.com/webhook-b',
                events: ['payment.completed']
            )
        );

        $endpointRepository->save(
            new WebhookEndpoint(
                url: 'https://example.com/other',
                events: ['payment.failed']
            )
        );

        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: new Payment(
                reference: new PaymentReference('PAY-123'),
                phone: new PhoneNumber('0712345678'),
                amount: 500,
                channel: PaymentChannel::STK,
            ),
            occurredAt: new \DateTimeImmutable(
                '2026-09-04T12:30:00+03:00'
            ),
        );

        $service = new QueuePaymentCompletedWebhooks(
            $endpointRepository,
            $deliveryRepository,
            new PaymentCompletedPayloadBuilder(),
        );

        $service->queue($event);

        $this->assertCount(
            2,
            $deliveryRepository->deliveries
        );

        $deliveryA = $deliveryRepository->findByEventIdAndUrl(
            'evt-123',
            'https://example.com/webhook-a'
        );

        $deliveryB = $deliveryRepository->findByEventIdAndUrl(
            'evt-123',
            'https://example.com/webhook-b'
        );

        $this->assertNotNull($deliveryA);
        $this->assertNotNull($deliveryB);

        $this->assertSame(
            'pending',
            $deliveryA->status
        );

        $this->assertSame(
            0,
            $deliveryA->attempts
        );

        $this->assertSame(
            'pending',
            $deliveryB->status
        );

        $this->assertSame(
            0,
            $deliveryB->attempts
        );
    }

    public function testItStoresTheExactJsonPayloadInEachDelivery(): void
    {
        $endpointRepository = new FakeWebhookEndpointRepository();
        $deliveryRepository = new FakeWebhookDeliveryRepository();

        $endpointRepository->save(
            new WebhookEndpoint(
                url: 'https://example.com/webhook',
                events: ['payment.completed']
            )
        );

        $event = new PaymentCompleted(
            eventId: 'evt-123',
            payment: new Payment(
                reference: new PaymentReference('PAY-123'),
                phone: new PhoneNumber('0712345678'),
                amount: 500,
                channel: PaymentChannel::STK,
                checkoutRequestId: 'ws_CO_67890',
                transactionId: 'ABC123XYZ',
            ),
            occurredAt: new \DateTimeImmutable(
                '2026-09-04T12:30:00+03:00'
            ),
        );

        $service = new QueuePaymentCompletedWebhooks(
            $endpointRepository,
            $deliveryRepository,
            new PaymentCompletedPayloadBuilder(),
        );

        $service->queue($event);

        $delivery = $deliveryRepository->findByEventIdAndUrl(
            'evt-123',
            'https://example.com/webhook'
        );

        $this->assertNotNull($delivery);

        $this->assertSame(
            '{"event_id":"evt-123","event":"payment.completed","occurred_at":"2026-09-04T12:30:00+03:00","data":{"reference":"PAY-123","phone":"0712345678","amount":500,"channel":"STK","provider_reference":"ws_CO_67890","provider_transaction_id":"ABC123XYZ"}}',
            $delivery->payload
        );
    }
}