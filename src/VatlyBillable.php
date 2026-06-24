<?php

declare(strict_types=1);

namespace Vatly\Laravel;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Exceptions\InvalidOrderException;
use Vatly\Fluent\OrderHandle;
use Vatly\Fluent\SubscriptionHandle;
use Vatly\Fluent\Vatly;
use Vatly\Laravel\Concerns\ManagesVatlyCustomer;
use Vatly\Laravel\Models\Chargeback;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Models\Refund;
use Vatly\Laravel\Models\Subscription;

/**
 * Vatly billing capability under `vatly*`-prefixed names — for running Vatly
 * *beside* another Cashier-style biller on the same Eloquent model.
 *
 * Laravel Cashier (Stripe and Paddle) and Lemon Squeezy for Laravel each ship a
 * `Billable` trait that defines `subscribed()`, `subscription()`,
 * `subscriptions()` and `checkout()` (Paddle and Lemon Squeezy also `subscribe()`;
 * Lemon Squeezy also `orders()`). PHP forbids two traits defining the same method
 * on one class, so {@see Billable} cannot sit next to one of those. This trait
 * exposes the identical Vatly surface as `vatlySubscribed()`,
 * `vatlySubscription()`, `vatlySubscribe()`, `vatlyCheckout()`, `vatlyOrder()` and
 * the `vatly*` relations — so both billers coexist with no trait aliasing.
 *
 * Crucially, the state readers (`vatlySubscribed()` / `vatlySubscription()`) query
 * this trait's own `vatlySubscriptions()` relation, never an unprefixed
 * `subscriptions()` that the other biller owns — so there is no cross-wiring.
 *
 * Customer identity and claim helpers (`createAsVatlyCustomer()`,
 * `claimVatlyCustomerFromReturn()`, `findBillable()`, …) already carry the Vatly
 * name, don't collide, and are shared verbatim with {@see Billable} via
 * {@see ManagesVatlyCustomer}.
 *
 * If Vatly is your *only* biller, use {@see Billable} (unprefixed) instead.
 *
 * @property string|null $vatly_id
 * @property string|null $email
 * @property string|null $name
 *
 * @method static where(string $column, mixed $value)
 * @method bool save()
 * @method mixed getKey()
 * @method string getMorphClass()
 */
trait VatlyBillable
{
    use ManagesVatlyCustomer;

    // --- Eloquent relations (vatly*-prefixed to avoid collisions) ---

    /**
     * @return MorphMany<Subscription>
     */
    public function vatlySubscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'owner')->orderByDesc('created_at');
    }

    /**
     * @return MorphMany<Order>
     */
    public function vatlyOrders(): MorphMany
    {
        return $this->morphMany(Order::class, 'owner')->orderByDesc('created_at');
    }

    /**
     * @return MorphMany<Refund>
     */
    public function vatlyRefunds(): MorphMany
    {
        return $this->morphMany(Refund::class, 'owner')->orderByDesc('created_at');
    }

    /**
     * @return MorphMany<Chargeback>
     */
    public function vatlyChargebacks(): MorphMany
    {
        return $this->morphMany(Chargeback::class, 'owner')->orderByDesc('created_at');
    }

    // --- Subscription accessors (vatly*-prefixed) ---

    public function vatlySubscribe(): SubscriptionBuilder
    {
        return app(Vatly::class)->subscriptionBuilder($this->customerProfile());
    }

    public function vatlySubscribed(string $type = Subscription::DEFAULT_TYPE): bool
    {
        $subscription = $this->vatlySubscriptions()
            ->where('type', $type)
            ->first();

        return $subscription !== null && $subscription->isActive();
    }

    public function vatlySubscription(string $type = Subscription::DEFAULT_TYPE): ?SubscriptionHandle
    {
        $local = $this->vatlySubscriptions()
            ->where('type', $type)
            ->first();

        return $local !== null ? app(Vatly::class)->subscription($local) : null;
    }

    public function vatlyCheckout(): CheckoutBuilder
    {
        return app(Vatly::class)->checkoutBuilder($this->customerProfile());
    }

    /**
     * @throws InvalidOrderException When no order with the given Vatly id exists for this owner.
     */
    public function vatlyOrder(string $vatlyId): OrderHandle
    {
        $local = $this->vatlyOrders()
            ->where('vatly_id', $vatlyId)
            ->first();

        if ($local === null) {
            throw InvalidOrderException::notFound($vatlyId);
        }

        return app(Vatly::class)->order($local);
    }
}
