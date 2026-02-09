<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Vatly\API\VatlyApiClient;

trait ManagesApiClient
{
    protected function vatlyApiClient(): VatlyApiClient
    {
        return app()->make(VatlyApiClient::class);
    }
}
