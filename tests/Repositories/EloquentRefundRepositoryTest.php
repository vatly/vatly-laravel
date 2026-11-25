<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Repositories;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Vatly\Fluent\Data\StoreRefundData;
use Vatly\Fluent\Data\UpdateRefundData;
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

    public function test_store_saves_metadata(): void
    {
        $this->repo->store($this->storeData(['orderRef' => 'A-1', 'lines' => [1, 2]]));

        $refund = Refund::where('vatly_id', 'refund_meta')->firstOrFail();
        $this->assertSame(['orderRef' => 'A-1', 'lines' => [1, 2]], $refund->metadata);
    }

    public function test_store_without_metadata_leaves_it_null(): void
    {
        $this->repo->store($this->storeData(null));

        $this->assertNull(Refund::where('vatly_id', 'refund_meta')->firstOrFail()->metadata);
    }

    public function test_update_replaces_metadata_and_null_keeps_it(): void
    {
        $refund = $this->repo->store($this->storeData(['orderRef' => 'A-1']));

        $this->repo->update($refund, new UpdateRefundData(status: 'failed', metadata: ['orderRef' => 'B-2']));
        $this->assertSame(['orderRef' => 'B-2'], Refund::where('vatly_id', 'refund_meta')->firstOrFail()->metadata);

        $this->repo->update($refund, new UpdateRefundData(status: 'canceled'));
        $stored = Refund::where('vatly_id', 'refund_meta')->firstOrFail();
        $this->assertSame('canceled', $stored->status);
        $this->assertSame(['orderRef' => 'B-2'], $stored->metadata);
    }

    public function test_refunds_are_still_stored_and_updated_before_the_metadata_column_is_migrated(): void
    {
        Schema::table('vatly_refunds', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        $refund = $this->repo->store($this->storeData(['orderRef' => 'A-1']));
        $this->repo->update($refund, new UpdateRefundData(status: 'failed', metadata: ['orderRef' => 'B-2']));

        $stored = Refund::where('vatly_id', 'refund_meta')->firstOrFail();
        $this->assertSame('failed', $stored->status);
        $this->assertNull($stored->metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function storeData(?array $metadata): StoreRefundData
    {
        return new StoreRefundData(
            vatlyId: 'refund_meta',
            customerId: 'customer_1',
            status: 'refunded',
            total: 9900,
            currency: 'EUR',
            originalOrderId: 'order_1',
            testmode: true,
            metadata: $metadata,
        );
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
