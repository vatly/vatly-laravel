<?php

declare(strict_types=1);

namespace Vatly\Laravel\Exceptions;

class InvalidCustomer extends BaseVatlyException
{
    public static function notYetCreated($owner): self
    {
        return new static(class_basename($owner).' is not a Vatly customer yet. See the createAsVatlyCustomer method.');
    }
}
