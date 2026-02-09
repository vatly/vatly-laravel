<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Builders;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Vatly\Laravel\Builders\CheckoutBuilder;
use Vatly\Laravel\Builders\SubscriptionBuilder;
use Vatly\Laravel\Tests\BaseTestCase;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckout;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckoutResponse;
use Vatly\Laravel\VatlyConfig;

class SubscriptionBuilderTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->vatlyConfig = new VatlyConfig;
    }

    /** @test */
    public function it_instantiates(): void
    {
        $this->assertInstanceOf(SubscriptionBuilder::class, $this->getSubscriptionBuilder());
    }

    /** @test */
    public function it_can_create_minimum_payload(): void
    {
        $this->mock(CreateVatlyCheckout::class, function (MockInterface $mock) {
            $mock
                ->shouldReceive('execute')
                ->with([
                    'products' => [
                        [
                            'id' => 'subscription_foo_bar',
                            'quantity' => 1,
                        ],
                    ],
                    'redirectUrlSuccess' => $this->vatlyConfig->getDefaultRedirectUrlSuccess(),
                    'redirectUrlCanceled' => $this->vatlyConfig->getDefaultRedirectUrlCanceled(),
                ])
                ->andReturn(new CreateVatlyCheckoutResponse(
                    checkoutId: 'checkout_dummy_1',
                    checkoutUrl: 'https://vatly.com/fake-checkout-url-1',
                )
                );
        });

        $result = $this->getSubscriptionBuilder()
            ->toPlan('subscription_foo_bar')
            ->create();

        $this->assertInstanceOf(CreateVatlyCheckoutResponse::class, $result);
    }

    /** @test */
    public function it_can_set_the_quantity(): void
    {
        $this->mock(CreateVatlyCheckout::class, function (MockInterface $mock) {
            $mock
                ->shouldReceive('execute')
                ->with([
                    'products' => [
                        [
                            'id' => 'subscription_foo_bar',
                            'quantity' => 3,
                        ],
                    ],
                    'redirectUrlSuccess' => $this->vatlyConfig->getDefaultRedirectUrlSuccess(),
                    'redirectUrlCanceled' => $this->vatlyConfig->getDefaultRedirectUrlCanceled(),
                ])
                ->andReturn(new CreateVatlyCheckoutResponse(
                    checkoutId: 'checkout_dummy_1',
                    checkoutUrl: 'https://vatly.com/fake-checkout-url-1',
                )
                );
        });

        $result = $this->getSubscriptionBuilder()
            ->toPlan('subscription_foo_bar')
            ->withQuantity(3)
            ->create();

        $this->assertInstanceOf(CreateVatlyCheckoutResponse::class, $result);
    }

    /** @test */
    public function it_can_override_default_redirect_urls(): void
    {
        $this->mock(CreateVatlyCheckout::class, function (MockInterface $mock) {
            $mock
                ->shouldReceive('execute')
                ->with([
                    'products' => [
                        [
                            'id' => 'subscription_foo_bar',
                            'quantity' => 1,
                        ],
                    ],
                    'redirectUrlSuccess' => 'https://www.sandorian.com/success',
                    'redirectUrlCanceled' => 'https://www.sandorian.com/canceled',
                ])
                ->andReturn(new CreateVatlyCheckoutResponse(
                    checkoutId: 'checkout_dummy_1',
                    checkoutUrl: 'https://vatly.com/fake-checkout-url-1',
                )
                );
        });

        $result = $this->getSubscriptionBuilder()
            ->toPlan('subscription_foo_bar')
            ->withRedirectUrlSuccess('https://www.sandorian.com/success')
            ->withRedirectUrlCanceled('https://www.sandorian.com/canceled')
            ->create();

        $this->assertInstanceOf(CreateVatlyCheckoutResponse::class, $result);
    }

    private function getSubscriptionBuilder(): SubscriptionBuilder
    {
        return new SubscriptionBuilder(
            vatlyConfig: app()->make(VatlyConfig::class),
            owner: $this->owner,
            checkoutBuilder: new CheckoutBuilder($this->owner, app()->make(CreateVatlyCheckout::class)),
        );
    }
}
