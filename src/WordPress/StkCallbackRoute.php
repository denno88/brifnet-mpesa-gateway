<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class StkCallbackRoute
{
    public function __construct(
        private readonly RestRegistrar $registrar,
        private readonly StkCallbackController $controller,
    ) {
    }

    public function register(): void
    {
        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/mpesa/callback',
            args: [
                'methods' => 'POST',
                'callback' => function ($request) {
                    $response = $this->controller->handle(
                        $request->get_body()
                    );

                    return new \WP_REST_Response(
                        $response->body,
                        $response->statusCode
                    );
                },
                'permission_callback' => '__return_true',
            ],
        );
    }
}