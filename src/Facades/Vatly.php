<?php

declare(strict_types=1);

namespace Vatly\Laravel\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Facade;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Vatly as FluentVatly;
use Vatly\Laravel\Models\VatlyWebhookCall;
use Vatly\Laravel\VatlyConfig;

/**
 * Facade over the Vatly composition root ({@see FluentVatly}).
 *
 * The single brand-named entry point for the package's static surface:
 *
 * - reaches the framework-agnostic API — `Vatly::order($order)`,
 *   `Vatly::subscription($subscription)`, … — by proxying the resolved
 *   `Vatly\Fluent\Vatly` instance;
 * - adds the Cashier-style host-side helpers: {@see self::fake()},
 *   {@see self::findBillable()}, {@see self::findBillableOrFail()} and
 *   {@see self::cleanUp()}.
 *
 * Lives in the `Facades` namespace (rather than a `Vatly\Laravel\Vatly`
 * class) so it never clashes with the underlying `Vatly\Fluent\Vatly` that
 * the package resolves as `app(Vatly::class)`.
 *
 * @method static \Vatly\Fluent\OrderHandle order(\Vatly\Fluent\Contracts\OrderInterface $order)
 * @method static \Vatly\Fluent\SubscriptionHandle subscription(\Vatly\Fluent\Contracts\SubscriptionInterface $subscription)
 * @method static \Vatly\Fluent\Builders\CheckoutBuilder checkoutBuilder(\Vatly\Fluent\CustomerProfile $profile)
 * @method static \Vatly\Fluent\Builders\SubscriptionBuilder subscriptionBuilder(\Vatly\Fluent\CustomerProfile $profile)
 * @method static \Vatly\Fluent\CustomerService customers()
 * @method static \Vatly\Fluent\CustomerHandle customer(string $vatlyCustomerId)
 * @method static ?string customerIdFromCheckout(string $checkoutId)
 * @method static bool claimCustomerFromCheckout(string $checkoutId, string $hostCustomerId)
 * @method static \Vatly\Fluent\Webhooks\WebhookProcessor webhookProcessor()
 *
 * @see FluentVatly
 */
class Vatly extends Facade
{
    /**
     * Swap the Vatly composition root for a {@see FakeVatly} and bind it into
     * the container — the Cashier-style one-liner (`Vatly::fake()`) for feature
     * tests.
     *
     * Every `subscribe()` / `checkout()` / `subscription()` call made through
     * the `Billable` trait — and every `Vatly::…` facade call — then routes
     * through recording fakes instead of the real API, so a test scripts only
     * what it cares about and asserts against the returned fake:
     *
     * ```php
     * $vatly = Vatly::fake();
     *
     * $user->subscribe()->toPlan('plan_pro')->withTrialDays(14)->create();
     *
     * $vatly->assertSubscriptionCreated('plan_pro');
     * ```
     */
    public static function fake(): FakeVatly
    {
        $fake = new FakeVatly;

        app()->instance(FluentVatly::class, $fake);

        // Drop any instance this facade already resolved so subsequent
        // `Vatly::…` calls proxy through the freshly-bound fake.
        static::clearResolvedInstance(static::getFacadeAccessor());

        return $fake;
    }

    /**
     * Get the billable instance by its Vatly customer ID, or null when no row
     * matches. Resolves the configured billable model, so callers don't need to
     * name it.
     */
    public static function findBillable(string $vatlyCustomerId): ?Model
    {
        $billableModel = app()->make(VatlyConfig::class)->getBillableModel();

        return $billableModel::where('vatly_id', $vatlyCustomerId)->first();
    }

    /**
     * Get the billable instance by its Vatly customer ID.
     *
     * @throws ModelNotFoundException when no row matches.
     */
    public static function findBillableOrFail(string $vatlyCustomerId): Model
    {
        $billableModel = app()->make(VatlyConfig::class)->getBillableModel();

        return $billableModel::where('vatly_id', $vatlyCustomerId)->firstOrFail();
    }

    /**
     * Prune stored webhook-call rows past their retention window.
     */
    public static function cleanUp(): void
    {
        VatlyWebhookCall::cleanUp();
    }

    protected static function getFacadeAccessor(): string
    {
        return FluentVatly::class;
    }
}
