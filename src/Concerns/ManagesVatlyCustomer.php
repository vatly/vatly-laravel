<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Illuminate\Http\Request;
use Vatly\API\Resources\Customer;
use Vatly\Fluent\CustomerProfile;
use Vatly\Fluent\Exceptions\CustomerAlreadyBoundException;
use Vatly\Fluent\Vatly;
use Vatly\Laravel\Billable;
use Vatly\Laravel\Exceptions\NoVatlyCustomerException;
use Vatly\Laravel\Models\Order;
use Vatly\Laravel\Models\Subscription;
use Vatly\Laravel\VatlyBillable;

/**
 * Vatly customer identity, creation, claiming and lookup — shared between the
 * standalone {@see Billable} trait and the prefixed,
 * coexistence-friendly {@see VatlyBillable} trait.
 *
 * Everything here is independent of the subscription/order *relation* names, so
 * both public traits can compose it without their relation methods colliding.
 * The method names below already carry "Vatly" (or are otherwise Vatly-specific),
 * so they never clash with another Cashier-style biller on the same model.
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
trait ManagesVatlyCustomer
{
    // --- Vatly identity / profile accessors ---

    public function vatlyId(): ?string
    {
        return $this->vatly_id;
    }

    public function hasVatlyId(): bool
    {
        return $this->vatly_id !== null;
    }

    public function vatlyEmail(): ?string
    {
        return $this->email ?? null;
    }

    public function vatlyName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Snapshot the host-side fields fluent uses when talking to the API.
     */
    public function customerProfile(): CustomerProfile
    {
        return new CustomerProfile(
            vatlyId: $this->vatlyId(),
            email: $this->vatlyEmail(),
            name: $this->vatlyName(),
        );
    }

    // --- Customer-creation shortcuts ---

    /**
     * Create the Vatly customer for this owner and bind the resulting id.
     *
     * The host id is written via the configured CustomerBindingRepository
     * (which for the default Eloquent impl updates the `vatly_id` column
     * directly). The in-memory model is also refreshed so subsequent calls
     * see the new id without an extra query.
     *
     * `$options` keys not consumed by `email` / `name` (the host-supplied
     * defaults the trait fills in from this model) are forwarded as-is to
     * the create-customer API call — `locale`, `metadata`, etc.
     *
     * @param  array<string, mixed>  $options  Create-customer API payload keys.
     *                                         Caller-supplied `email` / `name`
     *                                         override the host defaults.
     */
    public function createAsVatlyCustomer(array $options = []): Customer
    {
        $profile = new CustomerProfile(
            email: $options['email'] ?? $this->vatlyEmail(),
            name: $options['name'] ?? $this->vatlyName(),
        );

        unset($options['email'], $options['name']);

        $customer = app(Vatly::class)
            ->customers()
            ->createFor((string) $this->getKey(), $profile, $options);

        $this->vatly_id = $customer->id;

        return $customer;
    }

    public function asVatlyCustomer(): Customer
    {
        if (! $this->hasVatlyId()) {
            throw NoVatlyCustomerException::notYetCreated($this);
        }

        return app(Vatly::class)->customers()->findByVatlyCustomerId((string) $this->vatlyId());
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createOrGetVatlyCustomer(array $options = []): Customer
    {
        if ($this->hasVatlyId()) {
            return $this->asVatlyCustomer();
        }

        return $this->createAsVatlyCustomer($options);
    }

    /**
     * Claim an anonymous Vatly customer for this host entity.
     *
     * Use this on a user-signup hook when the user paid via guest checkout
     * before the account existed. Pass the Vatly customer id (typically
     * stashed on the checkout-success redirect or in the session) and this
     * method:
     *
     *   1. binds the Vatly id ↔ host id pair (via the CustomerBindingRepository),
     *   2. writes `vatly_id` onto this model so subsequent lookups find it,
     *   3. backfills `owner_type` / `owner_id` on every `vatly_subscriptions`
     *      and `vatly_orders` row that carries this `customer_id` but had no
     *      owner yet — the rows the webhook persisted during the anonymous
     *      flow.
     *
     * Returns the number of subscription + order rows that were re-attributed.
     */
    public function claimVatlyCustomer(string $vatlyCustomerId): int
    {
        app(Vatly::class)
            ->customers()
            ->attribute($vatlyCustomerId, (string) $this->getKey());

        $this->vatly_id = $vatlyCustomerId;
        $this->save();

        $ownerAttrs = [
            'owner_type' => $this->getMorphClass(),
            'owner_id' => $this->getKey(),
        ];

        $subscriptions = Subscription::query()
            ->whereNull('owner_id')
            ->where('customer_id', $vatlyCustomerId)
            ->update($ownerAttrs);

        $orders = Order::query()
            ->whereNull('owner_id')
            ->where('customer_id', $vatlyCustomerId)
            ->update($ownerAttrs);

        return $subscriptions + $orders;
    }

    /**
     * Claim an anonymous Vatly customer on the checkout-success redirect back.
     *
     * Reads the checkout id from the request query — Vatly substitutes it into
     * the redirect URL via the `{CHECKOUT_ID}` placeholder you set when building
     * the checkout (e.g. `route('vatly.return').'?checkout_id={CHECKOUT_ID}'`) —
     * resolves the checkout's Vatly customer id, and, if a customer is attached,
     * runs {@see self::claimVatlyCustomer()} to bind it and re-attribute this
     * entity's previously-anonymous orders and subscriptions.
     *
     * Multi-tab safe by construction: each tab carries its own checkout id in
     * its own redirect URL, so concurrent checkouts resolve independently —
     * there is no shared session/cookie carrier whose last write wins.
     *
     * Returns whether a claim happened. Returns false (without throwing) for a
     * missing / unknown checkout id, or a checkout with no customer yet — so it
     * is safe to call unconditionally on the return route. A cross-host conflict
     * (the id already bound to a different customer) still throws
     * {@see CustomerAlreadyBoundException}.
     *
     * Out of scope: buyers who never return via the redirect link (closed tab,
     * different device). That is the email-recovery story, handled separately.
     */
    public function claimVatlyCustomerFromReturn(Request $request, string $key = 'checkout_id'): bool
    {
        $checkoutId = $request->query($key);

        if (! is_string($checkoutId) || $checkoutId === '') {
            return false;
        }

        $customerId = app(Vatly::class)->customerIdFromCheckout($checkoutId);

        if ($customerId === null) {
            return false;
        }

        $this->claimVatlyCustomer($customerId);

        return true;
    }

    // --- Static finders ---

    public static function findBillable(string $vatlyId): ?static
    {
        return static::where('vatly_id', $vatlyId)->first();
    }

    public static function findBillableOrFail(string $vatlyId): static
    {
        return static::where('vatly_id', $vatlyId)->firstOrFail();
    }
}
