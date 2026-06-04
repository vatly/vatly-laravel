<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Vatly\API\Resources\Order as ApiOrder;
use Vatly\API\Types\Money as ApiMoney;
use Vatly\API\Types\OrderLineData;
use Vatly\API\VatlyApiClient;
use Vatly\Fluent\Actions\GetOrder;
use Vatly\Fluent\Contracts\OrderInterface;
use Vatly\Fluent\Data\StoreOrderData;
use Vatly\Fluent\OrderHandle;
use Vatly\Fluent\Vatly;
use Vatly\Laravel\Models\Chargeback;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Models\OrderLine;
use Vatly\Laravel\Models\Refund;
use Vatly\Laravel\Repositories\EloquentOrderRepository;
use Vatly\Laravel\Tests\BaseTestCase;

class OrderTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_it_implements_order_interface(): void
    {
        $order = new Order;

        $this->assertInstanceOf(OrderInterface::class, $order);
    }

    public function test_it_can_be_created_with_attributes(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'order-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $order = Order::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'ord_test_123',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'invoice_number' => 'INV-2024-001',
            'payment_method' => 'credit_card',
            'testmode' => true,
        ]);

        $this->assertSame('ord_test_123', $order->getVatlyId());
        $this->assertSame('paid', $order->getStatus());
        $this->assertSame(9900, $order->getTotal());
        $this->assertSame('EUR', $order->getCurrency());
        $this->assertSame('INV-2024-001', $order->getInvoiceNumber());
        $this->assertSame('credit_card', $order->getPaymentMethod());
        $this->assertTrue($order->isPaid());
        $this->assertTrue($order->isTestmode());
    }

    public function test_it_persists_testmode_and_casts_it_to_bool(): void
    {
        /** @var EloquentOrderRepository $repo */
        $repo = $this->app->make(EloquentOrderRepository::class);

        $repo->store(new StoreOrderData(
            vatlyId: 'ord_live_1',
            customerId: 'cus_1',
            status: 'paid',
            total: 9900,
            currency: 'EUR',
            testmode: false,
        ));

        $this->assertDatabaseHas('vatly_orders', [
            'vatly_id' => 'ord_live_1',
            'testmode' => false,
        ]);

        $order = Order::where('vatly_id', 'ord_live_1')->firstOrFail();
        $this->assertIsBool($order->testmode);
        $this->assertFalse($order->isTestmode());
    }

    public function test_it_has_a_morph_to_owner_relationship(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'order-owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $order = Order::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'ord_owner_123',
            'status' => 'paid',
            'total' => 4900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $this->assertInstanceOf(User::class, $order->owner);
        $this->assertSame($user->id, $order->owner->id);
    }

    public function test_user_can_access_orders_via_relationship(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'order-rel@example.com',
            'password' => bcrypt('password'),
        ]);

        Order::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'ord_rel_1',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        Order::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'ord_rel_2',
            'status' => 'paid',
            'total' => 4900,
            'currency' => 'USD',
            'testmode' => true,
        ]);

        $this->assertCount(2, $user->orders);
    }

    public function test_is_paid_returns_false_for_non_paid_orders(): void
    {
        $order = new Order(['status' => 'pending']);

        $this->assertFalse($order->isPaid());
    }

    public function test_it_exposes_refunds_linked_on_the_vatly_order_id(): void
    {
        Order::create([
            'vatly_id' => 'ord_refunds_1',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        Refund::create([
            'vatly_id' => 'refund_1',
            'original_order_id' => 'ord_refunds_1',
            'status' => 'refunded',
            'total' => 5000,
            'currency' => 'EUR',
            'testmode' => true,
        ]);
        // A refund against a different order must not leak in.
        Refund::create([
            'vatly_id' => 'refund_other',
            'original_order_id' => 'ord_other',
            'status' => 'refunded',
            'total' => 100,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $order = Order::where('vatly_id', 'ord_refunds_1')->firstOrFail();

        $this->assertCount(1, $order->refunds);
        $this->assertSame('refund_1', $order->refunds->first()->getVatlyId());
    }

    public function test_it_exposes_chargebacks_linked_on_the_vatly_order_id(): void
    {
        Order::create([
            'vatly_id' => 'ord_cb_1',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        Chargeback::create([
            'vatly_id' => 'chargeback_1',
            'original_order_id' => 'ord_cb_1',
            'status' => 'pending',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);
        Chargeback::create([
            'vatly_id' => 'chargeback_other',
            'original_order_id' => 'ord_other',
            'status' => 'pending',
            'total' => 100,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $order = Order::where('vatly_id', 'ord_cb_1')->firstOrFail();

        $this->assertCount(1, $order->chargebacks);
        $this->assertSame('chargeback_1', $order->chargebacks->first()->getVatlyId());
    }

    public function test_it_exposes_lines_linked_on_the_vatly_order_id(): void
    {
        Order::create([
            'vatly_id' => 'ord_lines_1',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        OrderLine::create([
            'vatly_id' => 'order_item_1',
            'order_vatly_id' => 'ord_lines_1',
            'description' => 'Pro plan',
            'quantity' => 1,
            'base_price' => 9900,
            'total' => 9900,
            'subtotal' => 8182,
        ]);
        // A line on a different order must not leak in.
        OrderLine::create([
            'vatly_id' => 'order_item_other',
            'order_vatly_id' => 'ord_other',
            'description' => 'Other',
            'quantity' => 1,
            'base_price' => 100,
            'total' => 100,
            'subtotal' => 100,
        ]);

        $order = Order::where('vatly_id', 'ord_lines_1')->firstOrFail();

        $this->assertCount(1, $order->lines);
        $this->assertSame('order_item_1', $order->lines->first()->getVatlyId());
    }

    public function test_storing_an_order_with_lines_persists_the_lines(): void
    {
        /** @var EloquentOrderRepository $repo */
        $repo = $this->app->make(EloquentOrderRepository::class);

        $repo->store(new StoreOrderData(
            vatlyId: 'ord_store_lines',
            customerId: 'cus_1',
            status: 'paid',
            total: 9900,
            currency: 'EUR',
            testmode: true,
            lines: [
                new OrderLineData(
                    vatlyId: 'order_item_x',
                    description: 'Pro plan — monthly',
                    quantity: 1,
                    basePrice: new ApiMoney('EUR', '99.00'),
                    total: new ApiMoney('EUR', '99.00'),
                    subtotal: new ApiMoney('EUR', '81.82'),
                    productType: 'subscription',
                    productId: 'subscription_42',
                ),
            ],
        ));

        $this->assertDatabaseHas('vatly_order_lines', [
            'vatly_id' => 'order_item_x',
            'order_vatly_id' => 'ord_store_lines',
            'product_type' => 'subscription',
            'product_id' => 'subscription_42',
        ]);

        $order = Order::where('vatly_id', 'ord_store_lines')->firstOrFail();
        $this->assertCount(1, $order->lines);
    }

    public function test_reversal_helpers_surface_a_partial_reversal_from_the_api_order(): void
    {
        $order = Order::create([
            'vatly_id' => 'ord_partial_reversal',
            'status' => 'paid',
            'total' => 12100,
            'subtotal' => 10000,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $this->fakeVatlyOrder($order, subtotal: '100.00', reversed: '40.00', refundable: '60.00');

        // The local status stays terminal `paid` — reversal progress is read live.
        $this->assertSame('paid', $order->getStatus());
        $this->assertSame(4000, $order->reversedSubtotal());
        $this->assertSame(6000, $order->refundableSubtotal());
        $this->assertTrue($order->isReversed());
        $this->assertTrue($order->isPartiallyReversed());
        $this->assertFalse($order->isFullyReversed());
    }

    public function test_reversal_helpers_surface_a_full_reversal_from_the_api_order(): void
    {
        $order = Order::create([
            'vatly_id' => 'ord_full_reversal',
            'status' => 'paid',
            'total' => 12100,
            'subtotal' => 10000,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $this->fakeVatlyOrder($order, subtotal: '100.00', reversed: '100.00', refundable: '0.00');

        $this->assertSame(10000, $order->reversedSubtotal());
        $this->assertSame(0, $order->refundableSubtotal());
        $this->assertTrue($order->isReversed());
        $this->assertFalse($order->isPartiallyReversed());
        $this->assertTrue($order->isFullyReversed());
    }

    public function test_reversal_helpers_report_no_reversal_for_an_untouched_order(): void
    {
        $order = Order::create([
            'vatly_id' => 'ord_no_reversal',
            'status' => 'paid',
            'total' => 12100,
            'subtotal' => 10000,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $this->fakeVatlyOrder($order, subtotal: '100.00', reversed: '0.00', refundable: '100.00');

        $this->assertSame(0, $order->reversedSubtotal());
        $this->assertSame(10000, $order->refundableSubtotal());
        $this->assertFalse($order->isReversed());
        $this->assertFalse($order->isPartiallyReversed());
        $this->assertFalse($order->isFullyReversed());
    }

    /**
     * Bind the {@see Vatly} facade so that resolving a handle for `$order`
     * returns one backed by a canned API order with the given reversal figures,
     * letting the model's reversal helpers run the real {@see OrderHandle} logic
     * without hitting the network.
     */
    private function fakeVatlyOrder(Order $order, string $subtotal, string $reversed, string $refundable): void
    {
        $apiOrder = new ApiOrder(Mockery::mock(VatlyApiClient::class));
        $apiOrder->subtotal = new ApiMoney('EUR', $subtotal);
        $apiOrder->reversedSubtotal = new ApiMoney('EUR', $reversed);
        $apiOrder->refundableSubtotal = new ApiMoney('EUR', $refundable);

        $getOrder = Mockery::mock(GetOrder::class);
        $getOrder->shouldReceive('execute')
            ->with($order->getVatlyId())
            ->andReturn($apiOrder);

        $handle = new OrderHandle($order, $getOrder);

        $vatly = Mockery::mock(Vatly::class);
        $vatly->shouldReceive('order')->with($order)->andReturn($handle);

        $this->app->instance(Vatly::class, $vatly);
    }
}
