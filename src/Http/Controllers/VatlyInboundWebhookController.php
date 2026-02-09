<?php

declare(strict_types=1);

namespace Vatly\Laravel\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Vatly\Contracts\EventDispatcherInterface;
use Vatly\Contracts\WebhookCallRepositoryInterface;
use Vatly\Events\WebhookReceived;
use Vatly\Webhooks\WebhookEventFactory;

class VatlyInboundWebhookController
{
    public function __construct(
        private readonly WebhookEventFactory $eventFactory,
        private readonly WebhookCallRepositoryInterface $webhookCallRepository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        //
    }

    public function __invoke(Request $request): Response
    {
        if (config('app.debug')) {
            Log::info('Vatly Webhook request received!', $request->all());
        }

        $resourceId = $request->get('resourceId');

        if (!empty($resourceId)) {
            $event = $this->eventFactory->parsePayload($request->all());

            // Record the webhook call
            $this->webhookCallRepository->record(
                eventName: $event->eventName,
                resourceId: $event->resourceId,
                resourceName: $event->resourceName,
                payload: $event->object,
                raisedAt: new DateTimeImmutable($event->raisedAt),
                testmode: $event->testmode,
                vatlyCustomerId: $event->getCustomerId(),
            );

            // Dispatch the event
            $this->dispatcher->dispatch($event);
        }

        return response(status: 201);
    }
}
