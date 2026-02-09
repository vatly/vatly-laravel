<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Contracts\CustomerRepositoryInterface;
use Vatly\Contracts\EventDispatcherInterface;
use Vatly\Events\LocalSubscriptionCreated;
use Vatly\Events\SubscriptionStarted;
use Vatly\Laravel\Models\Subscription;

class StartSubscriptionListener
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        //
    }

    public function handle(SubscriptionStarted $event): Subscription
    {
        $owner = $this->customerRepository->findByVatlyIdOrFail($event->customerId);

        $subscription = Subscription::createFromWebhookEvent($event, $owner);

        // Dispatch local event for application use
        $this->dispatcher->dispatch(new LocalSubscriptionCreated($subscription));

        return $subscription;
    }
}
