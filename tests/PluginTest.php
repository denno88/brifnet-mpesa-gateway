<?php

declare(strict_types=1);

namespace BrifnetMpesa\Tests;

use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\ProcessStkCallback;
use BrifnetMpesa\Core\Plugin;
use BrifnetMpesa\WordPress\RestRegistrar;
use BrifnetMpesa\WordPress\StkCallbackController;
use BrifnetMpesa\WordPress\StkCallbackRoute;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use BrifnetMpesa\WordPress\WebhookEndpointController;
use BrifnetMpesa\WordPress\WebhookEndpointRoute;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testPluginRegistersInitializationHook(): void
    {
        $hooks = new FakeHookRegistrar();

        $plugin = new Plugin($hooks);

        $plugin->boot();

        $this->assertArrayHasKey('init', $hooks->actions);

        $this->assertSame(
            [$plugin, 'initialize'],
            $hooks->actions['init']
        );
    }

    public function testPluginRegistersRestApiHook(): void
    {
        $hooks = new FakeHookRegistrar();

        $restRegistrar = new class implements RestRegistrar {
            public function registerRoute(
                string $namespace,
                string $route,
                array $args,
            ): void {
            }
        };

        $repository = new FakePaymentRepository();

        $processor = new ProcessStkCallback(
            parser: new StkCallbackParser(),
            paymentRepository: $repository,
            completePayment: new CompletePayment(
                paymentRepository: $repository,
                eventRepository: new FakePaymentEventRepository(),
                transactionManager: new FakeTransactionManager(),
                eventIdGenerator: new PaymentEventIdGenerator(),
                webhookQueue: $this->webhookQueue(),
                webhookDeliveryWorker: $this->webhookWorker(),
            ),
        );

        $controller = new StkCallbackController($processor);

        $callbackRoute = new StkCallbackRoute(
            registrar: $restRegistrar,
            controller: $controller,
        );

        $plugin = new Plugin(
            hooks: $hooks,
            stkCallbackRoute: $callbackRoute,
        );

        $plugin->boot();

        $this->assertArrayHasKey(
            'rest_api_init',
            $hooks->actions
        );

        $this->assertSame(
            [$callbackRoute, 'register'],
            $hooks->actions['rest_api_init']
        );
    }

    public function testItRegistersAllDatabaseSchemasOnActivation(): void
    {
        $hooks = new FakeHookRegistrar();
        $activationRegistrar = new FakeActivationRegistrar();

        $transactionSchema = new FakeSchema();
        $paymentEventSchema = new FakeSchema();

        $plugin = new Plugin(
            hooks: $hooks,
            schemas: [
                $transactionSchema,
                $paymentEventSchema,
            ],
            activationRegistrar: $activationRegistrar,
            pluginFile: '/plugin/brifnet-mpesa-gateway.php',
        );

        $plugin->boot();

        $this->assertSame(
            '/plugin/brifnet-mpesa-gateway.php',
            $activationRegistrar->pluginFile
        );

        $this->assertCount(
            2,
            $activationRegistrar->callbacks
        );

        $activationRegistrar->callback();

        $this->assertSame(
            1,
            $transactionSchema->installCount
        );

        $this->assertSame(
            1,
            $paymentEventSchema->installCount
        );
    }

    public function testPluginRegistersWebhookRoute(): void
    {
        $hooks = new FakeHookRegistrar();

        $restRegistrar = new class implements RestRegistrar {
            public function registerRoute(
                string $namespace,
                string $route,
                array $args,
            ): void {
            }
        };

        $repository = new FakeWebhookEndpointRepository();

        $controller = new WebhookEndpointController(
            registrar: new RegisterWebhookEndpoint($repository),
            reactivator: new ReactivateWebhookEndpoint($repository),
            deactivator: new DeactivateWebhookEndpoint($repository),
        );

        $webhookRoute = new \BrifnetMpesa\WordPress\WebhookEndpointRoute(
            registrar: $restRegistrar,
            controller: $controller,
        );

        $plugin = new Plugin(
            hooks: $hooks,
            webhookEndpointRoute: $webhookRoute,
        );

        $plugin->boot();

        $this->assertArrayHasKey(
            'rest_api_init',
            $hooks->actions
        );

        $this->assertSame(
            [$webhookRoute, 'register'],
            $hooks->actions['rest_api_init']
        );
    }

    private function webhookQueue(): QueuePaymentCompletedWebhooks
    {
        return new QueuePaymentCompletedWebhooks(
            endpointRepository: new FakeWebhookEndpointRepository(),
            deliveryRepository: new FakeWebhookDeliveryRepository(),
            payloadBuilder: new PaymentCompletedPayloadBuilder(),
        );
    }

    private function webhookWorker(): \BrifnetMpesa\Application\WebhookDeliveryWorker
    {
        return new \BrifnetMpesa\Application\WebhookDeliveryWorker(
            repository: new FakeWebhookDeliveryRepository(),
            httpClient: new FakeWebhookHttpClient(),
            signature: new \BrifnetMpesa\Domain\WebhookSignature(),
            webhookSecret: 'test-secret',
        );
    }
}