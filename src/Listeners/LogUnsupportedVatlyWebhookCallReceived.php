<?php

declare(strict_types=1);

namespace Vatly\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use Vatly\Laravel\Events\Inbound\UnsupportedVatlyWebhookCallReceived;

class LogUnsupportedVatlyWebhookCallReceived
{
    public function handleUnsupportedVatlyWebhookCallReceived(UnsupportedVatlyWebhookCallReceived $callReceived): void
    {
        if (config('app.debug')) {
            Log::info('Unknown or irrelevant Vatly webhook event '.$callReceived->vatlyEventName().' received.', (array) $callReceived);
        }
    }
}
