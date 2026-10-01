# BrifNet M-Pesa Gateway — Developer Guide

This document describes the internal architecture, application flow, domain model, integration boundaries and implementation decisions of the BrifNet M-Pesa Gateway WordPress plugin.

The README is intended for users integrating the gateway.

This document is intended for developers working on the gateway itself or building systems that integrate with it at a deeper level.

---

# 1. Purpose

BrifNet M-Pesa Gateway is a WordPress plugin that provides a reusable M-Pesa payment boundary for external applications.

The plugin sits between:

```text
Safaricom Daraja
        |
        v
BrifNet M-Pesa Gateway
        |
        v
Client Application
```

The WordPress installation hosts the gateway and provides:

* the REST API
* payment persistence
* provider callback processing
* payment event generation
* webhook registration
* webhook delivery
* webhook signing

The gateway is intentionally not a complete business application.

For example, the gateway does not decide:

* which ISP service should be activated
* which e-commerce order should be fulfilled
* which subscription should be renewed
* which customer account should receive credit

Those decisions belong to the consuming application.

The gateway provides the payment event and enough payment information for the consuming application to perform its own business operation.

---

# 2. Architectural Layers

The project is organized around several logical layers:

```text
src/
├── Api/
├── Application/
├── Domain/
├── Infrastructure/
└── WordPress/
```

The intended dependency direction is:

```text
API
 |
 v
Application
 |
 v
Domain
 ^
 |
Infrastructure
 |
 v
WordPress
```

The Domain layer should not depend directly on WordPress.

WordPress-specific concerns belong at the integration boundary.

---

# 3. Domain Layer

The Domain layer contains concepts that represent the gateway's business behavior.

Important domain concepts include:

* `Payment`
* `PaymentCompleted`
* `WebhookEndpoint`
* `WebhookDelivery`
* `WebhookSignature`

Repository interfaces also belong at the domain/application boundary where appropriate.

The domain should describe **what the system means**, rather than **how WordPress stores it**.

For example:

```text
WebhookEndpoint
```

represents the concept of an endpoint subscribed to events.

It should not need to know whether the endpoint is stored in:

* MySQL
* MariaDB
* another database
* a WordPress table

That concern belongs to the infrastructure implementation.

---

# 4. Application Layer

The Application layer contains workflows.

Examples include:

```text
InitiatePayment
ProcessPaymentCallback
ProcessC2BPayment
QueuePaymentCompletedWebhooks
WebhookDeliveryWorker
```

Application services coordinate domain objects and infrastructure contracts.

They should not contain unnecessary WordPress-specific behavior.

---

# 5. Infrastructure Layer

Infrastructure implements the interfaces required by the application.

Examples include:

```text
WordPressPaymentRepository
WordPressWebhookEndpointRepository
WordPressWebhookDeliveryRepository
WordPressWebhookHttpClient
WordPressDatabase
```

This is where the application crosses into WordPress or external infrastructure.

---

# 6. WordPress Layer

The WordPress layer contains framework-specific integration.

This includes:

* plugin bootstrap
* REST route registration
* WordPress hooks
* configuration loading
* WordPress options
* WordPress database integration

The WordPress layer should remain an adapter around the application rather than becoming the location of business rules.

---

# 7. Payment Lifecycle

A payment moves through several stages.

For STK:

```text
Client request
      |
      v
Payment validation
      |
      v
Payment persistence
      |
      v
Daraja STK request
      |
      v
Customer interaction
      |
      v
Daraja callback
      |
      v
Payment completion
      |
      v
PaymentCompleted event
      |
      v
Webhook delivery
```

The important distinction is:

```text
STK initiated
```

versus:

```text
Payment completed
```

They are not the same state.

---

# 8. STK Processing

The client sends:

```json
{
  "reference": "ORDER-1001",
  "phone": "0729633304",
  "amount": 500
}
```

The application validates the request before calling the provider integration.

The gateway creates/persists the payment state and sends the STK request through the provider abstraction.

The provider returns request identifiers such as:

```text
MerchantRequestID
CheckoutRequestID
```

These identifiers identify the provider-side request.

The gateway returns them to the client.

The client should not interpret this as successful payment.

---

# 9. STK Callback Processing

Safaricom later calls:

```text
POST /wp-json/brifnet/v1/mpesa/callback
```

The callback contains the asynchronous result of the STK request.

The gateway parses the callback and determines whether the payment completed.

For a successful payment, the gateway records the payment completion and creates a payment completion event.

The application then queues webhook deliveries.

---

# 10. C2B Processing

C2B differs from STK because the gateway does not necessarily have a client-created payment request to correlate against.

The provider supplies transaction information.

The gateway maps provider fields into its payment model.

The important mapping is:

```text
BillRefNumber → account_number
TransID       → provider_transaction_id
```

The gateway generates its own internal payment reference.

The provider transaction ID remains the important provider-level identifier.

---

# 11. Payment Idempotency

Provider callbacks can be delivered more than once.

The gateway must therefore avoid creating duplicate payment effects.

C2B processing uses the provider transaction identifier:

```text
TransID
```

as the provider idempotency identity.

STK processing similarly uses provider identifiers available from the payment lifecycle.

The general rule is:

```text
Same provider transaction
        +
Repeated callback
        =
Same payment operation
```

rather than:

```text
Repeated callback
        =
New payment
```

Idempotency is essential because external payment providers operate asynchronously and network delivery is not guaranteed to be exactly once.

---

# 12. Payment Completion Events

When a payment transitions into the completed state, the gateway creates:

```text
payment.completed
```

The event contains:

```text
event_id
event
occurred_at
data
```

Example:

```json
{
  "event_id": "evt-123",
  "event": "payment.completed",
  "occurred_at": "2026-09-04T12:30:00+03:00",
  "data": {
    "reference": "ORDER-1001",
    "phone": "0712345678",
    "amount": 500,
    "channel": "STK",
    "provider_reference": "ws_CO_67890",
    "provider_transaction_id": "ABC123XYZ"
  }
}
```

The event payload is serialized and persisted before delivery.

This is important because webhook delivery should not depend on reconstructing the payment event later.

---

# 13. Event IDs

Every payment event receives a unique:

```text
event_id
```

The event ID identifies the event independently of the delivery attempt.

This distinction is important:

```text
event_id
```

is not the same thing as:

```text
delivery attempt
```

One event can have multiple delivery attempts.

For example:

```text
event_id = evt-123

attempt 1 → failed
attempt 2 → failed
attempt 3 → delivered
```

The event remains:

```text
evt-123
```

throughout.

---

# 14. Webhook Endpoint Model

A webhook endpoint contains information such as:

```text
url
active
events
created_at
updated_at
```

The URL is unique.

An endpoint may subscribe to multiple event types:

```json
{
  "url": "https://example.com/webhook",
  "events": [
    "payment.completed"
  ]
}
```

The `active` flag controls whether new matching deliveries should be created.

Deactivation does not delete the endpoint.

---

# 15. Webhook Queueing

When an event is generated:

```text
payment.completed
```

the gateway finds:

```text
active endpoints
+
endpoints subscribed to payment.completed
```

For every matching endpoint, it creates a delivery record.

Conceptually:

```text
                 payment.completed
                         |
                         v
               Find matching endpoints
                         |
              ┌──────────┼──────────┐
              |          |          |
              v          v          v
            URL A      URL B      URL C
              |          |          |
              v          v          v
         Delivery A  Delivery B  Delivery C
```

The event therefore has independent delivery state for each endpoint.

---

# 16. Delivery Persistence

The delivery model contains information including:

```text
event_id
url
payload
status
attempts
created_at
updated_at
```

The important design decision is that the payload is stored.

The worker does not regenerate the event payload every time it retries.

Instead:

```text
Create event
     |
     v
Serialize payload
     |
     v
Persist delivery
     |
     v
Worker sends stored payload
```

This makes retries deterministic with respect to the event body.

---

# 17. Webhook Delivery Worker

The worker obtains pending deliveries.

For each delivery:

```text
Is delivery retryable?
       |
       +-- no --> permanently failed
       |
       +-- yes
             |
             v
       Generate timestamp
             |
             v
       Generate signature
             |
             v
       Send HTTP request
             |
       ┌─────┴─────┐
       |           |
      2xx        failure
       |           |
       v           v
   delivered      retry
```

The worker is responsible for delivery orchestration.

The webhook endpoint domain object remains responsible for its own state transitions.

---

# 18. Delivery Attempts

The delivery model starts with:

```text
attempts = 0
```

A failed attempt increments the attempt counter.

The configured worker currently allows up to three actual delivery attempts.

Therefore:

```text
attempts = 0
→ send attempt 1

attempts = 1
→ send attempt 2

attempts = 2
→ send attempt 3

attempts = 3
→ no further attempt
```

A delivery that has exhausted its retry attempts becomes permanently failed.

---

# 19. HTTP Success Semantics

The HTTP client considers any `2xx` response successful.

Therefore:

```text
200
201
202
204
```

are successful deliveries.

Anything outside the `2xx` range is a failure.

Network/client exceptions are also failures.

This keeps the worker independent of the receiving application's specific success response code.

---

# 20. Webhook Signing

Webhook signing is implemented using:

```text
HMAC-SHA256
```

The signature input is:

```text
timestamp + "." + payload
```

where `payload` is the exact JSON string stored for the delivery.

Conceptually:

```php
hash_hmac(
    'sha256',
    $timestamp . '.' . $payload,
    $secret
);
```

The worker sends:

```http
X-BrifNet-Timestamp: 2026-09-04T12:30:00Z
X-BrifNet-Signature: sha256=<hex-signature>
```

---

# 21. Why the Raw Payload Matters

The receiver must calculate the signature using the exact HTTP body.

For example, these JSON documents can represent the same logical data:

```json
{"amount":500,"reference":"ORDER-1001"}
```

and:

```json
{
  "reference": "ORDER-1001",
  "amount": 500
}
```

They are not necessarily the same byte sequence.

HMAC operates on bytes, not on the abstract meaning of the JSON.

Therefore the receiver should capture the raw request body first.

Only after signature verification should it parse the JSON.

---

# 22. Webhook Secret Storage

The gateway's webhook secret is stored as a WordPress option:

```text
brifnet_mpesa_webhook_secret
```

The worker receives the secret during plugin bootstrap.

The bootstrap obtains it through:

```php
get_option(
    'brifnet_mpesa_webhook_secret',
    ''
)
```

The option is therefore the gateway's current source of truth for the shared secret.

There is currently no dedicated WordPress admin UI for managing this secret.

---

# 23. Provisioning the Webhook Secret

The recommended provisioning approach is WP-CLI.

Generate a secret:

```bash
wp eval 'echo wp_generate_password(64, true, true) . PHP_EOL;'
```

Then store it:

```bash
wp option update brifnet_mpesa_webhook_secret "YOUR_GENERATED_SECRET"
```

The secret should be generated once for the integration and securely distributed to the receiving application.

Do not hard-code the secret into plugin source code.

---

# 24. Retrieving the Webhook Secret

An administrator can retrieve the configured option through WP-CLI:

```bash
wp option get brifnet_mpesa_webhook_secret
```

This is primarily useful when configuring the receiving application.

The value returned is sensitive.

A production workflow should preferably place the retrieved value directly into the receiving application's secret store rather than copying it through insecure channels.

---

# 25. Webhook Secret Rotation

The current implementation does not provide automatic secret rotation.

If a secret must be rotated:

1. Generate a new secret.
2. Configure the receiving application to use the new secret.
3. Update the WordPress option.
4. Verify webhook delivery.
5. Remove the old secret from systems that no longer need it.

Because the gateway currently supports one configured webhook secret, rotation should be coordinated.

A future implementation could support multiple active secrets during a rotation window.

---

# 26. Replay Protection

The timestamp is part of the signed message:

```text
timestamp + "." + payload
```

This means an attacker cannot simply change the timestamp without invalidating the signature.

However, a valid signed request could theoretically be replayed while the signature remains valid.

The receiver should therefore enforce timestamp freshness.

Example policy:

```text
current time - timestamp <= configured window
```

A five-minute window is a reasonable example, but the receiving application owns this policy.

The gateway provides the timestamp but does not currently enforce the receiver's freshness policy.

---

# 27. Webhook Idempotency

At-least-once delivery means a receiving application must assume duplicate delivery.

The receiver should persist:

```text
event_id
```

before or as part of its business transaction.

A robust receiver can use a database uniqueness constraint:

```text
UNIQUE(event_id)
```

Then:

```text
Webhook received
       |
       v
Verify signature
       |
       v
Validate timestamp
       |
       v
Insert event_id
       |
   ┌───┴────┐
   |        |
 success   duplicate
   |        |
   v        v
process   acknowledge
event     without
          repeating
```

The exact transaction strategy belongs to the consuming application.

---

# 28. Security Boundary

There are two different HTTP trust boundaries.

## Safaricom → Gateway

These are provider callbacks.

```text
Safaricom
    |
    v
WordPress Gateway
```

These endpoints need to be reachable by the provider.

## Gateway → Client Application

These are outgoing webhook requests.

```text
WordPress Gateway
    |
    | HMAC signed
    v
Client Application
```

These requests are authenticated using the shared webhook secret.

These two flows should not be confused.

---

# 29. Client API Authentication

The current implementation does not yet provide API-key authentication for the client-facing REST endpoints.

This is an intentional limitation of the current implementation.

For example:

```text
POST /wp-json/brifnet/v1/mpesa/stk
```

does not currently require a BrifNet API key.

This means deployments must consider who can reach these endpoints.

A future authentication layer could introduce:

```text
API key
Bearer token
Application credentials
```

without changing the core payment domain.

Authentication should be added at the API/infrastructure boundary rather than embedded inside payment domain objects.

---

# 30. Provider Integration Boundary

Safaricom-specific behavior should remain at the provider integration boundary.

The rest of the application should work with gateway concepts such as:

```text
Payment
PaymentCompleted
PaymentReference
ProviderTransactionId
```

rather than spreading raw Daraja response structures throughout the application.

This makes the gateway easier to test and potentially extend to another provider later.

---

# 31. Persistence

The gateway persists payment and webhook state using WordPress-compatible database infrastructure.

Persistence concerns are kept behind repository abstractions where appropriate.

For example:

```text
Application
    |
    v
Repository interface
    |
    v
WordPress repository implementation
    |
    v
WordPress database
```

This prevents application workflows from becoming tightly coupled to SQL implementation details.

---

# 32. Webhook Delivery Data Model

The delivery model effectively represents:

```text
Event
  |
  +-- Endpoint A → Delivery A
  |
  +-- Endpoint B → Delivery B
  |
  +-- Endpoint C → Delivery C
```

This is important because each endpoint has independent delivery state.

For example:

```text
Event evt-123

Endpoint A → delivered
Endpoint B → failed
Endpoint C → delivered
```

A failure at Endpoint B does not invalidate the successful delivery to Endpoint A.

---

# 33. Why Delivery Records Are Separate

The event describes:

```text
what happened
```

The delivery describes:

```text
where the event was sent
and what happened during delivery
```

This separation allows the gateway to support:

* multiple webhook endpoints
* independent retries
* independent delivery status
* endpoint-specific failures

without changing the underlying payment event.

---

# 34. Testing Strategy

The project uses PHPUnit.

Testing is divided conceptually into:

```text
Domain tests
Application tests
Infrastructure tests
API tests
Integration tests
```

Provider network calls should not be required for ordinary unit tests.

External systems are isolated behind abstractions.

This allows tests to verify behavior without depending on:

* Safaricom availability
* network latency
* external credentials
* external transaction state

---

# 35. Important Webhook Test Cases

Webhook behavior should be protected by tests covering at least:

### Signature generation

Given:

```text
timestamp
payload
secret
```

the signature must be deterministic.

### Signature format

The outgoing header should contain:

```text
sha256=<signature>
```

### Fresh timestamps

Each delivery attempt generates its own timestamp.

### Retry behavior

Failed deliveries increment attempts.

### Retry limit

A delivery exceeding the maximum number of attempts becomes permanently failed.

### 2xx behavior

Any HTTP `2xx` response marks the delivery as successful.

### Non-2xx behavior

A non-2xx response results in a failed delivery.

### Exception behavior

HTTP client exceptions result in a failed delivery.

### Payload persistence

The worker sends the persisted payload rather than regenerating it.

### Event idempotency

The same payment callback should not create duplicate payment effects.

---

# 36. Adding a New Webhook Event

When adding an event such as:

```text
payment.failed
```

the implementation should consider:

1. Define the event name.
2. Define its payload contract.
3. Define the domain event representation.
4. Update event validation.
5. Update endpoint subscription behavior.
6. Update event queueing.
7. Add delivery tests.
8. Document the event.
9. Add receiver examples where useful.

Do not simply add a string to the documentation.

An event is part of an API contract.

---

# 37. Adding a New Payment Channel

A new payment channel should follow the same architectural boundaries.

For example:

```text
Provider
   |
   v
Provider adapter
   |
   v
Payment processing use case
   |
   v
Payment
   |
   v
PaymentCompleted
   |
   v
Webhook
```

Provider-specific parsing should remain close to the provider integration.

The resulting domain/application model should remain provider-neutral where possible.

---

# 38. API Layer Responsibilities

API classes should primarily handle:

* receiving HTTP requests
* decoding request data
* validating input
* invoking application use cases
* converting application results into HTTP responses

They should not become large containers for business rules.

For example:

```text
REST Controller
      |
      v
Application Use Case
      |
      v
Domain
```

rather than:

```text
REST Controller
      |
      +-- validate
      +-- query database
      +-- call Safaricom
      +-- modify payment
      +-- create event
      +-- send webhook
```

The latter becomes difficult to test and maintain.

---

# 39. WordPress as an Integration Framework

WordPress provides the runtime environment for the plugin.

The architecture should therefore use WordPress where WordPress is useful:

* REST API
* options
* database
* plugin lifecycle
* hooks

But business concepts should not unnecessarily depend on WordPress APIs.

For example, the domain should not need to know that:

```text
brifnet_mpesa_webhook_secret
```

is stored using `get_option()`.

That knowledge belongs in the WordPress/bootstrap/infrastructure boundary.

---

# 40. Development Workflow

A typical development workflow is:

```bash
composer install
```

then:

```bash
vendor/bin/phpunit
```

Before committing:

```bash
vendor/bin/phpunit
```

Review:

```bash
git diff
```

and:

```bash
git status
```

Changes should ideally be kept focused.

For example:

```text
Webhook signing
```

should not unnecessarily include unrelated refactoring.

---

# 41. Configuration and Secrets

There are currently two conceptually different categories of secrets.

## Provider credentials

These are used to communicate with Safaricom:

```text
DARAJA_CONSUMER_KEY
DARAJA_CONSUMER_SECRET
DARAJA_PASSKEY
```

and related configuration.

## Webhook secret

This is used between the gateway and receiving application:

```text
brifnet_mpesa_webhook_secret
```

They serve different purposes.

```text
Daraja credentials
      |
      v
Gateway → Safaricom

Webhook secret
      |
      v
Gateway → Client Application
```

Neither should be committed to source control.

---

# 42. Production Deployment Considerations

A production deployment should provide:

* HTTPS
* valid Daraja credentials
* reachable provider callbacks
* reliable WordPress hosting
* reliable database persistence
* secure webhook secret storage
* HTTPS webhook endpoints
* monitoring of webhook delivery failures
* controlled access to WP-CLI
* secure backups

The receiving application should also provide:

* signature verification
* timestamp/replay protection
* event idempotency
* transaction-safe payment processing
* logging
* appropriate alerting

---

# 43. Operational Monitoring

A production system should monitor at least:

```text
Payment failures
Provider callback failures
Webhook delivery failures
Permanently failed deliveries
Unexpected callback volume
Repeated webhook events
```

A future gateway dashboard could expose:

```text
Payment
Webhook endpoint
Delivery status
Attempt count
Last failure
Last successful delivery
```

The current implementation provides the underlying persistence required for such tooling but does not yet provide a full operational dashboard.

---

# 44. Current Scope

Implemented:

* STK Push
* STK callbacks
* C2B callbacks
* payment persistence
* payment idempotency
* `payment.completed`
* webhook registration
* webhook activation
* webhook deactivation
* persistent webhook deliveries
* HMAC-SHA256 signatures
* timestamped webhook requests
* retry handling
* at-least-once delivery
* event-level idempotency

Not currently implemented:

* client API-key authentication
* automatic Daraja callback registration
* webhook management UI
* webhook secret management UI
* automatic secret rotation
* additional payment events
* advanced exponential backoff
* delivery dashboards
* webhook replay tooling

These are future capabilities rather than assumptions about the current implementation.

---

# 45. Design Principles

The project should preserve the following principles as it evolves.

## Keep provider integration isolated

Safaricom-specific details should not leak throughout the application.

## Keep business logic out of controllers

Controllers should coordinate, not become the business layer.

## Treat payments as asynchronous

Payment initiation and payment completion are separate operations.

## Make callbacks idempotent

External providers may retry callbacks.

## Make webhook receivers idempotent

The gateway deliberately provides at-least-once delivery.

## Persist before delivery

Events should not depend on an immediate network request succeeding.

## Sign outgoing webhooks

The receiver needs a way to establish authenticity.

## Keep secrets outside source control

Credentials and shared secrets are deployment configuration.

## Prefer explicit contracts

Payment events and webhook payloads are integration contracts and should be documented as such.

---

# 46. Integration Contract Summary

The core integration contract can be summarized as:

```text
CLIENT
  |
  | POST /mpesa/stk
  | reference + phone + amount
  v
GATEWAY
  |
  | STK Push
  v
DARaja
  |
  | callback
  v
GATEWAY
  |
  | payment.completed
  | signed with HMAC-SHA256
  v
CLIENT WEBHOOK
```

For C2B:

```text
CUSTOMER
   |
   | M-Pesa payment
   v
DARaja
   |
   | BillRefNumber + TransID
   v
GATEWAY
   |
   | payment.completed
   | account_number + provider_transaction_id
   v
CLIENT WEBHOOK
```

The gateway's job is to provide a reliable payment integration boundary.

The consuming application's job is to decide what a completed payment means for its own business.

---

# 47. Final Integration Checklist

Before considering an integration complete, verify:

### Gateway

* [ ] Daraja credentials configured
* [ ] STK callback URL configured
* [ ] C2B callback configured where applicable
* [ ] webhook endpoint registered
* [ ] webhook endpoint active
* [ ] webhook secret generated
* [ ] webhook secret stored in WordPress

### Receiving Application

* [ ] same webhook secret configured securely
* [ ] HTTPS webhook endpoint available
* [ ] raw request body available for signature verification
* [ ] `X-BrifNet-Timestamp` verified
* [ ] `X-BrifNet-Signature` verified
* [ ] timestamp freshness enforced
* [ ] `event_id` idempotency implemented
* [ ] event payload validated
* [ ] successful HTTP response returned after processing

### Payment Processing

* [ ] STK initiation does not mark payment as completed
* [ ] completion is driven by `payment.completed`
* [ ] client reference is correlated correctly
* [ ] C2B `BillRefNumber` is handled as `account_number`
* [ ] C2B `TransID` is handled as `provider_transaction_id`
* [ ] duplicate events cannot repeat the business operation

---

# 48. Future Evolution

The current architecture provides a foundation for future capabilities without requiring the gateway to become tightly coupled to a specific consuming application.

Potential future additions include:

* API authentication
* webhook secret management UI
* webhook secret rotation
* delivery backoff
* delivery dashboards
* manual webhook replay
* additional payment events
* payment reversal handling
* payment failure events
* multiple payment providers
* application-level API credentials
* richer operational monitoring

Future changes should preserve the separation between:

```text
Provider
Domain
Application
Infrastructure
API
WordPress
```

The gateway should remain a reusable payment integration component rather than becoming an application-specific payment workflow.
