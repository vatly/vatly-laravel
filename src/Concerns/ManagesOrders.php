<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Vatly\API\VatlyApiClient;
use Vatly\Laravel\Models\Order;

trait ManagesOrders
{
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'owner')->orderByDesc('created_at');
    }

    /**
     * Get the invoice URL for a specific order.
     */
    public function getInvoiceUrl(string $orderId): ?string
    {
        /** @var VatlyApiClient $client */
        $client = app()->make(VatlyApiClient::class);
        $order = $client->orders->get($orderId);

        return $order->links->invoice->href ?? null;
    }
}
