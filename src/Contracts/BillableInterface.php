<?php

declare(strict_types=1);

namespace Vatly\Laravel\Contracts;

use Vatly\Fluent\Contracts\BillableInterface as FluentBillableInterface;

/**
 * Interface for entities that can be billed (customers).
 *
 * Implement this interface on your User model or any entity
 * that should be able to subscribe and make payments.
 *
 * @see \Vatly\Laravel\Billable for the trait that provides the implementation
 */
interface BillableInterface extends FluentBillableInterface
{
    // Inherits all methods from Fluent BillableInterface
    // This wrapper exists so users only need to import from Vatly\Laravel namespace
}
