![Vatly for Laravel](art/banner.png)

# Vatly Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vatly/vatly-laravel.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-laravel)
[![Tests](https://github.com/Vatly/vatly-laravel/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Vatly/vatly-laravel/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/vatly/vatly-laravel.svg?style=flat-square)](https://packagist.org/packages/vatly/vatly-laravel)

> **Alpha — under active development. Expect breaking changes between minor versions.**

A Cashier-style integration for [Vatly](https://vatly.com) in your Laravel application. Drop a `Billable` trait on your User model and you get subscriptions, checkouts, customer management, hosted billing update links, and a fully wired webhook endpoint — built around Eloquent and Laravel's IoC, events, and routing.

If you've used a Cashier-style billing package, this will feel familiar. Vatly handles Merchant of Record billing for EU SaaS, so you get a similar developer experience without managing VAT, invoicing, or payment compliance yourself.

## Documentation

Full docs at [docs.vatly.com](https://docs.vatly.com). In this repo:

- [Getting started](docs/README.md)
- [Configuration](docs/configuration.md)
- [Customers](docs/Customers.md)
- [Checkouts](docs/Checkouts.md)
- [Subscriptions](docs/Subscriptions.md)
- [Orders](docs/Orders.md)
- [Webhooks](docs/Webhooks.md)
- [How Vatly compares](docs/Comparison.md) — Vatly vs. other Laravel billing packages, for greenfield apps
- [Migrating from Cashier](docs/Migrating-to-Vatly.md) — adding Vatly next to an existing Merchant of Record, side by side

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- A Vatly API key ([vatly.com](https://vatly.com))

## Installation

```bash
composer require vatly/vatly-laravel:v0.7.0-alpha.17
```

Pin to an exact version during alpha — the API will change.

## Setup

1. **Publish the config:**

   ```bash
   php artisan vendor:publish --tag=vatly-config
   ```

2. **Add credentials to `.env`:**

   ```env
   VATLY_KEY=test_xxxxxxxxxxxx
   VATLY_WEBHOOK_SECRET=your-webhook-secret
   VATLY_REDIRECT_URL_SUCCESS=https://your-app.test/checkout/success
   VATLY_REDIRECT_URL_CANCELED=https://your-app.test/checkout/canceled
   ```

   See [`.env.example`](.env.example) for a ready-to-copy stub and [docs/configuration.md](docs/configuration.md) for the full list. Testmode is inferred from the key prefix (`test_` vs `live_`) — no extra config needed.

3. **Publish and run migrations:**

   ```bash
   php artisan vendor:publish --tag=vatly-migrations
   php artisan vendor:publish --tag=vatly-billable-migrations
   php artisan migrate
   ```

   This adds a `vatly_id` column to your users table plus `vatly_subscriptions`, `vatly_orders`, `vatly_order_lines`, `vatly_refunds`, `vatly_chargebacks`, and `vatly_webhook_calls` tables.

4. **Add the `Billable` trait to your User model:**

   ```php
   use Vatly\Laravel\Billable;

   class User extends Authenticatable
   {
       use Billable;
   }
   ```

   Already running another billing provider and migrating gradually? Reach for the `VatlyBillable` trait instead — it exposes the same surface under `vatly*`-prefixed names, so Vatly sits beside your existing biller without a trait collision. See [Migrating from Cashier](docs/Migrating-to-Vatly.md).

## Usage

```php
// Vatly ids are prefixed — subscription plans are `subscription_plan_…`,
// one-off products `one_off_product_…`. Find them in the Vatly dashboard
// or via `GET /subscription-plans`.

// Start a subscription checkout
$checkout = $user->subscribe()
    ->toPlan('subscription_plan_7Hd9Kf2Lm')
    ->create();

return redirect($checkout->links->checkoutUrl->href);

// Start it with a free trial — billing begins after the trial elapses.
// Either set whole days…
$user->subscribe()
    ->toPlan('subscription_plan_7Hd9Kf2Lm')
    ->withTrialDays(14)
    ->create();

// …or an end date (rounded up to whole days, so the trial never ends early):
$user->subscribe()
    ->toPlan('subscription_plan_7Hd9Kf2Lm')
    ->withTrialEndsAt(now()->addMonth())
    ->create();

// One-off checkouts with explicit items
// Each item id is a Vatly product: `one_off_product_…` for a one-off product
// or `subscription_plan_…` for a subscription plan.
$checkout = $user->checkout()->create(
    items: [['id' => 'one_off_product_3Qb8Wz1Yt', 'quantity' => 1]],
    redirectUrlSuccess: 'https://example.com/success',
    redirectUrlCanceled: 'https://example.com/canceled',
);

// Guest checkout: put {CHECKOUT_ID} in the return URL — Vatly fills it in,
// and claimVatlyCustomerFromReturn() links the purchase on the way back.
$user->checkout()->create(
    items: [['id' => 'one_off_product_3Qb8Wz1Yt', 'quantity' => 1]],
    redirectUrlSuccess: route('vatly.return').'?checkout_id={CHECKOUT_ID}',
    redirectUrlCanceled: 'https://example.com/billing',
);
// …on the return route (multi-tab safe; no session/cookie plumbing):
$request->user()->claimVatlyCustomerFromReturn($request);   // see docs/Customers.md

// Subscription state — Cashier-shape predicates
$user->subscribed();                                    // bool, default type
$user->subscribed('team');                              // bool, custom type
$user->subscription()->active();
$user->subscription()->onGracePeriod();
$user->subscription()->canceled();
$user->subscription()->valid();
$user->subscription()->ended();

// Subscription operations
$user->subscription()->swap('subscription_plan_7Hd9Kf2Lm');
$user->subscription()->cancel();                        // Vatly decides immediate vs grace
$user->subscription()->resume();                        // while in grace period
$user->subscription()->updateBilling();                 // signed link for hosted update flow

// Orders — Cashier-style iteration works on the Eloquent collection too
foreach ($user->orders as $order) {
    echo $order->invoiceUrl();                          // hosted invoice URL
}

// Or explicit lookup
$invoiceUrl = $user->order('order_abc')->invoiceUrl();

// Order lines & a subscription's renewal orders
$order->lines;                                          // line items recorded for this order
$subscription->orders;                                  // initial + renewal orders this subscription generated

// Refunds & chargebacks — owned-by-customer and per-order relations
$user->refunds;                                         // all refunds for this customer
$user->chargebacks;                                     // all chargebacks (disputes) for this customer
$order->refunds;                                        // refunds against this order
$order->chargebacks;                                    // chargebacks against this order

// Reversal progress — read live from the Vatly API (status stays `paid`).
// Combines refunds and chargebacks: "did money come back, and how much".
$order->isReversed();                                   // any subtotal reversed?
$order->isPartiallyReversed();                          // reversed, but not in full
$order->isFullyReversed();                              // full subtotal reversed
$order->reversedSubtotal();                             // reversed subtotal, in cents
$order->refundableSubtotal();                           // still-reversible subtotal, in cents

// Static finders
$user = User::findBillable('customer_xyz');             // ?User
$user = User::findBillableOrFail('customer_xyz');       // User

// Test vs live — each local record carries the mode it was created in,
// stored in a `testmode` column and read back via the cast property /
// `isTestmode()`. Use it to segregate test data and pick the matching API key.
$order->testmode;                                       // bool (cast)
$order->isTestmode();                                   // also on Subscription / Refund / Chargeback
```

See [docs/Subscriptions.md](docs/Subscriptions.md) and [docs/Checkouts.md](docs/Checkouts.md) for the full surface.

## Webhooks

The package registers `POST /webhooks/vatly` automatically. Set this URL and your `VATLY_WEBHOOK_SECRET` in the Vatly dashboard, and subscriptions/orders sync to your database automatically.

Vatly events are dispatched on Laravel's event bus — register listeners the usual way:

```php
use Vatly\API\Webhooks\Events\OrderPaid;

Event::listen(OrderPaid::class, function (OrderPaid $event) {
    $event->total->toCents();   // 9900 — minor units
    $event->total->currency;    // "EUR"

    // send receipt, etc.
});
```

The webhook event DTOs live in `vatly-api-php` (alongside the rest of the API
contracts) under the `Vatly\API\Webhooks\Events\` namespace, so a webhook field
change is a single api-php release that flows through every integration. The
exceptions are `SubscriptionWasCreatedFromWebhook` and `OrderWasCreatedFromWebhook`,
which are internal fluent signals (not webhook payloads) and stay under
`Vatly\Fluent\Events\`.

Events available:

- `Vatly\API\Webhooks\Events\OrderPaid` — carries `total`, `subtotal` (both `Vatly\API\Types\Money`), `taxSummary` (full per-rate breakdown), `invoiceNumber`, `paymentMethod`. Read the currency via `$event->total->currency` and minor units via `$event->total->toCents()` (there's no standalone `currency` field). Materialize local invoices without an extra API call.
- `Vatly\API\Webhooks\Events\OrderCanceled` — the local order's status is mirrored to `canceled`.
- `Vatly\API\Webhooks\Events\OrderChargebackReceived` / `OrderChargebackReversed` — dispute signals carrying the affected `orderId`, enriched with `customerId`, dispute `status`, totals and `taxSummary`; persisted to `vatly_chargebacks` (see below). Also react to suspend/reinstate access — a chargeback never mutates the local order row.
- `Vatly\API\Webhooks\Events\OrderPaymentFailed` — same enriched order shape as `OrderPaid`; typically the start of dunning.
- `Vatly\API\Webhooks\Events\CheckoutPaid` / `CheckoutFailed` / `CheckoutCanceled` / `CheckoutExpired` — hosted-checkout lifecycle signals carrying `checkoutId`, nullable `customerId` / `orderId`, `status` and `metadata`. Dispatched straight from the payload (no enrichment GET, no local row); `CheckoutPaid` fires before `OrderPaid` so you can drive in-app receipt/analytics UI, while the others feed retry / cart-abandonment flows.
- `Vatly\API\Webhooks\Events\RefundCompleted` / `RefundFailed` / `RefundCanceled` — each with full `taxSummary`; persisted to `vatly_refunds` (see below).
- `Vatly\API\Webhooks\Events\SubscriptionStarted`
- `Vatly\API\Webhooks\Events\SubscriptionBillingUpdated` — the stored mandate (`mandate_method` / `mandate_masked_identifier`) is refreshed.
- `Vatly\API\Webhooks\Events\SubscriptionUpdated` — an **immediate** plan/price/interval/quantity change; the local subscription's `plan_id`, `name` and `quantity` are refreshed from the payload (price is not stored locally).
- `Vatly\API\Webhooks\Events\SubscriptionUpdateScheduled` — a change **scheduled** for the next billing cycle; the local row is left unchanged (the change hasn't applied yet). Dispatched only, exposing the target values via a typed `scheduledUpdate` (`Vatly\API\Types\ScheduledSubscriptionUpdate`) so you can, e.g., warn the customer of an upcoming price change.
- `Vatly\API\Webhooks\Events\SubscriptionResumed` — the stored end date is cleared.
- `Vatly\API\Webhooks\Events\SubscriptionCanceledImmediately`
- `Vatly\API\Webhooks\Events\SubscriptionCanceledWithGracePeriod`
- `Vatly\API\Webhooks\Events\SubscriptionCancellationGracePeriodCompleted` — the grace period set at cancellation has elapsed; carries `customerId`, `subscriptionId`, `endsAt`. The local subscription's `ends_at` is stamped to the actual end (self-healing a missed `subscription.canceled_with_grace_period` webhook and correcting any drift); also dispatched so you can flip your own application-level "fully ended" state without polling.
- `Vatly\API\Webhooks\Events\WebhookSetupReceived` — a `webhook.setup` endpoint-verification call; no resource to enrich and no local row to touch, just acknowledge with a `2xx`.
- `Vatly\API\Webhooks\Events\UnsupportedWebhookReceived`
- `Vatly\Fluent\Events\SubscriptionWasCreatedFromWebhook` — internal fluent signal (not a webhook payload).
- `Vatly\Fluent\Events\OrderWasCreatedFromWebhook` — the order analogue of `SubscriptionWasCreatedFromWebhook`: an internal fluent signal that fires once when a brand-new local `Order` row is created from an `order.paid` webhook. A clean hook for receipts / fulfillment.

Refund webhooks (`refund.completed` / `refund.failed` / `refund.canceled`) are persisted to the `vatly_refunds` table via the bundled `Refund` model and `EloquentRefundRepository`. Chargeback webhooks (`order.chargeback_received` / `order.chargeback_reversed`) are persisted the same way to the `vatly_chargebacks` table via the bundled `Chargeback` model and `EloquentChargebackRepository` — Vatly's public order status doesn't change on a chargeback, so also wire your own listener if you need to suspend/reinstate access.

The webhook route is named `vatly.webhook` — reach it with `route('vatly.webhook')`.

See [docs/Webhooks.md](docs/Webhooks.md) for signature verification, retries, and customising reactions.

## Testing

```bash
composer test
```

### Faking Vatly in your app's tests

For feature tests that drive checkout/subscription flows, call `Vatly::fake()` — it binds a `FakeVatly` into the container, so every `subscribe()` / `checkout()` / `subscription()` call routes through recording fakes instead of the real API. Script only what you care about and assert against the returned fake (in the spirit of `Notification::fake()`), no hand-rolled Mockery stubs:

```php
use Vatly\Fluent\Testing\FakeCheckout;
use Vatly\Laravel\Facades\Vatly;

$vatly = Vatly::fake();

// Optional: script the Checkout returned on subscription create
$vatly->onSubscriptionCreate(
    fn (string $planId) => FakeCheckout::make('https://checkout.vatly.test/chk_1'),
);

$this->actingAs($user)
    ->post('/billing/subscribe', ['plan' => 'plan_pro'])
    ->assertRedirect('https://checkout.vatly.test/chk_1');

$vatly->assertSubscriptionCreated('plan_pro');
$vatly->assertNothingCanceled();
```

Available assertions: `assertSubscriptionCreated`, `assertCheckoutCreated`, `assertSubscriptionSwapped`, `assertSubscriptionCanceled`, `assertNothingCanceled`, `assertNothingCreated`.

`Vatly::fake()` lives on the `Vatly\Laravel\Facades\Vatly` facade — the package's single static-helper surface. The same facade proxies the composition root (`Vatly::order($order)`, `Vatly::subscription($subscription)`, …) and exposes the host-side helpers `Vatly::findBillable($vatlyCustomerId)`, `Vatly::findBillableOrFail($vatlyCustomerId)`, and `Vatly::cleanUp()`.

### Faking the webhook API fetch

For the `order.paid` webhook flow, the package fetches the full Order from the Vatly API to populate the tax breakdown. The actions are encapsulated by the `Vatly` composition root (not individually bound in the container), so swap one via reflection on the singleton:

```php
use Mockery;
use ReflectionClass;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Webhooks\WebhookProcessor;

$action = Mockery::mock(GetOrder::class);
$action->shouldReceive('execute')->andReturn($yourFakeApiOrder);

$vatly = $this->app->make(Vatly::class);
$ref = (new ReflectionClass($vatly))->getProperty('getOrder');
$ref->setAccessible(true);
$ref->setValue($vatly, $action);

// Clear downstream caches that captured the previous action
foreach (['webhookEventFactory', 'webhookProcessor'] as $prop) {
    $r = (new ReflectionClass($vatly))->getProperty($prop);
    $r->setAccessible(true);
    $r->setValue($vatly, null);
}

$this->app->forgetInstance(WebhookProcessor::class);
```

See [`tests/Http/Controllers/VatlyInboundWebhookControllerTest.php`](tests/Http/Controllers/VatlyInboundWebhookControllerTest.php) for the helper used in this package's own tests.

## Under the hood

The ecosystem splits into three layers:

- [`vatly/vatly-api-php`](https://github.com/Vatly/vatly-api-php) owns the API client and every wire contract — REST resources, value types (`Money`, `TaxSummaryCollection`), the `Vatly\API\Types\OrderLineData` DTO, and the webhook event DTOs under `Vatly\API\Webhooks\Events\`. A webhook-payload change is a single api-php release.
- [`vatly/vatly-fluent-php`](https://github.com/Vatly/vatly-fluent-php) is the framework-agnostic core: the contracts, composition root (`Vatly`), webhook pipeline (factory / processor / reactions that consume the api-php event DTOs), and the operation wrappers (`Vatly\Fluent\SubscriptionHandle`, `Vatly\Fluent\OrderHandle`).
- This package is the thin Laravel driver on top of fluent. It supplies:

- Concrete Eloquent-backed impls of fluent's contracts (subscription / order / webhook-call repositories, customer-binding repository, models, config reader, event dispatcher)
- The `Billable` trait with Cashier-style shortcuts and static finders
- The HTTP route and controller for inbound webhooks
- Publishable migrations and configuration

The driver bindings live in `VatlyServiceProvider`: each fluent contract is bound to its Eloquent / Laravel impl, then `Vatly::class` is registered as a singleton built from a `Vatly\Fluent\Wiring` DTO. The new `CustomerBindingRepository` contract replaces the old `CustomerRepositoryInterface` — fluent never touches the host model directly; it only consults the binding repo for host-id ↔ vatly-id lookups. Every other fluent service (`Customers` helper, `WebhookProcessor`, actions, operation wrappers) resolves lazily through the singleton.

## License

MIT
