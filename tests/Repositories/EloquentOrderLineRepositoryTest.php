<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\API\Types\Money;
use Vatly\API\Types\OrderLineData;
use Vatly\API\Types\OrderLineRelatedResources;
use Vatly\Laravel\Models\OrderLine;
use Vatly\Laravel\Repositories\EloquentOrderLineRepository;
use Vatly\Laravel\Tests\BaseTestCase;

class EloquentOrderLineRepositoryTest extends BaseTestCase
{
    use RefreshDatabase;

    private EloquentOrderLineRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->app->make(EloquentOrderLineRepository::class);
    }

    public function test_list_for_order_returns_only_that_orders_lines(): void
    {
        $this->makeLine('order_item_a', 'order_1');
        $this->makeLine('order_item_b', 'order_1');
        $this->makeLine('order_item_c', 'order_2');

        $result = $this->repo->listForOrder('order_1');

        $this->assertCount(2, $result);
        $ids = array_map(fn ($l) => $l->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['order_item_a', 'order_item_b'], $ids);
    }

    public function test_list_for_subscription_includes_the_initial_order_and_renewals(): void
    {
        // Initial signup line: references the plan, linked only by subscription_id.
        $this->makeLine('order_item_a', 'order_1', 'subscription_plan', 'subscription_plan_pro', 'subscription_target');
        // Renewal carrying the link: matches both rules but is returned once.
        $this->makeLine('order_item_b', 'order_2', 'subscription', 'subscription_target', 'subscription_target');
        // Renewal stored before Vatly carried the link.
        $this->makeLine('order_item_c', 'order_3', 'subscription', 'subscription_target');
        // A different subscription's renewal must not leak in.
        $this->makeLine('order_item_d', 'order_4', 'subscription', 'subscription_other', 'subscription_other');
        // Another customer's signup for the same plan must not leak in.
        $this->makeLine('order_item_e', 'order_5', 'subscription_plan', 'subscription_plan_pro', 'subscription_other');
        // A signup whose subscription was not yet available must not leak in.
        $this->makeLine('order_item_f', 'order_6', 'subscription_plan', 'subscription_plan_pro');
        // A one-off product line for the same id value must not leak in.
        $this->makeLine('order_item_g', 'order_7', 'one_off_product', 'subscription_target');
        // An unattributed line must not leak in.
        $this->makeLine('order_item_h', 'order_8', null, null);

        $result = $this->repo->listForSubscription('subscription_target');

        $ids = array_map(fn ($l) => $l->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['order_item_a', 'order_item_b', 'order_item_c'], $ids);
    }

    public function test_store_persists_a_line_row(): void
    {
        $line = $this->repo->store(
            new OrderLineData(
                vatlyId: 'order_item_store',
                description: 'Pro plan — monthly',
                quantity: 1,
                basePrice: new Money('EUR', '99.00'),
                total: new Money('EUR', '99.00'),
                subtotal: new Money('EUR', '81.82'),
                taxSummary: null,
                productType: 'subscription',
                productId: 'subscription_123',
                relatedResources: new OrderLineRelatedResources('subscription_123'),
            ),
            'order_store',
        );

        $this->assertNotNull($line);
        $this->assertSame('order_item_store', $line->getVatlyId());
        $this->assertSame('order_store', $line->getOrderId());
        $this->assertSame('subscription', $line->getProductType());
        $this->assertSame('subscription_123', $line->getProductId());
        $this->assertSame('subscription_123', $line->getSubscriptionId());
        $this->assertDatabaseHas('vatly_order_lines', [
            'vatly_id' => 'order_item_store',
            'order_vatly_id' => 'order_store',
            'product_type' => 'subscription',
            'product_id' => 'subscription_123',
            'subscription_id' => 'subscription_123',
            'base_price' => 9900,
            'total' => 9900,
            'subtotal' => 8182,
        ]);
    }

    private function makeLine(
        string $vatlyId,
        string $orderVatlyId,
        ?string $productType = null,
        ?string $productId = null,
        ?string $subscriptionId = null,
    ): OrderLine {
        return OrderLine::create([
            'vatly_id' => $vatlyId,
            'order_vatly_id' => $orderVatlyId,
            'description' => 'A line',
            'quantity' => 1,
            'base_price' => 9900,
            'total' => 9900,
            'subtotal' => 8182,
            'product_type' => $productType,
            'product_id' => $productId,
            'subscription_id' => $subscriptionId,
        ]);
    }
}
