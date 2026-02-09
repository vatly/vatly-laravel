<?php

declare(strict_types=1);

namespace Vatly\Laravel\VatlyApiActions;

use Vatly\API\VatlyApiClient;

abstract class BaseVatlyApiAction
{
    public function __construct(
        protected readonly VatlyApiClient $vatlyApiClient,
    ) {
        //
    }
}
