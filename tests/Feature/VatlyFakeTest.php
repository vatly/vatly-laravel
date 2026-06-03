<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vatly\Fluent\Testing\FakeVatly;
use Vatly\Fluent\Vatly;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\VatlyHelpers;

class VatlyFakeTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_fake_binds_a_fake_vatly_into_the_container(): void
    {
        $vatly = VatlyHelpers::fake();

        $this->assertInstanceOf(FakeVatly::class, $vatly);
        $this->assertSame($vatly, app(Vatly::class));
    }

    public function test_fake_records_checkouts_created_through_billable(): void
    {
        $vatly = VatlyHelpers::fake();
        $user = User::factory()->create(['vatly_id' => 'customer_x']);

        $user->checkout()->create(
            items: [['id' => 'plan_pro', 'quantity' => 1]],
            redirectUrlSuccess: 'https://example.com/success',
            redirectUrlCanceled: 'https://example.com/canceled',
        );

        $vatly->assertCheckoutCreated('plan_pro');
        $vatly->assertNothingCanceled();
    }

    public function test_fake_asserts_nothing_created_when_idle(): void
    {
        $vatly = VatlyHelpers::fake();

        $vatly->assertNothingCreated();
    }
}
