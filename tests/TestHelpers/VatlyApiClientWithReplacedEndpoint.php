<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\TestHelpers;

use Vatly\API\Endpoints\BaseEndpoint;
use Vatly\API\VatlyApiClient;

class VatlyApiClientWithReplacedEndpoint extends VatlyApiClient
{
    public static function createAndReplaceEndpoint(string $endpointName, BaseEndpoint $endpoint)
    {
        $result = new static;

        $result->{$endpointName} = $endpoint;

        return $result;
    }
}
