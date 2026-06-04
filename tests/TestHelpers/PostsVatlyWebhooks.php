<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\TestHelpers;

use Illuminate\Testing\TestResponse;
use Mockery;
use ReflectionClass;
use Vatly\API\Exceptions\ApiException;
use Vatly\API\Resources\Chargeback as ApiChargeback;
use Vatly\API\Resources\Checkout as ApiCheckout;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Resources\Refund as ApiRefund;
use Vatly\API\Resources\ResourceFactory;
use Vatly\API\Resources\Subscription as ApiSubscription;
use Vatly\API\Types\Mandate;
use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetCheckout;
use Vatly\Fluent\Vatly;
use Vatly\Fluent\Webhooks\WebhookProcessor;

/**
 * Test helper for driving the Vatly webhook controller end-to-end.
 *
 * Builds + signs the fat JSON payload Vatly would POST. As of
 * vatly-fluent-php alpha.19 the webhook event factory hydrates the
 * money/tax-bearing events straight from the signed payload (no follow-up
 * `GET`), so the `post*Webhook` helpers serialize a built api-php Resource
 * into the delivery's `object`. The `fakeGetCheckout(s)` helpers remain for
 * the anonymous-checkout *return* flow (`claimVatlyCustomerFromReturn`),
 * which still resolves the checkout through a real fluent `GetCheckout` call.
 *
 * Shared by the controller test and the higher-level flow tests.
 */
trait PostsVatlyWebhooks
{
    protected string $webhookSecret = 'test-webhook-secret';

    protected function configureWebhookSecret(?string $secret = null): void
    {
        if ($secret !== null) {
            $this->webhookSecret = $secret;
        }

        $this->app['config']->set('vatly.webhook_secret', $this->webhookSecret);
        $this->app->forgetInstance(WebhookProcessor::class);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function makeWebhookPayload(string $eventName, string $entityId, string $entityType, array $object = []): string
    {
        return (string) json_encode([
            'id' => 'webhook_event_'.bin2hex(random_bytes(10)),
            'resource' => 'webhook_event',
            'eventName' => $eventName,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'testmode' => true,
            'createdAt' => now()->toIso8601String(),
            'object' => (object) $object,
        ]);
    }

    protected function postSignedWebhook(string $payload): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return $this->call(
            'POST',
            'webhooks/vatly',
            server: ['HTTP_VATLY_SIGNATURE' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
    }

    /**
     * Convenience: build + sign + POST in one go.
     *
     * @param  array<string, mixed>  $object
     */
    protected function postWebhookEvent(string $eventName, string $entityId, string $entityType, array $object = []): TestResponse
    {
        return $this->postSignedWebhook(
            $this->makeWebhookPayload($eventName, $entityId, $entityType, $object),
        );
    }

    /**
     * Replace the cached `GetCheckout` action on the composition root so the
     * anonymous-checkout return flow (`claimVatlyCustomerFromReturn`) doesn't
     * need a real API call. Convenience wrapper around
     * {@see self::fakeGetCheckouts()} for a single checkout.
     */
    protected function fakeGetCheckout(ApiCheckout $checkout): void
    {
        $this->fakeGetCheckouts([$checkout->id => $checkout]);
    }

    /**
     * Replace the cached `GetCheckout` action with a fake that returns a
     * different `ApiCheckout` per checkout id — so a multi-tab scenario can
     * keep several checkout ids in flight at once. An unregistered id throws a
     * 404 `ApiException`, mirroring the real API for an unknown / out-of-scope
     * checkout (which fluent maps to "nothing to claim").
     *
     * @param  array<string, ApiCheckout>  $checkouts  keyed by checkout id
     */
    protected function fakeGetCheckouts(array $checkouts): void
    {
        $action = Mockery::mock(GetCheckout::class);
        $action->shouldReceive('execute')->andReturnUsing(function (string $id) use ($checkouts) {
            if (isset($checkouts[$id])) {
                return $checkouts[$id];
            }

            throw new ApiException("Error 404 executing API call for checkout '{$id}'", 404);
        });

        $this->writeVatlyPrivate($this->app->make(Vatly::class), 'getCheckout', $action);
    }

    /**
     * Build a minimal ApiCheckout for `fakeGetCheckout()` callers.
     *
     * @param  array{id: string, customerId?: ?string, status?: string}  $data
     */
    protected function buildApiCheckout(array $data): ApiCheckout
    {
        $checkout = new ApiCheckout(Mockery::mock(VatlyApiClient::class));
        $checkout->id = $data['id'];
        $checkout->customerId = $data['customerId'] ?? null;
        $checkout->status = $data['status'] ?? 'paid';

        return $checkout;
    }

    /**
     * Build a minimal ApiSubscription for `subscription.*` webhook tests.
     *
     * @param  array{
     *   id: string,
     *   customerId: ?string,
     *   subscriptionPlanId: string,
     *   name: string,
     *   quantity: int,
     *   mandate?: ?Mandate,
     * }  $data
     */
    protected function buildApiSubscription(array $data): ApiSubscription
    {
        $subscription = new ApiSubscription(Mockery::mock(VatlyApiClient::class));
        $subscription->id = $data['id'];
        $subscription->customerId = $data['customerId'] ?? null;
        $subscription->subscriptionPlanId = $data['subscriptionPlanId'];
        $subscription->name = $data['name'];
        $subscription->quantity = $data['quantity'];
        $subscription->mandate = $data['mandate'] ?? null;
        $subscription->testmode = $data['testmode'] ?? true;

        return $subscription;
    }

    private function writeVatlyPrivate(object $target, string $property, mixed $value): void
    {
        $ref = (new ReflectionClass($target))->getProperty($property);
        $ref->setAccessible(true);
        $ref->setValue($target, $value);
    }

    /**
     * Serialize a built api-php Resource back into the API-shaped `object`
     * tree Vatly embeds on a fat webhook delivery.
     *
     * As of vatly-fluent-php alpha.19 the webhook event factory hydrates the
     * money/tax-bearing events straight from `$webhook->object` (no follow-up
     * `GET`), so the posted payload must carry the full resource — byte-shaped
     * like a `GET /…/{id}` body. This reflects the resource's *initialized*
     * public properties (the builders only set the ones the events read) and
     * re-encodes the `Money` / `TaxSummaryCollection` / `Mandate` value objects
     * into the raw array form {@see ResourceFactory} parses.
     *
     * @return array<string, mixed>
     */
    protected function apiResourceToWebhookObject(object $resource): array
    {
        $object = [];

        foreach ((new ReflectionClass($resource))->getProperties() as $property) {
            if ($property->isStatic() || ! $property->isPublic()) {
                continue;
            }

            // Skip typed-but-unset properties the builder never populated, and
            // the resource's injected api client.
            if (! $property->isInitialized($resource) || $property->getName() === 'apiClient') {
                continue;
            }

            $object[$property->getName()] = $this->encodeWebhookValue($property->getValue($resource));
        }

        return $object;
    }

    private function encodeWebhookValue(mixed $value): mixed
    {
        if ($value instanceof Money) {
            return ['currency' => $value->currency, 'value' => $value->value];
        }

        if ($value instanceof Mandate) {
            return ['method' => $value->method, 'maskedIdentifier' => $value->maskedIdentifier];
        }

        if ($value instanceof TaxSummaryCollection) {
            return array_map(
                fn ($item) => [
                    'taxRate' => [
                        'name' => $item->taxRate->name,
                        'percentage' => $item->taxRate->percentage,
                        'taxablePercentage' => $item->taxRate->taxablePercentage,
                    ],
                    'amount' => ['currency' => $item->amount->currency, 'value' => $item->amount->value],
                ],
                $value->items,
            );
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->encodeWebhookValue($item), $value);
        }

        if ($value instanceof \stdClass) {
            return (array) $value;
        }

        return $value;
    }

    /**
     * Build + sign + POST an `order.*` webhook carrying the full order as its
     * fat `object` payload. Replaces the old `fakeGetOrder()` + sparse-payload
     * dance now that the factory hydrates straight from the payload.
     */
    protected function postOrderWebhook(string $eventName, ApiOrder $order): TestResponse
    {
        return $this->postWebhookEvent($eventName, $order->id, 'order', $this->apiResourceToWebhookObject($order));
    }

    protected function postSubscriptionWebhook(string $eventName, ApiSubscription $subscription): TestResponse
    {
        return $this->postWebhookEvent($eventName, $subscription->id, 'subscription', $this->apiResourceToWebhookObject($subscription));
    }

    protected function postRefundWebhook(string $eventName, ApiRefund $refund): TestResponse
    {
        return $this->postWebhookEvent($eventName, $refund->id, 'refund', $this->apiResourceToWebhookObject($refund));
    }

    /**
     * Chargeback webhooks key on the originating order id (`entityType: order`,
     * `entityId: <originalOrderId>`); the chargeback itself rides in `object`.
     */
    protected function postChargebackWebhook(string $eventName, ApiChargeback $chargeback): TestResponse
    {
        return $this->postWebhookEvent($eventName, $chargeback->originalOrderId, 'order', $this->apiResourceToWebhookObject($chargeback));
    }

    /**
     * @param  array{
     *   id: string,
     *   customerId: string,
     *   totalValue: string,
     *   subtotalValue: string,
     *   currency: string,
     *   invoiceNumber: ?string,
     *   paymentMethod: ?string,
     *   taxRates: array<int, array{name: string, percentage: float, taxablePercentage: float, amount: string}>,
     *   lines?: array<int, array{id: string, description: string, quantity: int, basePriceValue: string, totalValue: string, subtotalValue: string, productType?: ?string, productId?: ?string}>,
     * }  $data
     */
    protected function buildApiOrder(array $data): ApiOrder
    {
        $order = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $order->id = $data['id'];
        $order->customerId = $data['customerId'];
        $order->total = new Money($data['currency'], $data['totalValue']);
        $order->subtotal = new Money($data['currency'], $data['subtotalValue']);
        $order->invoiceNumber = $data['invoiceNumber'];
        $order->paymentMethod = $data['paymentMethod'];
        $order->status = 'paid';
        $order->testmode = $data['testmode'] ?? true;
        // The enriched API order carries its lines; default to none so callers
        // that don't exercise line persistence stay unaffected.
        $order->lines = array_map(
            fn (array $line) => (object) [
                'id' => $line['id'],
                'resource' => 'order_line',
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'basePrice' => ['currency' => $data['currency'], 'value' => $line['basePriceValue']],
                'total' => ['currency' => $data['currency'], 'value' => $line['totalValue']],
                'subtotal' => ['currency' => $data['currency'], 'value' => $line['subtotalValue']],
                'taxes' => [],
                'productType' => $line['productType'] ?? null,
                'productId' => $line['productId'] ?? null,
            ],
            $data['lines'] ?? [],
        );
        $order->taxSummary = new TaxSummaryCollection(array_map(
            fn (array $rate) => [
                'taxRate' => [
                    'name' => $rate['name'],
                    'percentage' => $rate['percentage'],
                    'taxablePercentage' => $rate['taxablePercentage'],
                ],
                'amount' => ['currency' => $data['currency'], 'value' => $rate['amount']],
            ],
            $data['taxRates'],
        ));

        return $order;
    }

    /**
     * @param  array{
     *   id: string,
     *   customerId: string,
     *   originalOrderId: string,
     *   status: string,
     *   totalValue: string,
     *   subtotalValue: string,
     *   currency: string,
     *   taxRates: array<int, array{name: string, percentage: float, taxablePercentage: float, amount: string}>,
     * }  $data
     */
    protected function buildApiRefund(array $data): ApiRefund
    {
        $refund = new ApiRefund(Mockery::mock(VatlyApiClient::class));
        $refund->id = $data['id'];
        $refund->customerId = $data['customerId'];
        $refund->originalOrderId = $data['originalOrderId'];
        $refund->status = $data['status'];
        $refund->testmode = $data['testmode'] ?? true;
        $refund->total = new Money($data['currency'], $data['totalValue']);
        $refund->subtotal = new Money($data['currency'], $data['subtotalValue']);
        $refund->taxSummary = new TaxSummaryCollection(array_map(
            fn (array $rate) => [
                'taxRate' => [
                    'name' => $rate['name'],
                    'percentage' => $rate['percentage'],
                    'taxablePercentage' => $rate['taxablePercentage'],
                ],
                'amount' => ['currency' => $data['currency'], 'value' => $rate['amount']],
            ],
            $data['taxRates'],
        ));

        return $refund;
    }

    /**
     * @param  array{
     *   id: string,
     *   customerId: string,
     *   originalOrderId: string,
     *   status: string,
     *   totalValue: string,
     *   subtotalValue: string,
     *   currency: string,
     *   reason?: string,
     *   taxRates: array<int, array{name: string, percentage: float, taxablePercentage: float, amount: string}>,
     * }  $data
     */
    protected function buildApiChargeback(array $data): ApiChargeback
    {
        $chargeback = new ApiChargeback(Mockery::mock(VatlyApiClient::class));
        $chargeback->id = $data['id'];
        $chargeback->customerId = $data['customerId'];
        $chargeback->originalOrderId = $data['originalOrderId'];
        $chargeback->status = $data['status'];
        $chargeback->reason = $data['reason'] ?? '';
        $chargeback->testmode = $data['testmode'] ?? true;
        $chargeback->total = new Money($data['currency'], $data['totalValue']);
        $chargeback->subtotal = new Money($data['currency'], $data['subtotalValue']);
        $chargeback->taxSummary = new TaxSummaryCollection(array_map(
            fn (array $rate) => [
                'taxRate' => [
                    'name' => $rate['name'],
                    'percentage' => $rate['percentage'],
                    'taxablePercentage' => $rate['taxablePercentage'],
                ],
                'amount' => ['currency' => $data['currency'], 'value' => $rate['amount']],
            ],
            $data['taxRates'],
        ));

        return $chargeback;
    }
}
