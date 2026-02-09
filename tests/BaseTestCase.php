<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests;

use Orchestra\Testbench\TestCase;
use Vatly\Laravel\VatlyServiceProvider;

abstract class BaseTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VatlyServiceProvider::class,
        ];
    }
}
