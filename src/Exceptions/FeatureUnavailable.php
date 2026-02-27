<?php

declare(strict_types=1);

namespace Vatly\Laravel\Exceptions;

class FeatureUnavailable extends BaseVatlyException
{
    /**
     * @return static
     */
    public static function toBeImplementedOnApi(): static
    {
        return new static('This feature is not available yet on the Vatly API.');
    }

    /**
     * @return static
     */
    public static function toBeImplementedOnVatlyLaravelPackage(): static
    {
        return new static('This feature is not available yet on the Vatly Laravel package. Feel free to submit a PR.');
    }
}
