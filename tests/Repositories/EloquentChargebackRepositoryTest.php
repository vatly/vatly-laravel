<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Fluent\Data\StoreChargebackData;
use Vatly\Fluent\Data\UpdateChargebackData;
use Vatly\Laravel\Models\Chargeback;
use Vatly\Laravel\Repositories\EloquentChargebackRepository;
use Vatly\Laravel\Tests\BaseTestCase;

class EloquentChargebackRepositoryTest extends BaseTestCase
{
    use RefreshDatabase;

    private EloquentChargebackRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->app->make(EloquentChargebackRepository::class);
    }

    public function test_store_persists_a_chargeback(): void
    {
        $chargeback = $this->repo->store(new StoreChargebackData(
            vatlyId: 'chargeback_1',
            customerId: 'customer_1',
            status: 'pending',
            total: 9900,
            currency: 'EUR',
            originalOrderId: 'order_1',
            reason: 'fraud',
            subtotal: 8182,
            testmode: true,
        ));

        $this->assertNotNull($chargeback);
        $this->assertSame('chargeback_1', $chargeback->getVatlyId());
        $this->assertSame('fraud', $chargeback->getReason());
        $this->assertTrue($chargeback->isTestmode());
        $this->assertDatabaseHas('vatly_chargebacks', [
            'vatly_id' => 'chargeback_1',
            'original_order_id' => 'order_1',
            'status' => 'pending',
            'reason' => 'fraud',
            'testmode' => true,
        ]);
    }

    public function test_find_by_vatly_id_returns_the_chargeback(): void
    {
        Chargeback::create([
            'vatly_id' => 'chargeback_find',
            'customer_id' => 'customer_1',
            'original_order_id' => 'order_1',
            'status' => 'pending',
            'total' => 100,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $this->assertNotNull($this->repo->findByVatlyId('chargeback_find'));
        $this->assertNull($this->repo->findByVatlyId('chargeback_missing'));
    }

    public function test_list_for_customer_returns_only_that_customers_chargebacks(): void
    {
        $this->makeChargeback('chargeback_a', 'customer_1', 'order_1');
        $this->makeChargeback('chargeback_b', 'customer_1', 'order_2');
        $this->makeChargeback('chargeback_c', 'customer_2', 'order_3');

        $result = $this->repo->listForCustomer('customer_1');

        $this->assertCount(2, $result);
        $ids = array_map(fn ($c) => $c->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['chargeback_a', 'chargeback_b'], $ids);
    }

    public function test_list_for_order_returns_only_that_orders_chargebacks(): void
    {
        $this->makeChargeback('chargeback_a', 'customer_1', 'order_1');
        $this->makeChargeback('chargeback_b', 'customer_2', 'order_1');
        $this->makeChargeback('chargeback_c', 'customer_3', 'order_2');

        $result = $this->repo->listForOrder('order_1');

        $this->assertCount(2, $result);
        $ids = array_map(fn ($c) => $c->getVatlyId(), $result);
        $this->assertEqualsCanonicalizing(['chargeback_a', 'chargeback_b'], $ids);
    }

    public function test_update_flips_status_to_reversed(): void
    {
        $chargeback = $this->makeChargeback('chargeback_rev', 'customer_1', 'order_1');
        $this->assertFalse($chargeback->isReversed());

        $updated = $this->repo->update($chargeback, new UpdateChargebackData(status: 'won'));

        $this->assertSame('won', $updated->getStatus());
        $this->assertTrue($updated->isReversed());
        $this->assertDatabaseHas('vatly_chargebacks', [
            'vatly_id' => 'chargeback_rev',
            'status' => 'won',
        ]);
    }

    private function makeChargeback(string $vatlyId, string $customerId, string $originalOrderId): Chargeback
    {
        return Chargeback::create([
            'vatly_id' => $vatlyId,
            'customer_id' => $customerId,
            'original_order_id' => $originalOrderId,
            'status' => 'pending',
            'total' => 9900,
            'subtotal' => 8182,
            'currency' => 'EUR',
            'reason' => 'fraud',
            'testmode' => true,
        ]);
    }
}
