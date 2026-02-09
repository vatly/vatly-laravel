<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Vatly\Actions\CreateCheckout;
use Vatly\Builders\CheckoutBuilder;
use Vatly\Builders\SubscriptionBuilder;
use Vatly\Contracts\ConfigurationInterface;

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
