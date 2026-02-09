<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

class CancelVatlySubscription extends BaseVatlyApiAction
{
    public function execute(string $subscriptionId): void
    {
        $this->vatlyApiClient->subscriptions->cancel($subscriptionId);
    }
}
