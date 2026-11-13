<?php

declare(strict_types=1);

namespace Vatly\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Vatly\Fluent\Contracts\OrderLineInterface;

/**
 * The local representation of a single Vatly order line.
 *
 * Mirrors {@see Refund}: a thin read surface over the driver's own persisted
 * order-line row, populated from Vatly's `order.paid` webhook. The
 * order↔subscription link lives here, at the line level, as `subscription_id`
 * (Vatly's `relatedResources.subscriptionId`), whatever the line's product: an
 * initial signup line references the plan (`product_type` is
 * `subscription_plan`), while a renewal references the subscription itself
 * (`product_type` is `subscription`, `product_id` the `subscription_…` id).
 * Lines stored before Vatly carried the link have a null `subscription_id`.
 *
 * @property string $vatly_id The Vatly order-line id (the `order_item_…` id).
 * @property string $order_vatly_id The Vatly id of the parent order this line belongs to.
 * @property string $description
 * @property int $quantity
 * @property int $base_price Per-unit base price (before quantity), in integer cents.
 * @property int $total Gross line total (including tax), in integer cents.
 * @property int $subtotal Net line subtotal (net of tax), in integer cents.
 * @property array<int, array{taxRate: array{name: string, percentage: float, taxablePercentage: float}, amount: int, currency: string}>|null $tax_summary
 * @property string|null $product_type
 * @property string|null $product_id
 * @property string|null $subscription_id The Vatly id of the subscription this line belongs to.
 *
 * @method static create(array<string, mixed> $array)
 * @method static where(string $column, mixed $value)
 */
class OrderLine extends Model implements OrderLineInterface
{
    protected $table = 'vatly_order_lines';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'tax_summary' => 'array',
    ];

    // OrderLineInterface implementation

    public function getVatlyId(): string
    {
        return $this->vatly_id;
    }

    public function getOrderId(): string
    {
        return $this->order_vatly_id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): int
    {
        return (int) $this->quantity;
    }

    public function getBasePrice(): int
    {
        return (int) $this->base_price;
    }

    public function getTotal(): int
    {
        return (int) $this->total;
    }

    public function getSubtotal(): int
    {
        return (int) $this->subtotal;
    }

    public function getProductType(): ?string
    {
        return $this->product_type;
    }

    public function getProductId(): ?string
    {
        return $this->product_id;
    }

    public function getSubscriptionId(): ?string
    {
        return $this->subscription_id;
    }
}
