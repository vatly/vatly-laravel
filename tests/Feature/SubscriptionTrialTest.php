<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Vatly\API\Resources\Checkout;
use Vatly\Fluent\Testing\FakeCheckout;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\VatlyHelpers;

class SubscriptionTrialTest extends BaseTestCase
{
    use RefreshDatabase;

    private FakeVatly $vatly;

    /** @var array<string, mixed>|null */
    private ?array $capturedPayload = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vatly = VatlyHelpers::fake();
        $this->vatly->onSubscriptionCreate(function (string $planId, array $payload): Checkout {
            $this->capturedPayload = $payload;

            return FakeCheckout::make('https://checkout.vatly.test/chk_trial');
        });
    }

    public function test_with_trial_days_reaches_the_subscription_payload(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_trial']);

        $checkout = $user->subscribe()
            ->toPlan('plan_pro')
            ->withTrialDays(14)
            ->create();

        $this->assertSame('https://checkout.vatly.test/chk_trial', $checkout->links->checkoutUrl->href);
        $this->assertSame('plan_pro', $this->capturedPayload['id']);
        $this->assertSame(14, $this->capturedPayload['trialDays']);
        $this->vatly->assertSubscriptionCreated('plan_pro');
    }

    public function test_with_trial_ends_at_rounds_up_to_whole_days(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_trial']);

        // Testbench runs in UTC, so 30 calendar days is exactly 30 * 86400s;
        // fluent rounds the remaining time up to whole days.
        $user->subscribe()
            ->toPlan('plan_pro')
            ->withTrialEndsAt(Carbon::now()->addDays(30))
            ->create();

        $this->assertSame(30, $this->capturedPayload['trialDays']);
    }

    public function test_a_plain_subscription_carries_no_trial_days(): void
    {
        $user = User::factory()->create(['vatly_id' => 'customer_plain']);

        $user->subscribe()
            ->toPlan('plan_basic')
            ->create();

        $this->assertIsArray($this->capturedPayload);
        $this->assertArrayNotHasKey('trialDays', $this->capturedPayload);
    }
}
