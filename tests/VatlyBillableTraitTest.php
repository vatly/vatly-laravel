<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests;

use App\Models\CoexistingUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\OrderHandle;
use Vatly\Fluent\SubscriptionHandle;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Models\Subscription;
use Vatly\Laravel\VatlyBillable;

/**
 * Exercises {@see VatlyBillable} on a model that *also* carries a
 * foreign Cashier-style `Billable` trait, proving the two coexist with no
 * collision and without cross-wiring the state readers.
 */
class VatlyBillableTraitTest extends BaseTestCase
{
    use RefreshDatabase;

    private function user(array $attrs = []): CoexistingUser
    {
        return CoexistingUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'coexist+'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'vatly_id' => null,
        ], $attrs));
    }

    private function activeVatlySubscription(CoexistingUser $user, string $type = 'default'): Subscription
    {
        return Subscription::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'subscription_abc',
            'type' => $type,
            'plan_id' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'testmode' => true,
        ]);
    }

    public function test_the_two_billable_traits_compose_without_collision(): void
    {
        $user = $this->user();

        // The foreign trait keeps the unprefixed names…
        $this->assertSame('foreign:subscriptions', $user->subscriptions());
        $this->assertSame('foreign:subscription', $user->subscription());
        $this->assertSame('foreign:subscribe', $user->subscribe());
        $this->assertSame('foreign:checkout', $user->checkout());
        $this->assertSame('foreign:orders', $user->orders());
        $this->assertTrue($user->subscribed());

        // …while Vatly lives entirely under vatly* names.
        $this->assertInstanceOf(SubscriptionBuilder::class, $user->vatlySubscribe());
        $this->assertInstanceOf(CheckoutBuilder::class, $user->vatlyCheckout());
        $this->assertInstanceOf(MorphMany::class, $user->vatlySubscriptions());
        $this->assertInstanceOf(MorphMany::class, $user->vatlyOrders());
    }

    public function test_vatly_readers_are_independent_of_the_foreign_subscription_state(): void
    {
        $user = $this->user();

        // No Vatly rows yet — vatlySubscribed() must run Vatly's own logic and
        // return false, even though the foreign subscribed() returns true.
        $this->assertTrue($user->subscribed());
        $this->assertFalse($user->vatlySubscribed());
        $this->assertNull($user->vatlySubscription());
    }

    public function test_vatly_subscribed_reads_the_prefixed_relation_not_the_foreign_one(): void
    {
        $user = $this->user();
        $subscription = $this->activeVatlySubscription($user);

        $this->assertTrue($user->vatlySubscribed());
        $this->assertFalse($user->vatlySubscribed('team'));

        $handle = $user->vatlySubscription();

        $this->assertInstanceOf(SubscriptionHandle::class, $handle);
        $this->assertSame('subscription_abc', $handle->getVatlyId());
        $this->assertSame('plan_basic', $handle->getPlanId());
        $this->assertTrue($handle->active());
        $this->assertEquals($subscription->id, $handle->model()->getKey());

        // The foreign reader is untouched — no cross-wiring through subscriptions().
        $this->assertSame('foreign:subscription', $user->subscription());
        $this->assertTrue($user->subscribed());
    }

    public function test_vatly_subscriptions_relation_returns_only_owned_rows(): void
    {
        $user = $this->user(['vatly_id' => 'customer_owned']);
        $this->activeVatlySubscription($user);

        // A subscription owned by nobody (anonymous flow) must not leak in.
        Subscription::create([
            'vatly_id' => 'subscription_orphan',
            'customer_id' => 'cus_orphan',
            'type' => 'default',
            'plan_id' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'testmode' => true,
        ]);

        $this->assertCount(1, $user->vatlySubscriptions);
        $this->assertSame('subscription_abc', $user->vatlySubscriptions->first()->getVatlyId());
    }

    public function test_vatly_order_resolves_through_the_prefixed_relation(): void
    {
        $user = $this->user();

        Order::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'vatly_id' => 'order_abc',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $handle = $user->vatlyOrder('order_abc');

        $this->assertInstanceOf(OrderHandle::class, $handle);
        $this->assertSame('order_abc', $handle->getVatlyId());

        // The foreign relation is still its own thing.
        $this->assertSame('foreign:orders', $user->orders());
    }

    public function test_shared_customer_helpers_are_available_on_the_coexistence_trait(): void
    {
        $user = $this->user([
            'vatly_id' => 'customer_xyz',
            'email' => 'sander@example.test',
            'name' => 'Sander',
        ]);

        // Identity + profile (shared via ManagesVatlyCustomer).
        $this->assertSame('customer_xyz', $user->vatlyId());
        $this->assertTrue($user->hasVatlyId());
        $profile = $user->customerProfile();
        $this->assertInstanceOf(CustomerProfile::class, $profile);
        $this->assertSame('customer_xyz', $profile->vatlyId);

        // Static finders.
        $found = CoexistingUser::findBillable('customer_xyz');
        $this->assertNotNull($found);
        $this->assertSame($user->getKey(), $found->getKey());

        $this->expectException(ModelNotFoundException::class);
        CoexistingUser::findBillableOrFail('customer_nonexistent');
    }

    public function test_claim_backfills_orphan_rows_on_the_coexistence_trait(): void
    {
        Subscription::create([
            'vatly_id' => 'sub_anon',
            'customer_id' => 'cus_anon',
            'type' => 'default',
            'plan_id' => 'plan_basic',
            'name' => 'Basic',
            'quantity' => 1,
            'testmode' => true,
        ]);
        Order::create([
            'vatly_id' => 'order_anon',
            'customer_id' => 'cus_anon',
            'status' => 'paid',
            'total' => 9900,
            'currency' => 'EUR',
            'testmode' => true,
        ]);

        $user = $this->user(['vatly_id' => null]);

        $claimed = $user->claimVatlyCustomer('cus_anon');

        $this->assertSame(2, $claimed);
        $this->assertSame('cus_anon', $user->fresh()->vatly_id);
        $this->assertCount(1, $user->vatlySubscriptions);
        $this->assertCount(1, $user->vatlyOrders);
    }
}
