<?php

/**
 * Plugin Name: BrifNet M-Pesa Gateway
 * Plugin URI: https://brifnet.co.ke
 * Description: M-Pesa payment gateway integration for WordPress.
 * Version: 1.0.0
 * Author: Denis Irungu | CEO BrifNet Technologies LTD
 * Author URI: https://brifnet.co.ke
 * License: MIT
 * Requires PHP: 8.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use BrifnetMpesa\Api\C2BPaymentParser;
use BrifnetMpesa\Api\C2BPaymentValidator;
use BrifnetMpesa\Api\StkCallbackParser;
use BrifnetMpesa\Application\CompletePayment;
use BrifnetMpesa\Application\PaymentEventIdGenerator;
use BrifnetMpesa\Application\ProcessC2BPayment;
use BrifnetMpesa\Application\ProcessStkCallback;
use BrifnetMpesa\Core\Plugin;
use BrifnetMpesa\Database\WordPressPaymentEventSchema;
use BrifnetMpesa\Database\WordPressSchema;
use BrifnetMpesa\WordPress\C2BPaymentController;
use BrifnetMpesa\WordPress\C2BPaymentRoute;
use BrifnetMpesa\WordPress\StkCallbackController;
use BrifnetMpesa\WordPress\StkCallbackRoute;
use BrifnetMpesa\WordPress\WordPressActivationRegistrar;
use BrifnetMpesa\WordPress\WordPressHookRegistrar;
use BrifnetMpesa\WordPress\WordPressPaymentEventRepository;
use BrifnetMpesa\WordPress\WordPressPaymentRepository;
use BrifnetMpesa\WordPress\WordPressRestRegistrar;
use BrifnetMpesa\WordPress\WordPressSchemaExecutor;
use BrifnetMpesa\WordPress\WordPressTransactionManager;
use BrifnetMpesa\Application\PaymentCompletedPayloadBuilder;
use BrifnetMpesa\Application\QueuePaymentCompletedWebhooks;
use BrifnetMpesa\Database\WordPressWebhookDeliverySchema;
use BrifnetMpesa\Database\WordPressWebhookEndpointSchema;
use BrifnetMpesa\WordPress\WordPressWebhookDeliveryRepository;
use BrifnetMpesa\WordPress\WordPressWebhookEndpointRepository;
use BrifnetMpesa\Api\SystemDateTimeProvider;
use BrifnetMpesa\WordPress\NativeWordPressDatabase;
use BrifnetMpesa\Application\InitiatePayment;
use BrifnetMpesa\WordPress\DarajaClientFactory;
use BrifnetMpesa\WordPress\DarajaConfigLoader;
use BrifnetMpesa\WordPress\NativeWordPressHttpTransport;
use BrifnetMpesa\WordPress\StkPushController;
use BrifnetMpesa\WordPress\StkPushRoute;
use BrifnetMpesa\WordPress\WordPressHttpClient;
use BrifnetMpesa\WordPress\WordPressTransientStore;
use BrifnetMpesa\WordPress\WebhookEndpointRoute;
use BrifnetMpesa\Application\RegisterWebhookEndpoint;
use BrifnetMpesa\WordPress\WebhookEndpointController;
use BrifnetMpesa\Application\ReactivateWebhookEndpoint;
use BrifnetMpesa\Application\DeactivateWebhookEndpoint;
use BrifnetMpesa\Application\WebhookDeliveryWorker;
use BrifnetMpesa\Domain\WebhookSignature;
use BrifnetMpesa\WordPress\WordPressWebhookHttpClient;
use BrifnetMpesa\Application\DefaultPaymentReferenceGenerator;

$hooks = new WordPressHookRegistrar();

$database = new NativeWordPressDatabase(
    $GLOBALS['wpdb']
);

$paymentRepository = new WordPressPaymentRepository(
    database: $database,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_transactions',
    dateTimeProvider: new SystemDateTimeProvider(),
);

$httpClient = new WordPressHttpClient(
    new NativeWordPressHttpTransport()
);

$webhookHttpClient = new WordPressWebhookHttpClient(
    http: $httpClient,
);

$transientStore = new WordPressTransientStore();

$darajaConfigLoader = new DarajaConfigLoader();

$darajaConfig = $darajaConfigLoader->load();

$mpesaClient = new DarajaClientFactory(
    httpClient: $httpClient,
    transientStore: $transientStore,
)->create($darajaConfig);

$initiatePayment = new InitiatePayment(
    paymentRepository: $paymentRepository,
    mpesaClient: $mpesaClient,
);

$stkPushController = new StkPushController(
    initiatePayment: $initiatePayment,
);

$stkPushRoute = new StkPushRoute(
    registrar: new WordPressRestRegistrar(),
    controller: $stkPushController,
);

$paymentEventRepository = new WordPressPaymentEventRepository(
    database: $database,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_payment_events',
);

$webhookEndpointRepository = new WordPressWebhookEndpointRepository(
    database: $database,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_webhook_endpoints',
);

$webhookDeliveryRepository = new WordPressWebhookDeliveryRepository(
    database: $database,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_webhook_deliveries',
);

$transactionManager = new WordPressTransactionManager(
    database: $database,
);

$webhookQueue = new QueuePaymentCompletedWebhooks(
    endpointRepository: $webhookEndpointRepository,
    deliveryRepository: $webhookDeliveryRepository,
    payloadBuilder: new PaymentCompletedPayloadBuilder(),
);

$webhookDeliveryWorker = new WebhookDeliveryWorker(
    repository: $webhookDeliveryRepository,
    httpClient: $webhookHttpClient,
    signature: new WebhookSignature(),
    webhookSecret: (string) get_option(
        'brifnet_mpesa_webhook_secret',
        ''
    ),
);

$completePayment = new CompletePayment(
    paymentRepository: $paymentRepository,
    eventRepository: $paymentEventRepository,
    transactionManager: $transactionManager,
    eventIdGenerator: new PaymentEventIdGenerator(),
    webhookQueue: $webhookQueue,
    webhookDeliveryWorker: $webhookDeliveryWorker,
);

$processor = new ProcessStkCallback(
    parser: new StkCallbackParser(),
    paymentRepository: $paymentRepository,
    completePayment: $completePayment,
);

$controller = new StkCallbackController(
    processor: $processor,
);

$callbackRoute = new StkCallbackRoute(
    registrar: new WordPressRestRegistrar(),
    controller: $controller,
);

$c2bProcessor = new ProcessC2BPayment(
    parser: new C2BPaymentParser(),
    validator: new C2BPaymentValidator(),
    paymentRepository: $paymentRepository,
    completePayment: $completePayment,
    referenceGenerator: new DefaultPaymentReferenceGenerator(),
);

$c2bController = new C2BPaymentController(
    processor: $c2bProcessor,
);

$c2bRoute = new C2BPaymentRoute(
    registrar: new WordPressRestRegistrar(),
    controller: $c2bController,
);


$webhookEndpointController = new WebhookEndpointController(
    registrar: new RegisterWebhookEndpoint(
        repository: $webhookEndpointRepository,
    ),
    reactivator: new ReactivateWebhookEndpoint(
        repository: $webhookEndpointRepository,
    ),
    deactivator: new DeactivateWebhookEndpoint(
        repository: $webhookEndpointRepository,
    ),
);

$webhookEndpointRoute = new WebhookEndpointRoute(
    registrar: new WordPressRestRegistrar(),
    controller: $webhookEndpointController,
);

$schemaExecutor = new WordPressSchemaExecutor();

$transactionSchema = new WordPressSchema(
    database: $database,
    executor: $schemaExecutor,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_transactions',
);

$paymentEventSchema = new WordPressPaymentEventSchema(
    database: $database,
    executor: $schemaExecutor,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_payment_events',
);

$webhookEndpointSchema = new WordPressWebhookEndpointSchema(
    database: $database,
    executor: $schemaExecutor,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_webhook_endpoints',
);

$webhookDeliverySchema = new WordPressWebhookDeliverySchema(
    database: $database,
    executor: $schemaExecutor,
    tableName: $GLOBALS['wpdb']->prefix . 'brifnet_mpesa_webhook_deliveries',
);

$plugin = new Plugin(
    hooks: $hooks,
    stkCallbackRoute: $callbackRoute,
    stkPushRoute: $stkPushRoute,
    c2bPaymentRoute: $c2bRoute,
    webhookEndpointRoute: $webhookEndpointRoute,
    schemas: [
        $transactionSchema,
        $paymentEventSchema,
        $webhookEndpointSchema,
        $webhookDeliverySchema,
    ],
    activationRegistrar: new WordPressActivationRegistrar(),
    pluginFile: __FILE__,
);

$plugin->boot();