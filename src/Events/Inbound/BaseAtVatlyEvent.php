<?php

declare(strict_types=1);

namespace Vatly\Laravel\Events\Inbound;

//use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

//use Illuminate\Queue\SerializesModels;

abstract class BaseAtVatlyEvent
{
    use Dispatchable;
    //use InteractsWithSockets;
    //use SerializesModels;

    abstract public static function fromWebhookCall(VatlyWebhookCallReceived $callReceived): self;
}
