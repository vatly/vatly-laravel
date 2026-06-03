<?php

declare(strict_types=1);

namespace Vatly\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Vatly as FluentVatly;
use Vatly\Laravel\VatlyHelpers;

/**
 * Facade over the Vatly composition root ({@see FluentVatly}).
 *
 * Lets application code reach the framework-agnostic API through the brand
 * name — `Vatly::order($order)`, `Vatly::subscription($subscription)`, … —
 * and adds the Cashier-style {@see self::fake()} test helper.
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
     * tests. Delegates to {@see VatlyHelpers::fake()}.
     *
     * Every `subscribe()` / `checkout()` / `subscription()` call made through
     * the `Billable` trait — and every `Vatly::…` facade call — then routes
     * through recording fakes instead of the real API, so a test scripts only
     * what it cares about and asserts against the returned fake.
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
        $fake = VatlyHelpers::fake();

        // Drop any instance this facade already resolved so subsequent
        // `Vatly::…` calls proxy through the freshly-bound fake.
        static::clearResolvedInstance(static::getFacadeAccessor());

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return FluentVatly::class;
    }
}
