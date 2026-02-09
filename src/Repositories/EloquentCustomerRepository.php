<?php

declare(strict_types=1);

namespace Vatly\Laravel\Repositories;

use Vatly\Contracts\BillableInterface;
use Vatly\Contracts\CustomerRepositoryInterface;
use Vatly\Exceptions\InvalidCustomerException;
use Vatly\Laravel\VatlyConfig;

class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        private readonly VatlyConfig $config,
    ) {
        //
    }

    public function findByVatlyId(string $vatlyId): ?BillableInterface
    {
        $model = $this->config->getBillableModel();

        return $model::where('vatly_id', $vatlyId)->first();
    }

    public function findByVatlyIdOrFail(string $vatlyId): BillableInterface
    {
        $billable = $this->findByVatlyId($vatlyId);

        if ($billable === null) {
            throw InvalidCustomerException::notFound($vatlyId);
        }

        return $billable;
    }

    public function save(BillableInterface $billable): void
    {
        $billable->save();
    }
}
