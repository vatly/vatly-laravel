<?php

declare(strict_types=1);

namespace Vatly\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Vatly;
use Vatly\Laravel\Models\VatlyWebhookCall;

class VatlyHelpers
{
    /**
     * Swap the Vatly composition root for a {@see FakeVatly} and bind it into
     * the container — the Cashier-style one-liner for feature tests.
     *
     * Every `subscribe()` / `checkout()` / `subscription()` call made through
     * the `Billable` trait then routes through recording fakes instead of the
     * real API, so a test scripts only what it cares about and asserts against
     * the returned fake — no hand-rolled Mockery stubs:
     *
     * ```php
     * $vatly = VatlyHelpers::fake();
     *
     * $user->subscribe()->toPlan('plan_pro')->withTrialDays(14)->create();
     *
     * $vatly->assertSubscriptionCreated('plan_pro');
     * ```
     */
    public static function fake(): FakeVatly
    {
        $fake = new FakeVatly;

        app()->instance(Vatly::class, $fake);

        return $fake;
    }

    /**
     * Get the billable instance by its Vatly customer ID.
     */
    public static function findBillable(string $vatlyCustomerId): ?Model
    {
        $billableModel = app()->make(VatlyConfig::class)->getBillableModel();

        return $billableModel::where('vatly_id', $vatlyCustomerId)->first();
    }

    /**
     * Get the billable instance by its Vatly customer ID.
     *
     * @throws ModelNotFoundException
     */
    public static function findBillableOrFail(string $vatlyCustomerId): Model
    {
        $billableModel = app()->make(VatlyConfig::class)->getBillableModel();

        return $billableModel::where('vatly_id', $vatlyCustomerId)->firstOrFail();
    }

    public static function cleanUp(): void
    {
        VatlyWebhookCall::cleanUp();
    }
}
