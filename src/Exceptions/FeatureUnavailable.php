<?php

declare(strict_types=1);

namespace Vatly\Laravel\Exceptions;

class FeatureUnavailable extends BaseVatlyException
{
    public static function toBeImplementedOnApi()
    {
        return new static('This feature is not available yet on the Vatly API.');
    }

    public static function toBeImplementedOnVatlyLaravelPackage()
    {
        return new static('This feature is not available yet on the Vatly Laravel package. Feel free to submit a PR.');
    }
}
