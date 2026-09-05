<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class StkPushRoute
{
    public function __construct(
        private readonly RestRegistrar $registrar,
        private readonly StkPushController $controller,
    ) {
    }

    public function register(): void
    {
        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/mpesa/stk',
            args: [
                'methods' => 'POST',
                'callback' => function ($request) {
                    $response = $this->controller->handle(
                        $request->get_json_params()
                    );

                    return new \WP_REST_Response(
                        $response->body,
                        $response->statusCode,
                    );
                },
                'permission_callback' => '__return_true',
            ],
        );
    }
}