<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Vatly\Fluent\Actions\CreateCheckout;
use Vatly\Fluent\Builders\CheckoutBuilder;
use Vatly\Fluent\Builders\SubscriptionBuilder;
use Vatly\Fluent\Contracts\ConfigurationInterface;

trait ManagesCheckouts
{
    public function checkout(): CheckoutBuilder
    {
        $this->ensureHasVatlyCustomer();

        return new CheckoutBuilder(
            owner: $this,
            createCheckout: app()->make(CreateCheckout::class),
        );
    }

    public function subscribe(): SubscriptionBuilder
    {
        $this->ensureHasVatlyCustomer();

        return new SubscriptionBuilder(
            config: app()->make(ConfigurationInterface::class),
            owner: $this,
            checkoutBuilder: $this->checkout(),
        );
    }
}
