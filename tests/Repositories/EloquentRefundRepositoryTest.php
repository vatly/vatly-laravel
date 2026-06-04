<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Laravel\Models\Refund;
use Vatly\Laravel\Repositories\EloquentRefundRepository;
use Vatly\Laravel\Tests\BaseTestCase;

class EloquentRefundRepositoryTest extends BaseTestCase
{
    use RefreshDatabase;

    private EloquentRefundRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->app->make(EloquentRefundRepository::class);
    }

    public function test_list_for_customer_returns_only_that_customers_refunds(): void
    {
        $this->makeRefund('refund_a', 'customer_1', 'order_1');
        $this->makeRefund('refund_b', 'customer_1', 'order_2');
        $this->makeRefund('refund_c', 'customer_2', 'order_3');

        $result = $this->repo->listForCustomer('customer_1');

        $this->assertCount(2, $result);
        $ids = array_map(fn ($r) => $r->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['refund_a', 'refund_b'], $ids);
    }

    public function test_list_for_order_returns_only_that_orders_refunds(): void
    {
        $this->makeRefund('refund_a', 'customer_1', 'order_1');
        $this->makeRefund('refund_b', 'customer_2', 'order_1');
        $this->makeRefund('refund_c', 'customer_3', 'order_2');

        $result = $this->repo->listForOrder('order_1');

        $this->assertCount(2, $result);
        $ids = array_map(fn ($r) => $r->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['refund_a', 'refund_b'], $ids);
    }

    private function makeRefund(string $vatlyId, string $customerId, string $originalOrderId): Refund
    {
        return Refund::create([
            'vatly_id' => $vatlyId,
            'customer_id' => $customerId,
            'original_order_id' => $originalOrderId,
            'status' => 'refunded',
            'total' => 9900,
            'subtotal' => 8182,
            'currency' => 'EUR',
            'testmode' => true,
        ]);
    }
}
