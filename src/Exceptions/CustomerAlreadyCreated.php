<?php

declare(strict_types=1);

namespace Vatly\Laravel\Exceptions;

use Illuminate\Database\Eloquent\Model;

class CustomerAlreadyCreated extends BaseVatlyException
{
    public static function exists(Model $owner)
    {
        return new static(class_basename($owner)." is already a Vatly customer with ID {$owner->vatly_id}.");
    }
}
