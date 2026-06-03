<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories\Concerns;

use Vatly\API\Types\Money;
use Vatly\API\Types\TaxSummaryCollection;
use Vatly\API\Types\TaxSummaryRate;

/**
 * Serializes an api-php {@see TaxSummaryCollection} into the flat array stored
 * in the `tax_summary` JSON column.
 *
 * The collection's items carry a {@see TaxSummaryRate} and a
 * {@see Money} amount; we flatten each item to a stable,
 * driver-owned JSON shape:
 *
 *   [
 *     'taxRate' => ['name' => string, 'percentage' => float, 'taxablePercentage' => float],
 *     'amount'  => int,    // integer cents, via Money::toCents()
 *     'currency'=> string, // ISO currency of the amount
 *   ]
 *
 * Note: the collection is not Countable, so we iterate `->items` directly.
 */
trait SerializesTaxSummary
{
    /**
     * @return array<int, array{taxRate: array{name: string, percentage: float, taxablePercentage: float}, amount: int, currency: string}>|null
     */
    protected function serializeTaxSummary(?TaxSummaryCollection $taxSummary): ?array
    {
        if ($taxSummary === null) {
            return null;
        }

        $serialized = [];

        foreach ($taxSummary->items as $item) {
            $serialized[] = [
                'taxRate' => [
                    'name' => $item->taxRate->name,
                    'percentage' => $item->taxRate->percentage,
                    'taxablePercentage' => $item->taxRate->taxablePercentage,
                ],
                'amount' => $item->amount->toCents(),
                'currency' => $item->amount->currency,
            ];
        }

        return $serialized;
    }
}
