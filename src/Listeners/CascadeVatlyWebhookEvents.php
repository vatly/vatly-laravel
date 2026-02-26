<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Vatly\Fluent\Contracts\EventDispatcherInterface;
use Vatly\Fluent\Events\WebhookReceived;
use Vatly\Fluent\Webhooks\WebhookEventFactory;

class CascadeVatlyWebhookEvents
{
    public function __construct(
        private readonly WebhookEventFactory $eventFactory,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        //
    }

    public function handle(WebhookReceived $webhook): object
    {
        $event = $this->eventFactory->createFromWebhook($webhook);

        $this->dispatcher->dispatch($event);

        return $event;
    }
}
