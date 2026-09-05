<?php

declare(strict_types=1);

namespace BrifnetMpesa\WordPress;

final class C2BPaymentRoute
{
    public function __construct(
        private readonly RestRegistrar $registrar,
        private readonly C2BPaymentController $controller,
    ) {
    }

    public function register(): void
    {
        $this->registrar->registerRoute(
            namespace: 'brifnet/v1',
            route: '/mpesa/c2b',
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
    }
}