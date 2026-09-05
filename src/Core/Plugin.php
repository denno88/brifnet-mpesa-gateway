<?php

declare(strict_types=1);

namespace BrifnetMpesa\Core;

use BrifnetMpesa\Database\Schema;
use BrifnetMpesa\WordPress\ActivationRegistrar;
use BrifnetMpesa\WordPress\C2BPaymentRoute;
use BrifnetMpesa\WordPress\HookRegistrar;
use BrifnetMpesa\WordPress\StkCallbackRoute;
use BrifnetMpesa\WordPress\WebhookEndpointRoute;
use BrifnetMpesa\WordPress\StkPushRoute;

final class Plugin
{
    public function __construct(
        private readonly HookRegistrar $hooks,
        private readonly ?StkCallbackRoute $stkCallbackRoute = null,
        private readonly ?StkPushRoute $stkPushRoute = null,
        private readonly ?WebhookEndpointRoute $webhookEndpointRoute = null,
        private readonly ?C2BPaymentRoute $c2bPaymentRoute = null,
        private readonly array $schemas = [],
        private readonly ?ActivationRegistrar $activationRegistrar = null,
        private readonly ?string $pluginFile = null,
    ) {
    }

    public function boot(): void
    {
        $this->hooks->addAction(
            'init',
            [$this, 'initialize']
        );

        if ($this->stkCallbackRoute !== null) {
            $this->hooks->addAction(
                'rest_api_init',
                [$this->stkCallbackRoute, 'register']
            );
        }

        if ($this->stkPushRoute !== null) {
            $this->hooks->addAction(
                'rest_api_init',
                [$this->stkPushRoute, 'register']
            );
        }

        if ($this->webhookEndpointRoute !== null) {
            $this->hooks->addAction(
                'rest_api_init',
                [$this->webhookEndpointRoute, 'register']
            );
        }

        if (
            $this->schemas !== [] &&
            $this->activationRegistrar !== null &&
            $this->pluginFile !== null
        ) {
            foreach ($this->schemas as $schema) {
                $this->activationRegistrar->register(
                    $this->pluginFile,
                    [$schema, 'install']
                );
            }
        }

        if ($this->c2bPaymentRoute !== null) {
            $this->hooks->addAction(
                'rest_api_init',
                [$this->c2bPaymentRoute, 'register']
            );
        }
    }

    public function initialize(): void
    {
        // Plugin initialization will happen here.
    }
}