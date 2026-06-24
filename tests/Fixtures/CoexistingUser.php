<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Vatly\Laravel\Billable;
use Vatly\Laravel\VatlyBillable;

/**
 * Stand-in for another Cashier-style `Billable` trait (Laravel Cashier for
 * Stripe/Paddle, or Lemon Squeezy for Laravel): it declares exactly the method
 * names that would collide with Vatly's unprefixed {@see Billable}.
 *
 * The return values are deliberately distinct from Vatly's so a test can prove
 * which trait a given call routes to.
 */
trait FakeForeignBillable
{
    public function subscriptions(): string
    {
        return 'foreign:subscriptions';
    }

    public function subscription(string $type = 'default'): string
    {
        return 'foreign:subscription';
    }

    public function subscribed(string $type = 'default'): bool
    {
        return true;
    }

    public function subscribe(): string
    {
        return 'foreign:subscribe';
    }

    public function checkout(): string
    {
        return 'foreign:checkout';
    }

    public function orders(): string
    {
        return 'foreign:orders';
    }
}

/**
 * A billable model that carries a foreign Cashier-style trait *and* Vatly's
 * coexistence trait ({@see VatlyBillable}) at the same time — the exact
 * side-by-side scenario.
 *
 * That this class loads at all is half the proof: PHP fatals on a trait method
 * collision at class-definition time, so a green test suite means the two traits
 * compose cleanly.
 */
class CoexistingUser extends Authenticatable
{
    use FakeForeignBillable;
    use VatlyBillable;

    protected $table = 'users';

    protected $guarded = [];
}
