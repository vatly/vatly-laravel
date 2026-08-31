<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Vatly\API\Types\Mandate;
use Vatly\API\Webhooks\Events\CheckoutCanceled;
use Vatly\API\Webhooks\Events\CheckoutExpired;
use Vatly\API\Webhooks\Events\CheckoutFailed;
use Vatly\API\Webhooks\Events\CheckoutPaid;
use Vatly\API\Webhooks\Events\OrderChargebackReceived;
use Vatly\API\Webhooks\Events\OrderChargebackReversed;
use Vatly\API\Webhooks\Events\OrderPaymentFailed;
use Vatly\API\Webhooks\Events\RefundCanceled;
use Vatly\API\Webhooks\Events\RefundCompleted;
use Vatly\API\Webhooks\Events\RefundFailed;
use Vatly\API\Webhooks\Events\SubscriptionCancellationGracePeriodCompleted;
use Vatly\API\Webhooks\Events\UnsupportedWebhookReceived;
use Vatly\API\Webhooks\Events\WebhookSetupReceived;
use Vatly\Fluent\Events\OrderWasCreatedFromWebhook;
use Vatly\Laravel\Models\Chargeback;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Models\Refund;
use Vatly\Laravel\Models\Subscription;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\Tests\TestHelpers\PostsVatlyWebhooks;

class VatlyInboundWebhookControllerTest extends BaseTestCase
{
    use PostsVatlyWebhooks;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureWebhookSecret();
    }

    public function test_it_returns_201_for_a_valid_signed_webhook(): void
    {
        User::factory()->create(['vatly_id' => 'customer_foo']);

        $response = $this->postSubscriptionWebhook('subscription.started', $this->buildApiSubscription([
            'id' => 'sub_123',
            'customerId' => 'customer_foo',
            'subscriptionPlanId' => 'plan_foo',
            'name' => 'Test Plan',
            'quantity' => 1,
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseCount('vatly_webhook_calls', 1);
    }

    public function test_it_handles_unknown_webhook_events(): void
    {
        $response = $this->postWebhookEvent('unknown.event.type', 'res_123', 'unknown', ['foo' => 'bar']);

        $response->assertStatus(201);
        $this->assertDatabaseCount('vatly_webhook_calls', 1);
    }

    public function test_it_returns_403_for_an_invalid_signature(): void
    {
        $payload = $this->makeWebhookPayload('subscription.started', 'sub_123', 'subscription');

        $response = $this->call(
            'POST',
            'webhooks/vatly',
            server: ['HTTP_VATLY_SIGNATURE' => 't='.time().',v1=deadbeef', 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('vatly_webhook_calls', 0);
    }

    public function test_it_returns_403_for_a_missing_signature(): void
    {
        $payload = $this->makeWebhookPayload('subscription.started', 'sub_123', 'subscription');

        $response = $this->call(
            'POST',
            'webhooks/vatly',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $response->assertStatus(403);
    }

    public function test_it_returns_403_for_a_stale_timestamp(): void
    {
        $payload = $this->makeWebhookPayload('subscription.started', 'sub_123', 'subscription');
        $staleTimestamp = time() - 3600;
        $signature = hash_hmac('sha256', $staleTimestamp.'.'.$payload, $this->webhookSecret);

        $response = $this->call(
            'POST',
            'webhooks/vatly',
            server: ['HTTP_VATLY_SIGNATURE' => "t={$staleTimestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('vatly_webhook_calls', 0);
    }

    public function test_it_creates_a_subscription_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postSubscriptionWebhook('subscription.started', $this->buildApiSubscription([
            'id' => 'sub_999',
            'customerId' => 'customer_abc',
            'subscriptionPlanId' => 'plan_premium',
            'name' => 'Premium Plan',
            'quantity' => 1,
            'mandate' => new Mandate('card', '4242'),
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_subscriptions', [
            'vatly_id' => 'sub_999',
            'plan_id' => 'plan_premium',
            'name' => 'Premium Plan',
            'owner_id' => $user->id,
            'mandate_method' => 'card',
            'mandate_masked_identifier' => '4242',
        ]);
    }

    public function test_it_creates_an_order_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postOrderWebhook('order.paid', $this->buildApiOrder([
            'id' => 'order_abc123',
            'customerId' => 'customer_abc',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'invoiceNumber' => 'INV-001',
            'paymentMethod' => 'card',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_orders', [
            'vatly_id' => 'order_abc123',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'owner_id' => $user->id,
        ]);
    }

    public function test_it_persists_tax_breakdown_when_creating_an_order_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postOrderWebhook('order.paid', $this->buildApiOrder([
            'id' => 'order_tax_1',
            'customerId' => 'customer_abc',
            'totalValue' => '49.99',
            'subtotalValue' => '41.31',
            'currency' => 'USD',
            'invoiceNumber' => null,
            'paymentMethod' => null,
            'taxRates' => [
                ['name' => 'Sales Tax', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '8.68'],
            ],
        ]));

        $response->assertStatus(201);

        $order = Order::where('vatly_id', 'order_tax_1')->firstOrFail();
        $this->assertSame(4131, $order->subtotal);
        $this->assertSame('Sales Tax', $order->tax_summary[0]['taxRate']['name']);
        // JSON has no int/float distinction, so a whole-number percentage decodes
        // back as an int — assert by value, not type.
        $this->assertEquals(21.0, $order->tax_summary[0]['taxRate']['percentage']);
        $this->assertEquals(100.0, $order->tax_summary[0]['taxRate']['taxablePercentage']);
        $this->assertSame(868, $order->tax_summary[0]['amount']);
        $this->assertSame('USD', $order->tax_summary[0]['currency']);
        $this->assertSame($user->id, $order->owner_id);
    }

    public function test_it_persists_order_lines_when_creating_an_order_from_webhook(): void
    {
        User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postOrderWebhook('order.paid', $this->buildApiOrder([
            'id' => 'order_lines_wh',
            'customerId' => 'customer_abc',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'invoiceNumber' => 'INV-010',
            'paymentMethod' => 'card',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
            'lines' => [
                [
                    'id' => 'order_item_wh_1',
                    'description' => 'Pro plan — monthly',
                    'quantity' => 1,
                    'basePriceValue' => '99.00',
                    'totalValue' => '99.00',
                    'subtotalValue' => '81.82',
                    'productType' => 'subscription',
                    'productId' => 'subscription_wh',
                ],
            ],
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_order_lines', [
            'vatly_id' => 'order_item_wh_1',
            'order_vatly_id' => 'order_lines_wh',
            'quantity' => 1,
            'base_price' => 9900,
            'total' => 9900,
            'subtotal' => 8182,
            'product_type' => 'subscription',
            'product_id' => 'subscription_wh',
        ]);
    }

    public function test_it_stores_an_order_when_a_payment_fails_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $apiOrder = $this->buildApiOrder([
            'id' => 'order_failed_1',
            'customerId' => 'customer_abc',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'invoiceNumber' => 'INV-009',
            'paymentMethod' => 'card',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]);
        // The reaction mirrors the upstream status verbatim — not a synthetic "failed".
        $apiOrder->status = 'pending';

        $response = $this->postOrderWebhook('order.payment_failed', $apiOrder);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_orders', [
            'vatly_id' => 'order_failed_1',
            'status' => 'pending',
            'total' => 9900,
            'currency' => 'EUR',
            'owner_id' => $user->id,
        ]);
    }

    public function test_it_dispatches_the_payment_failed_event_from_webhook(): void
    {
        Event::fake([OrderPaymentFailed::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $apiOrder = $this->buildApiOrder([
            'id' => 'order_failed_2',
            'customerId' => 'customer_abc',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'invoiceNumber' => null,
            'paymentMethod' => null,
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]);
        $apiOrder->status = 'pending';

        $this->postOrderWebhook('order.payment_failed', $apiOrder)->assertStatus(201);

        Event::assertDispatched(
            OrderPaymentFailed::class,
            fn (OrderPaymentFailed $event): bool => $event->orderId === 'order_failed_2'
                && $event->customerId === 'customer_abc',
        );
    }

    public function test_it_dispatches_the_order_was_created_from_webhook_event(): void
    {
        // The order analogue of SubscriptionWasCreatedFromWebhook: a driver-side
        // signal that fires once, from the order-sync reaction, when a brand-new
        // local Order row is created from an order.paid webhook.
        Event::fake([OrderWasCreatedFromWebhook::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postOrderWebhook('order.paid', $this->buildApiOrder([
            'id' => 'order_created_evt',
            'customerId' => 'customer_abc',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'invoiceNumber' => 'INV-100',
            'paymentMethod' => 'card',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            OrderWasCreatedFromWebhook::class,
            fn (OrderWasCreatedFromWebhook $event): bool => $event->order->getVatlyId() === 'order_created_evt',
        );
    }

    public function test_it_dispatches_the_webhook_setup_received_event(): void
    {
        Event::fake([WebhookSetupReceived::class]);

        $this->postWebhookEvent('webhook.setup', 'webhook_endpoint_1', 'webhook', [
            'url' => 'https://example.test/webhooks/vatly',
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            WebhookSetupReceived::class,
            fn (WebhookSetupReceived $event): bool => $event->entityType === 'webhook'
                && $event->eventName === 'webhook.setup',
        );
    }

    public function test_it_dispatches_the_checkout_paid_event_from_webhook(): void
    {
        Event::fake([CheckoutPaid::class]);

        $this->postWebhookEvent('checkout.paid', 'checkout_abc123', 'checkout', [
            'customerId' => 'customer_abc',
            'orderId' => 'order_abc123',
            'status' => 'paid',
            'metadata' => ['cart' => 'cart_1'],
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            CheckoutPaid::class,
            fn (CheckoutPaid $event): bool => $event->checkoutId === 'checkout_abc123'
                && $event->customerId === 'customer_abc'
                && $event->orderId === 'order_abc123'
                && $event->status === 'paid'
                && $event->metadata === ['cart' => 'cart_1'],
        );
    }

    public function test_it_dispatches_the_checkout_failed_event_from_webhook(): void
    {
        Event::fake([CheckoutFailed::class]);

        $this->postWebhookEvent('checkout.failed', 'checkout_failed_1', 'checkout', [
            'customerId' => 'customer_abc',
            'orderId' => null,
            'status' => 'failed',
            'metadata' => ['cart' => 'cart_2'],
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            CheckoutFailed::class,
            fn (CheckoutFailed $event): bool => $event->checkoutId === 'checkout_failed_1'
                && $event->customerId === 'customer_abc'
                && $event->orderId === null
                && $event->status === 'failed'
                && $event->metadata === ['cart' => 'cart_2'],
        );
    }

    public function test_it_dispatches_the_checkout_canceled_event_from_webhook(): void
    {
        Event::fake([CheckoutCanceled::class]);

        $this->postWebhookEvent('checkout.canceled', 'checkout_canceled_1', 'checkout', [
            'customerId' => 'customer_abc',
            'orderId' => null,
            'status' => 'canceled',
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            CheckoutCanceled::class,
            fn (CheckoutCanceled $event): bool => $event->checkoutId === 'checkout_canceled_1'
                && $event->customerId === 'customer_abc'
                && $event->orderId === null
                && $event->status === 'canceled',
        );
    }

    public function test_it_dispatches_the_checkout_expired_event_from_webhook(): void
    {
        Event::fake([CheckoutExpired::class]);

        $this->postWebhookEvent('checkout.expired', 'checkout_expired_1', 'checkout', [
            'customerId' => null,
            'orderId' => null,
            'status' => 'expired',
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            CheckoutExpired::class,
            fn (CheckoutExpired $event): bool => $event->checkoutId === 'checkout_expired_1'
                && $event->customerId === null
                && $event->orderId === null
                && $event->status === 'expired',
        );
    }

    public function test_it_dispatches_the_grace_period_completed_event_from_webhook(): void
    {
        Event::fake([SubscriptionCancellationGracePeriodCompleted::class]);

        $this->postWebhookEvent('subscription.cancellation_grace_period_completed', 'sub_grace', 'subscription', [
            'customerId' => 'customer_abc',
            'endedAt' => '2026-01-01T00:00:00+00:00',
        ])->assertStatus(201);

        $this->assertDatabaseCount('vatly_webhook_calls', 1);

        Event::assertDispatched(
            SubscriptionCancellationGracePeriodCompleted::class,
            fn (SubscriptionCancellationGracePeriodCompleted $event): bool => $event->subscriptionId === 'sub_grace'
                && $event->customerId === 'customer_abc'
                && $event->endsAt->format('Y-m-d') === '2026-01-01',
        );
    }

    public function test_it_stamps_ends_at_when_a_grace_period_completes_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        // Simulate a subscription whose `canceled_with_grace_period` webhook was
        // missed: the local row never got an `ends_at`, so it still looks active.
        Subscription::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'sub_grace',
            'plan_id' => 'plan_foo',
            'name' => 'Test Plan',
            'type' => 'default',
            'quantity' => 1,
            'ends_at' => null,
            'testmode' => true,
        ]);

        $response = $this->postWebhookEvent('subscription.cancellation_grace_period_completed', 'sub_grace', 'subscription', [
            'customerId' => 'customer_abc',
            'endedAt' => '2026-01-01T00:00:00+00:00',
        ]);

        $response->assertStatus(201);

        // The EndSubscriptionOnGracePeriodCompleted reaction self-heals the row:
        // ends_at is stamped to the actual end and the derived state flips to ended.
        $subscription = Subscription::where('vatly_id', 'sub_grace')->firstOrFail();
        $this->assertNotNull($subscription->ends_at);
        $this->assertSame('2026-01-01', $subscription->ends_at->format('Y-m-d'));
        $this->assertTrue($subscription->isEnded());
        $this->assertFalse($subscription->isActive());
    }

    public function test_it_corrects_a_drifted_end_date_when_a_grace_period_completes_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        // The cancellation stamped a *scheduled* end date; the grace period then
        // ended on a different *actual* date upstream (e.g. it was shortened).
        Subscription::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'sub_drift',
            'plan_id' => 'plan_foo',
            'name' => 'Test Plan',
            'type' => 'default',
            'quantity' => 1,
            'ends_at' => '2026-02-01T00:00:00+00:00',
            'testmode' => true,
        ]);

        $response = $this->postWebhookEvent('subscription.cancellation_grace_period_completed', 'sub_drift', 'subscription', [
            'customerId' => 'customer_abc',
            'endedAt' => '2026-01-15T00:00:00+00:00',
        ]);

        $response->assertStatus(201);

        // The reaction overwrites the scheduled end with the authoritative actual end.
        $subscription = Subscription::where('vatly_id', 'sub_drift')->firstOrFail();
        $this->assertSame('2026-01-15', $subscription->ends_at->format('Y-m-d'));
        $this->assertTrue($subscription->isEnded());
    }

    public function test_it_cancels_a_subscription_immediately_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        // First create the subscription
        Subscription::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'sub_cancel',
            'plan_id' => 'plan_foo',
            'name' => 'Test Plan',
            'type' => 'default',
            'quantity' => 1,
            'testmode' => true,
        ]);

        $response = $this->postWebhookEvent('subscription.canceled_immediately', 'sub_cancel', 'subscription', [
            'customerId' => 'customer_abc',
        ]);

        $response->assertStatus(201);
        $subscription = Subscription::where('vatly_id', 'sub_cancel')->first();
        $this->assertTrue($subscription->isCancelled());
    }

    public function test_it_persists_a_refund_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postRefundWebhook('refund.completed', $this->buildApiRefund([
            'id' => 'refund_abc123',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'refunded',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_refunds', [
            'vatly_id' => 'refund_abc123',
            'original_order_id' => 'order_abc123',
            'status' => 'refunded',
            'total' => 9900,
            'currency' => 'EUR',
            'owner_id' => $user->id,
        ]);

        $refund = Refund::where('vatly_id', 'refund_abc123')->firstOrFail();
        $this->assertSame(8182, $refund->subtotal);
        $this->assertSame(1718, $refund->tax_summary[0]['amount']);
        $this->assertTrue($refund->isCompleted());
    }

    public function test_it_dispatches_the_refund_completed_event_from_webhook(): void
    {
        Event::fake([RefundCompleted::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postRefundWebhook('refund.completed', $this->buildApiRefund([
            'id' => 'refund_evt_1',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'refunded',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            RefundCompleted::class,
            fn (RefundCompleted $event): bool => $event->refundId === 'refund_evt_1'
                && $event->customerId === 'customer_abc'
                && $event->originalOrderId === 'order_abc123'
                && $event->status === 'refunded'
                && $event->total->toCents() === 9900,
        );
    }

    public function test_it_dispatches_the_refund_failed_event_from_webhook(): void
    {
        Event::fake([RefundFailed::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postRefundWebhook('refund.failed', $this->buildApiRefund([
            'id' => 'refund_evt_2',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'failed',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            RefundFailed::class,
            fn (RefundFailed $event): bool => $event->refundId === 'refund_evt_2'
                && $event->customerId === 'customer_abc'
                && $event->originalOrderId === 'order_abc123'
                && $event->status === 'failed',
        );
    }

    public function test_it_dispatches_the_refund_canceled_event_from_webhook(): void
    {
        Event::fake([RefundCanceled::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postRefundWebhook('refund.canceled', $this->buildApiRefund([
            'id' => 'refund_evt_3',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'canceled',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            RefundCanceled::class,
            fn (RefundCanceled $event): bool => $event->refundId === 'refund_evt_3'
                && $event->customerId === 'customer_abc'
                && $event->originalOrderId === 'order_abc123'
                && $event->status === 'canceled',
        );
    }

    public function test_it_dispatches_the_chargeback_received_event_from_webhook(): void
    {
        Event::fake([OrderChargebackReceived::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postChargebackWebhook('order.chargeback_received', $this->buildApiChargeback([
            'id' => 'chargeback_evt_1',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'pending',
            'reason' => 'fraud',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            OrderChargebackReceived::class,
            fn (OrderChargebackReceived $event): bool => $event->chargebackId === 'chargeback_evt_1'
                && $event->orderId === 'order_abc123'
                && $event->originalOrderId === 'order_abc123'
                && $event->customerId === 'customer_abc'
                && $event->status === 'pending'
                && $event->reason === 'fraud',
        );
    }

    public function test_it_dispatches_the_chargeback_reversed_event_from_webhook(): void
    {
        Event::fake([OrderChargebackReversed::class]);

        User::factory()->create(['vatly_id' => 'customer_abc']);

        $this->postChargebackWebhook('order.chargeback_reversed', $this->buildApiChargeback([
            'id' => 'chargeback_evt_2',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'won',
            'reason' => 'fraud',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]))->assertStatus(201);

        Event::assertDispatched(
            OrderChargebackReversed::class,
            fn (OrderChargebackReversed $event): bool => $event->chargebackId === 'chargeback_evt_2'
                && $event->orderId === 'order_abc123'
                && $event->originalOrderId === 'order_abc123'
                && $event->customerId === 'customer_abc'
                && $event->status === 'won',
        );
    }

    public function test_it_persists_a_chargeback_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        $response = $this->postChargebackWebhook('order.chargeback_received', $this->buildApiChargeback([
            'id' => 'chargeback_abc123',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'pending',
            'reason' => 'fraud',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vatly_chargebacks', [
            'vatly_id' => 'chargeback_abc123',
            'original_order_id' => 'order_abc123',
            'status' => 'pending',
            'total' => 9900,
            'currency' => 'EUR',
            'reason' => 'fraud',
            'owner_id' => $user->id,
        ]);

        $chargeback = Chargeback::where('vatly_id', 'chargeback_abc123')->firstOrFail();
        $this->assertSame(8182, $chargeback->subtotal);
        $this->assertSame(1718, $chargeback->tax_summary[0]['amount']);
        $this->assertSame('fraud', $chargeback->getReason());
        $this->assertFalse($chargeback->isReversed());
    }

    public function test_it_updates_a_chargeback_on_reversal_from_webhook(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_abc']);

        Chargeback::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'chargeback_rev1',
            'customer_id' => 'customer_abc',
            'original_order_id' => 'order_abc123',
            'status' => 'pending',
            'total' => 9900,
            'subtotal' => 8182,
            'currency' => 'EUR',
            'reason' => 'fraud',
            'testmode' => true,
        ]);

        $response = $this->postChargebackWebhook('order.chargeback_reversed', $this->buildApiChargeback([
            'id' => 'chargeback_rev1',
            'customerId' => 'customer_abc',
            'originalOrderId' => 'order_abc123',
            'status' => 'won',
            'reason' => 'fraud',
            'totalValue' => '99.00',
            'subtotalValue' => '81.82',
            'currency' => 'EUR',
            'taxRates' => [
                ['name' => 'VAT', 'percentage' => 21.0, 'taxablePercentage' => 100.0, 'amount' => '17.18'],
            ],
        ]));

        $response->assertStatus(201);
        $this->assertDatabaseCount('vatly_chargebacks', 1);

        $chargeback = Chargeback::where('vatly_id', 'chargeback_rev1')->firstOrFail();
        $this->assertSame('won', $chargeback->status);
        $this->assertTrue($chargeback->isReversed());
    }

    /**
     * The catalogue-moderation webhooks (`one_off_product.*` and
     * `subscription_plan.*`, added to the WebhookEvent enum after 2026-07)
     * carry no customer and touch no local table. The wrapper still records
     * every one in `vatly_webhook_calls` and forwards it onto the event bus.
     *
     * They currently surface as {@see UnsupportedWebhookReceived}: they have no
     * typed DTO in vatly-api-php's WebhookEventFactory yet, so the factory's
     * default branch maps them. When api-php ships typed catalogue DTOs this
     * assertion is the canary that flips — swap it for the typed event and add
     * a reaction if one is warranted.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function catalogueEventProvider(): array
    {
        return [
            'one_off_product.update_submitted' => ['one_off_product.update_submitted', 'one_off_product'],
            'one_off_product.update_approved' => ['one_off_product.update_approved', 'one_off_product'],
            'one_off_product.update_rejected' => ['one_off_product.update_rejected', 'one_off_product'],
            'one_off_product.archived' => ['one_off_product.archived', 'one_off_product'],
            'one_off_product.unarchived' => ['one_off_product.unarchived', 'one_off_product'],
            'subscription_plan.update_submitted' => ['subscription_plan.update_submitted', 'subscription_plan'],
            'subscription_plan.update_approved' => ['subscription_plan.update_approved', 'subscription_plan'],
            'subscription_plan.update_rejected' => ['subscription_plan.update_rejected', 'subscription_plan'],
            'subscription_plan.archived' => ['subscription_plan.archived', 'subscription_plan'],
            'subscription_plan.unarchived' => ['subscription_plan.unarchived', 'subscription_plan'],
        ];
    }

    #[DataProvider('catalogueEventProvider')]
    public function test_it_stores_and_dispatches_catalogue_events(string $eventName, string $entityType): void
    {
        Event::fake([UnsupportedWebhookReceived::class]);

        $entityId = $entityType.'_Vr8kQdFhSrG4Y3DnfsdqH';

        $this->postWebhookEvent($eventName, $entityId, $entityType, [
            'id' => $entityId,
            'resource' => $entityType,
            'name' => 'Premium License',
            'pendingUpdates' => ['name' => 'Premium License v2'],
            'updateStatus' => 'pending',
        ])->assertStatus(201);

        $this->assertDatabaseHas('vatly_webhook_calls', [
            'event_name' => $eventName,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'vatly_customer_id' => null,
        ]);

        Event::assertDispatched(
            UnsupportedWebhookReceived::class,
            fn (UnsupportedWebhookReceived $event): bool => $event->eventName === $eventName
                && $event->entityType === $entityType
                && $event->entityId === $entityId,
        );
    }
}
