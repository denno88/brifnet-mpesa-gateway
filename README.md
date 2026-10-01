# BrifNet M-Pesa Gateway

WordPress plugin that provides an application-facing API for M-Pesa payments and webhook notifications.

The gateway integrates Safaricom Daraja with WordPress and exposes a REST API that client applications can use to initiate payments and receive payment completion events.

The plugin handles:

* M-Pesa STK Push initiation
* Safaricom Daraja STK callbacks
* Safaricom Daraja C2B callbacks
* Payment persistence
* Payment idempotency
* Payment completion events
* Webhook registration
* Webhook activation and deactivation
* Persistent webhook delivery records
* HMAC-SHA256 webhook signatures
* At-least-once webhook delivery
* Webhook retry handling
* Webhook event idempotency
* WordPress REST API integration

The gateway is designed to sit between **Safaricom Daraja** and one or more client applications.

```text
                    ┌─────────────────────┐
                    │      Safaricom      │
                    │       Daraja        │
                    └──────────┬──────────┘
                               │
                         Provider callbacks
                               │
                               ▼
                    ┌─────────────────────┐
                    │ BrifNet M-Pesa      │
                    │ WordPress Plugin    │
                    │                     │
                    │ Payment processing  │
                    │ Persistence         │
                    │ Events              │
                    │ Webhook delivery    │
                    └──────────┬──────────┘
                               │
                         Signed webhook
                               │
                               ▼
                    ┌─────────────────────┐
                    │ Client Application  │
                    │                     │
                    │ ISP / E-commerce /  │
                    │ SaaS / Other App    │
                    └─────────────────────┘
```

---

# Requirements

The plugin requires:

* WordPress
* PHP 8.4+
* Composer
* MySQL or MariaDB
* Safaricom Daraja credentials
* A publicly reachable HTTPS URL for Daraja callbacks
* A publicly reachable HTTPS URL for each receiving webhook endpoint

The plugin uses Composer dependencies.

---

# Installation

## 1. Install the Plugin

Place the plugin in the WordPress plugins directory:

```text
wp-content/plugins/brifnet-mpesa-gateway
```

The resulting structure should contain the plugin entry point:

```text
wp-content/
└── plugins/
    └── brifnet-mpesa-gateway/
        ├── src/
        ├── tests/
        ├── composer.json
        └── brifnet-mpesa-gateway.php
```

## 2. Install Composer Dependencies

From the plugin directory:

```bash
composer install
```

This installs the dependencies defined by `composer.lock`.

The generated `vendor/` directory should not be committed to the repository.

## 3. Activate the Plugin

Activate the plugin through WordPress:

```text
Plugins → Installed Plugins → BrifNet M-Pesa Gateway → Activate
```

The plugin registers its REST API routes when WordPress loads the plugin.

---

# Configuration

The plugin requires Safaricom Daraja configuration.

The Daraja configuration loader reads the required values from environment-specific configuration.

The exact configuration mechanism can be adapted for the deployment environment. The important requirement is that credentials remain outside source control.

## Required Daraja Configuration

| Variable                    | Description               |
| --------------------------- | ------------------------- |
| `DARAJA_CONSUMER_KEY`       | Daraja consumer key       |
| `DARAJA_CONSUMER_SECRET`    | Daraja consumer secret    |
| `DARAJA_BUSINESS_SHORTCODE` | M-Pesa business shortcode |
| `DARAJA_PASSKEY`            | M-Pesa passkey            |
| `DARAJA_AUTH_URL`           | Daraja OAuth endpoint     |
| `DARAJA_STK_PUSH_URL`       | Daraja STK Push endpoint  |
| `DARAJA_STK_QUERY_URL`      | Daraja STK Query endpoint |
| `DARAJA_CALLBACK_URL`       | Public STK callback URL   |

For sandbox development, use the appropriate Safaricom sandbox credentials and endpoints.

For production, use the production credentials and endpoints supplied for the M-Pesa integration.

Never commit real Daraja credentials to Git.

---

# Daraja Callback URLs

The plugin provides REST endpoints for Safaricom Daraja callbacks.

These endpoints are different from the client-facing API.

For a WordPress site running at:

```text
https://example.com
```

the provider callback endpoints are:

## STK Callback

```http
POST https://example.com/wp-json/brifnet/v1/mpesa/callback
```

This receives the asynchronous result of an STK Push request.

Configure the corresponding URL as:

```env
DARAJA_CALLBACK_URL=https://example.com/wp-json/brifnet/v1/mpesa/callback
```

## C2B Callback

```http
POST https://example.com/wp-json/brifnet/v1/mpesa/c2b
```

This receives C2B requests from Daraja.

The C2B integration should be configured to use this endpoint.

## Callback Registration

The plugin currently does not automatically register the callback URLs with Safaricom.

Daraja registration/configuration is therefore a deployment or integration step outside the plugin.

## Callback Requirements

The WordPress installation receiving callbacks must be publicly reachable.

Production deployments should:

* use HTTPS
* allow Safaricom to reach the callback endpoints
* avoid requiring normal WordPress authentication on provider callback routes
* use a publicly reachable hostname
* never use `localhost` as a Daraja callback URL

The plugin validates required configuration and will fail configuration-dependent operations when required values are missing.

---

# API Base URL

The client-facing API is exposed through the WordPress REST API.

For a WordPress site:

```text
https://your-domain.com/wp-json/brifnet/v1
```

The following sections use that base URL.

---

# STK Push

## Initiate an STK Push

```http
POST /wp-json/brifnet/v1/mpesa/stk
Content-Type: application/json
```

Example:

```json
{
  "reference": "ORDER-1001",
  "phone": "0729633304",
  "amount": 500
}
```

## Request Fields

| Field       | Type    | Description                            |
| ----------- | ------- | -------------------------------------- |
| `reference` | string  | Client application's payment reference |
| `phone`     | string  | Customer's Kenyan phone number         |
| `amount`    | integer | Amount to request                      |

The phone number must use the supported Kenyan local format:

```text
07XXXXXXXX
```

The amount must be greater than zero.

The `reference` belongs to the client application.

For example, an ISP application might send:

```text
SUB-1045
```

while an e-commerce application might send:

```text
ORDER-1001
```

The gateway does not use the reference as its provider transaction ID. It is retained as the client application's correlation value.

---

## Successful STK Response

A successful STK initiation returns the provider request identifiers:

```http
200 OK
```

```json
{
  "message": "STK Push initiated.",
  "merchant_request_id": "3b8d-40c5-817d-9beb9d179fd433836",
  "checkout_request_id": "ws_CO_050920260057144729633304"
}
```

These identifiers identify the request at Safaricom.

### Important

A successful STK initiation **does not mean the customer has paid**.

It means that the STK request was accepted for processing.

The application should wait for the `payment.completed` event before treating the payment as completed.

---

## STK Errors

### Invalid Request

```http
400 Bad Request
```

```json
{
  "message": "Invalid request."
}
```

### Provider Rejection

```http
502 Bad Gateway
```

```json
{
  "message": "M-Pesa rejected the STK Push.",
  "error": "..."
}
```

### Internal Error

```http
500 Internal Server Error
```

```json
{
  "message": "Unable to initiate payment."
}
```

---

# Webhooks

The gateway can notify external applications when payments are completed.

The current supported event is:

```text
payment.completed
```

A webhook endpoint is an HTTPS URL belonging to the receiving application.

For example:

```text
https://isp.example.com/api/webhooks/mpesa
```

The gateway stores webhook registrations and creates persistent delivery records for matching events.

---

# Register a Webhook

```http
POST /wp-json/brifnet/v1/webhooks
Content-Type: application/json
```

Request:

```json
{
  "url": "https://example.com/webhook",
  "events": [
    "payment.completed"
  ]
}
```

Response:

```json
{
  "url": "https://example.com/webhook",
  "active": true,
  "events": [
    "payment.completed"
  ]
}
```

New webhook registrations are active immediately.

The endpoint URL must be valid and unique.

The `events` array allows the registration model to support additional event types as they are introduced.

---

# Deactivate a Webhook

```http
POST /wp-json/brifnet/v1/webhooks/deactivate
Content-Type: application/json
```

Request:

```json
{
  "url": "https://example.com/webhook"
}
```

Response:

```json
{
  "url": "https://example.com/webhook",
  "active": false,
  "events": [
    "payment.completed"
  ]
}
```

Deactivation does not delete the endpoint.

It prevents the endpoint from receiving new event deliveries while inactive.

---

# Activate a Webhook

```http
POST /wp-json/brifnet/v1/webhooks/activate
Content-Type: application/json
```

Request:

```json
{
  "url": "https://example.com/webhook"
}
```

Response:

```json
{
  "url": "https://example.com/webhook",
  "active": true,
  "events": [
    "payment.completed"
  ]
}
```

---

# Webhook Registration Validation

The gateway validates webhook registration requests.

Examples of validation errors include:

## Invalid JSON

```json
{
  "message": "Invalid JSON payload."
}
```

## Events Is Not an Array

```json
{
  "message": "Webhook events must be an array."
}
```

## Event Name Is Not a String

```json
{
  "message": "Webhook event names must be strings."
}
```

## Invalid URL

```json
{
  "message": "Webhook URL is invalid."
}
```

## Empty URL

```json
{
  "message": "Webhook URL cannot be empty."
}
```

## Duplicate Endpoint

```json
{
  "message": "Webhook endpoint already exists."
}
```

---

# Payment Completed Event

When a payment is completed, the gateway creates a `payment.completed` event.

The event is queued for every active webhook endpoint subscribed to that event.

The event contains a unique `event_id`.

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

---

# STK Payment Event

An STK payment contains:

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

## STK Event Fields

| Field                          | Description                                     |
| ------------------------------ | ----------------------------------------------- |
| `event_id`                     | Unique identifier for the event                 |
| `event`                        | Event name                                      |
| `occurred_at`                  | Event creation time                             |
| `data.reference`               | Client application's original payment reference |
| `data.phone`                   | Customer phone number                           |
| `data.amount`                  | Payment amount                                  |
| `data.channel`                 | `STK`                                           |
| `data.provider_reference`      | Safaricom STK request reference                 |
| `data.provider_transaction_id` | Safaricom transaction/receipt identifier        |

The receiving application normally uses:

```text
data.reference
```

to locate the business/payment record that initiated the STK request.

---

# C2B Payment Event

C2B payments are different from STK payments.

There is normally no client-side payment request created immediately before the customer pays.

Instead, Safaricom sends the completed transaction to the gateway.

Example:

```json
{
  "event_id": "evt-456",
  "event": "payment.completed",
  "occurred_at": "2026-09-04T12:30:00+03:00",
  "data": {
    "account_number": "KAM100",
    "phone": "0712345678",
    "amount": 349,
    "channel": "C2B",
    "provider_transaction_id": "RKT123456"
  }
}
```

## C2B Event Fields

| Field                          | Description                                |
| ------------------------------ | ------------------------------------------ |
| `event_id`                     | Unique identifier for the event            |
| `event`                        | Event name                                 |
| `occurred_at`                  | Event creation time                        |
| `data.account_number`          | Value received from Daraja `BillRefNumber` |
| `data.phone`                   | Customer phone number                      |
| `data.amount`                  | Payment amount                             |
| `data.channel`                 | `C2B`                                      |
| `data.provider_transaction_id` | Daraja `TransID`                           |

The mapping is:

```text
BillRefNumber → account_number
TransID       → provider_transaction_id
```

The gateway also creates an internal payment reference for the C2B transaction.

That internal gateway reference is not the client correlation field exposed in the C2B webhook.

For C2B idempotency, the provider transaction ID is the important provider-side identifier:

```text
TransID
```

---

# Webhook Security

Webhook requests are authenticated using HMAC-SHA256.

Each webhook delivery contains two security headers:

```http
X-BrifNet-Timestamp: 2026-09-04T12:30:00Z
X-BrifNet-Signature: sha256=<signature>
```

The signature is generated from:

```text
timestamp + "." + raw_request_body
```

using the shared webhook secret.

Conceptually:

```text
signature =
    HMAC-SHA256(
        timestamp + "." + raw_request_body,
        webhook_secret
    )
```

The resulting hexadecimal signature is sent as:

```text
sha256=<hex-signature>
```

---

# Webhook Secret

Webhook requests are authenticated using a shared secret.

The same secret must be configured in two places:

1. The WordPress installation running BrifNet M-Pesa Gateway.
2. The application receiving the webhook.

The gateway stores the webhook secret in the WordPress options table under:

```text
brifnet_mpesa_webhook_secret
```

The plugin reads this value internally using:

```php
get_option('brifnet_mpesa_webhook_secret', '')
```

`get_option()` only **reads** the value. It does not create or update the option.

## Generate a Secret

Generate a cryptographically random secret rather than choosing a human-readable password.

Using WP-CLI:

```bash
wp eval 'echo wp_generate_password(64, true, true) . PHP_EOL;'
```

Example output:

```text
YOUR-GENERATED-RANDOM-SECRET
```

Do not use the example value above. Generate a new value for the actual integration.

## Save the Secret in WordPress

Use the WordPress options API through WP-CLI:

```bash
wp option update brifnet_mpesa_webhook_secret "YOUR-GENERATED-RANDOM-SECRET"
```

This command creates the option if it does not already exist, or updates it if it already exists.

For example:

```bash
wp option update brifnet_mpesa_webhook_secret "Kx7...generated-secret...9Q"
```

The gateway will subsequently retrieve the value using:

```php
get_option('brifnet_mpesa_webhook_secret', '')
```

## Verify the Stored Secret

To retrieve the value currently stored in WordPress:

```bash
wp option get brifnet_mpesa_webhook_secret
```

The returned value should be the same value that was generated and stored.

Treat the output as sensitive.

Do not include it in:

* Git commits
* GitHub
* source code
* screenshots
* public documentation
* webhook URLs
* webhook payloads
* `.env.example`
* public WordPress configuration

## Configure the Receiving Application

The receiving application must store the **same secret** using its own secure configuration mechanism.

For example, a receiving application might have:

```env
BRIFNET_WEBHOOK_SECRET=YOUR-GENERATED-RANDOM-SECRET
```

The exact variable name and storage mechanism belong to the receiving application.

The important requirement is:

```text
WordPress Gateway
    |
    | brifnet_mpesa_webhook_secret
    |
    +----------------------+
                           |
                           | SAME SECRET
                           |
                           v
                   Receiving Application
                       BRIFNET_WEBHOOK_SECRET
```

The gateway does not send the secret with the webhook.

Instead, both systems independently use the same secret to authenticate the request.

## Signature Verification

For every webhook delivery, the gateway generates:

```text
timestamp = current UTC timestamp
```

and calculates:

```text
HMAC-SHA256(
    timestamp + "." + raw_request_body,
    webhook_secret
)
```

The request contains:

```http
X-BrifNet-Timestamp: 2026-09-04T12:30:00Z
X-BrifNet-Signature: sha256=<signature>
```

The receiving application must calculate the signature using its stored copy of the secret and compare it with the received signature.

The receiving application should use a timing-safe comparison.

## Secret Rotation

The current gateway supports one active webhook secret.

To rotate the secret:

1. Generate a new secret.
2. Update the receiving application with the new secret.
3. Update the WordPress option:

```bash
wp option update brifnet_mpesa_webhook_secret "NEW-GENERATED-SECRET"
```

4. Verify webhook delivery.
5. Remove the old secret from the receiving application's configuration.

Future versions may support overlapping secrets to make rotation possible without coordination downtime.

A typical integration looks like:

```text
                    Generate secret
                           |
                           v
                  ┌───────────────┐
                  │ Shared Secret │
                  └───────┬───────┘
                          |
             ┌────────────┴────────────┐
             |                         |
             v                         v
      WordPress option        Receiving application
             |                         |
             v                         v
brifnet_mpesa_webhook_secret   secure application secret
             |                         |
             └────────────┬────────────┘
                          |
                          v
                  Signature verification
```

The two systems must contain the same secret.

The gateway never needs the receiving application's secret separately.

---

# Verifying a Webhook

The receiving application should verify a webhook before processing its business operation.

The recommended sequence is:

1. Read the raw HTTP request body.
2. Read `X-BrifNet-Timestamp`.
3. Read `X-BrifNet-Signature`.
4. Obtain the shared webhook secret.
5. Calculate:

```text
HMAC-SHA256(timestamp + "." + raw_body, secret)
```

6. Compare the calculated value with the received signature using a timing-safe comparison.
7. Validate the timestamp.
8. Reject stale requests according to the application's configured replay window.
9. Parse and validate the JSON payload.
10. Check `event_id` for duplicate processing.
11. Process the event.
12. Return a successful HTTP response.

The signature must be calculated against the **exact raw request body**.

Do not do this:

```text
HTTP body
   ↓
parse JSON
   ↓
re-encode JSON
   ↓
calculate signature
```

Instead:

```text
HTTP body
   ↓
use exact raw bytes
   ↓
calculate signature
```

JSON formatting, whitespace, escaping and key ordering can change when JSON is parsed and re-encoded.

---

# Replay Protection

The timestamp provides information that the receiving application can use to prevent replay attacks.

The gateway sends a fresh timestamp for each delivery attempt.

The receiving application should configure a reasonable freshness window.

For example, an application might reject requests whose timestamp is more than five minutes away from the current time.

The exact window is the responsibility of the receiving application.

The gateway does not currently enforce the receiver's replay window.

A typical verification sequence is:

```text
Receive request
      |
      v
Verify timestamp format
      |
      v
Check timestamp freshness
      |
      v
Verify HMAC signature
      |
      v
Check event_id
      |
      v
Process event
```

---

# Webhook Delivery

Webhook delivery uses a persistent delivery model.

When a `payment.completed` event is generated:

```text
Payment Completed
       |
       v
Payment Event
       |
       v
Find active subscribed endpoints
       |
       v
Create delivery records
       |
       v
Webhook Delivery Worker
       |
       +--------> HTTP 2xx
       |              |
       |              v
       |           delivered
       |
       +--------> HTTP non-2xx / exception
                      |
                      v
                    retry
```

The payload is persisted before delivery.

The worker sends the stored payload.

This means a temporary receiving application outage does not immediately destroy the event.

---

# Delivery Success

Any HTTP `2xx` response is considered a successful webhook delivery.

Examples:

```text
200 OK
201 Created
202 Accepted
204 No Content
```

Non-2xx responses are treated as delivery failures.

Network failures and HTTP client exceptions are also treated as delivery failures.

---

# Retry Behavior

Webhook delivery is **at-least-once**.

A failed delivery can be attempted again.

The current worker permits up to three actual delivery attempts.

Conceptually:

```text
Attempt 1
   |
   +-- success → delivered
   |
   +-- failure → retry

Attempt 2
   |
   +-- success → delivered
   |
   +-- failure → retry

Attempt 3
   |
   +-- success → delivered
   |
   +-- failure → failed
```

The gateway stores the number of attempts in the delivery record.

The same event may therefore be received more than once.

Receiving applications must be idempotent.

---

# Signature Per Delivery Attempt

The webhook payload remains the same across retry attempts.

The timestamp and signature are generated when the delivery is attempted.

Therefore:

```text
Same event
Same payload
Different delivery attempt
Different timestamp
Different signature
```

This prevents an old delivery signature from being reused indefinitely.

The receiver should verify each incoming request independently.

---

# Webhook Idempotency

The gateway uses `event_id` as the identity of a webhook event.

A receiving application should persist processed event IDs.

For example:

```text
event_id
---------
evt-123
evt-456
evt-789
```

Before processing:

```text
Has event_id already been processed?

        |
    ┌───┴───┐
    |       |
   YES      NO
    |       |
    v       v
Acknowledge Process
without     event
repeating
operation
```

If the same event is delivered again, the receiver should acknowledge it without repeating the business operation.

---

# STK Payment Flow

```text
Client Application
       |
       | POST /mpesa/stk
       v
BrifNet M-Pesa Gateway
       |
       | STK Push
       v
Customer Phone
       |
       | Customer completes payment
       v
Safaricom Daraja
       |
       | STK Callback
       v
BrifNet M-Pesa Gateway
       |
       | payment.completed
       v
Webhook Delivery
       |
       v
Client Application
```

The initial STK response is not payment confirmation.

The completion event is:

```text
payment.completed
```

---

# C2B Payment Flow

```text
Customer
    |
    | C2B Payment
    v
Safaricom Daraja
    |
    | C2B Callback
    v
BrifNet M-Pesa Gateway
    |
    | Payment persistence
    v
payment.completed
    |
    | Signed webhook
    v
Client Application
```

For C2B:

```text
BillRefNumber → account_number
TransID       → provider_transaction_id
```

---

# Example Client Integration

A client application integrating the gateway normally follows this sequence.

## Step 1 — Register a Webhook

Register:

```http
POST /wp-json/brifnet/v1/webhooks
```

with:

```json
{
  "url": "https://client.example.com/api/webhooks/mpesa",
  "events": [
    "payment.completed"
  ]
}
```

## Step 2 — Configure the Shared Secret

Generate a strong secret.

Store it in WordPress:

```bash
wp option update brifnet_mpesa_webhook_secret "YOUR_SECRET"
```

Store the same secret securely in the receiving application.

## Step 3 — Initiate Payment

```http
POST /wp-json/brifnet/v1/mpesa/stk
Content-Type: application/json
```

```json
{
  "reference": "ORDER-1001",
  "phone": "0729633304",
  "amount": 500
}
```

## Step 4 — Wait for Completion

Do not mark the payment as completed merely because STK initiation returned `200 OK`.

Wait for:

```text
payment.completed
```

## Step 5 — Verify the Webhook

The receiver verifies:

```text
X-BrifNet-Timestamp
X-BrifNet-Signature
```

using the shared secret.

## Step 6 — Process the Event

The receiving application:

1. verifies the signature
2. checks timestamp freshness
3. validates the payload
4. checks `event_id`
5. locates the payment using the appropriate business reference
6. performs the business operation
7. records the event as processed
8. returns a successful HTTP response

---

# Security Considerations

## Provider Callbacks

Daraja callback endpoints must remain reachable by Safaricom.

They are therefore different from authenticated application endpoints.

The plugin should not require normal WordPress login authentication for provider callbacks.

## Client API Authentication

The current client-facing REST endpoints do not yet implement application API-key authentication.

The current `permission_callback` configuration intentionally allows these routes to be reached without WordPress authentication.

This is a known security boundary of the current implementation.

Deployments should therefore consider the API's exposure carefully.

Application-level authentication and authorization are future security work.

## Webhook Authentication

Outgoing webhooks are authenticated using HMAC-SHA256.

The receiver must verify the signature before performing payment-related business operations.

## Secrets

Never commit:

* Daraja consumer keys
* Daraja consumer secrets
* M-Pesa passkeys
* webhook secrets
* production credentials
* private configuration files

Do not place secrets in:

* source code
* README examples
* public JavaScript
* GitHub
* screenshots
* `.env.example`
* webhook payloads
* webhook URLs

---

# Testing

The project uses PHPUnit.

Install dependencies:

```bash
composer install
```

Run the full test suite:

```bash
vendor/bin/phpunit
```

The test suite covers areas including:

* domain behavior
* application use cases
* payment processing
* STK processing
* C2B processing
* provider callbacks
* payment idempotency
* webhook registration
* webhook activation
* webhook deactivation
* webhook event generation
* webhook payloads
* webhook signatures
* webhook delivery
* webhook retries
* infrastructure
* WordPress integration
* request validation

Before committing changes:

```bash
vendor/bin/phpunit
```

All tests should pass.

---

# Project Structure

```text
brifnet-mpesa-gateway/

│
├── src/
│   ├── Api/
│   ├── Application/
│   ├── Domain/
│   ├── Infrastructure/
│   └── WordPress/
│
├── tests/
│
├── brifnet-mpesa-gateway.php
├── composer.json
├── composer.lock
├── phpunit.xml
├── .env.example
├── .gitignore
├── README.md
└── DEVELOPER_GUIDE.md
```

The application is separated into logical layers.

### Domain

Contains business concepts and rules such as:

* payments
* payment events
* webhook endpoints
* webhook deliveries
* webhook signatures
* repository contracts

### Application

Contains application workflows and use cases such as:

* initiating payments
* processing callbacks
* processing C2B payments
* creating payment events
* queueing webhook deliveries
* executing webhook delivery

### Infrastructure

Contains implementations that interact with external systems or persistence:

* WordPress database access
* payment repositories
* webhook repositories
* HTTP clients
* WordPress-specific persistence

### API

Contains REST-facing request handling, validation and response behavior.

### WordPress

Contains WordPress-specific integration and plugin bootstrap behavior.

The architecture keeps core application behavior separated from WordPress framework concerns where practical.

---

# API Summary

## Client-Facing API

| Method | Endpoint                                  | Purpose            |
| ------ | ----------------------------------------- | ------------------ |
| `POST` | `/wp-json/brifnet/v1/mpesa/stk`           | Initiate STK Push  |
| `POST` | `/wp-json/brifnet/v1/webhooks`            | Register webhook   |
| `POST` | `/wp-json/brifnet/v1/webhooks/activate`   | Activate webhook   |
| `POST` | `/wp-json/brifnet/v1/webhooks/deactivate` | Deactivate webhook |

## Provider Callback API

These endpoints are used by Safaricom Daraja.

| Method | Endpoint                             | Purpose              |
| ------ | ------------------------------------ | -------------------- |
| `POST` | `/wp-json/brifnet/v1/mpesa/callback` | Receive STK callback |
| `POST` | `/wp-json/brifnet/v1/mpesa/c2b`      | Receive C2B callback |

## Supported Events

```text
payment.completed
```

## Planned Events

The following events are not currently implemented:

```text
payment.failed
payment.cancelled
payment.reversed
```

Client applications should only subscribe to currently supported events.

---

# Design Principles

The gateway follows several important principles.

### Payment initiation is not payment completion

An accepted STK request is not proof of payment.

### Provider identifiers remain provider identifiers

Safaricom references such as `TransID` and STK request identifiers are not silently treated as client business references.

### Client references belong to the client

For STK payments, the client application's `reference` is retained for correlation.

### Webhooks are asynchronous

The receiving application should not depend on the HTTP response from the original STK request to determine whether payment succeeded.

### Delivery is at-least-once

Receivers must support duplicate events.

### Events are persisted

The gateway creates persistent delivery records instead of relying exclusively on a single synchronous HTTP request.

### Signatures protect webhook authenticity

The receiving application must verify the HMAC signature before trusting the event.

---

# Further Documentation

For implementation details, architecture, domain behavior, persistence decisions, webhook internals and extending the gateway, see:

```text
DEVELOPER_GUIDE.md
```

---

# Current Scope

The current implementation provides:

* STK Push
* STK callback processing
* C2B callback processing
* Payment persistence
* Payment idempotency
* `payment.completed`
* Webhook registration
* Webhook activation/deactivation
* Persistent webhook deliveries
* HMAC-SHA256 webhook signatures
* Timestamped webhook requests
* Retry handling
* At-least-once delivery
* Event-level idempotency

The following are outside the current implemented scope:

* API-key authentication for client-facing API routes
* automatic Daraja callback registration
* additional payment events
* webhook management UI
* advanced delivery scheduling/backoff
* webhook delivery dashboards
* automatic webhook secret rotation

---

# License

See the repository license for the applicable terms.
