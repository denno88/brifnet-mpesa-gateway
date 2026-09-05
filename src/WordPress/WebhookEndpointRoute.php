<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class WebhookEndpointRoute
{
    public function __construct(
        private readonly RestRegistrar $registrar,
        private readonly WebhookEndpointController $controller,
    ) {
    }

    public function register(): void
    {
        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/webhooks',
            args: [
                'methods' => 'POST',
                'callback' => function ($request): RestResponse {
                    return $this->controller->handle(
                        $request->get_body()
                    );
                },
                'permission_callback' => '__return_true',
            ],
        );

        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/webhooks/activate',
            args: [
                'methods' => 'POST',
                'callback' => function ($request): RestResponse {
                    return $this->controller->activate(
                        $request->get_body()
                    );
                },
                'permission_callback' => '__return_true',
            ],
        );

        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/webhooks/deactivate',
            args: [
                'methods' => 'POST',
                'callback' => function ($request): RestResponse {
                    return $this->controller->deactivate(
                        $request->get_body()
                    );
                },
                'permission_callback' => '__return_true',
            ],
        );
    }
}