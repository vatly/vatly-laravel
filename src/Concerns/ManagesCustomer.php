<?php

declare(strict_types=1);

namespace Vatly\Laravel\Concerns;

use Vatly\Actions\CreateCustomer;
use Vatly\Actions\GetCustomer;
use Vatly\Actions\Responses\CreateCustomerResponse;
use Vatly\Actions\Responses\CustomerResponse;
use Vatly\Actions\Responses\GetCustomerResponse;
use Vatly\Exceptions\CustomerAlreadyCreatedException;
use Vatly\Exceptions\FeatureUnavailableException;
use Vatly\Exceptions\InvalidCustomerException;

/**
 * Provides BillableInterface implementation and customer management for Eloquent models.
 *
 * @property string|null $vatly_id
 * @property string|null $email
 * @property string|null $name
 *
 * @method static where(string $column, mixed $value)
 * @method bool saveQuietly()
 * @method mixed getKey()
 * @method string getMorphClass()
 */
trait ManagesCustomer
{
    // BillableInterface implementation

    public function getVatlyId(): ?string
    {
        return $this->vatly_id;
    }

    public function setVatlyId(string $id): void
    {
        $this->vatly_id = $id;
    }

    public function hasVatlyId(): bool
    {
        return $this->vatly_id !== null;
    }

    public function getVatlyEmail(): ?string
    {
        return $this->email ?? null;
    }

    public function getVatlyName(): ?string
    {
        return $this->name ?? null;
    }

    // Legacy aliases for backward compatibility

    public function vatlyId(): ?string
    {
        return $this->getVatlyId();
    }

    public function vatlyName(): ?string
    {
        return $this->getVatlyName();
    }

    public function vatlyEmail(): ?string
    {
        return $this->getVatlyEmail();
    }

    // Customer management

    public function assertCustomerExists(): void
    {
        if (!$this->hasVatlyId()) {
            throw InvalidCustomerException::notYetCreated($this);
        }
    }

    public function createAsVatlyCustomer(array $options = []): CreateCustomerResponse
    {
        if ($this->hasVatlyId()) {
            throw CustomerAlreadyCreatedException::exists($this);
        }

        if (!array_key_exists('email', $options) && $email = $this->getVatlyEmail()) {
            $options['email'] = $email;
        }

        if (!array_key_exists('name', $options) && $name = $this->getVatlyName()) {
            $options['name'] = $name;
        }

        /** @var CreateCustomerResponse $response */
        $response = app()->make(CreateCustomer::class)->execute($options);

        $this->vatly_id = $response->customerId;
        $this->saveQuietly();

        return $response;
    }

    public function updateVatlyCustomer(array $options = []): CustomerResponse
    {
        throw FeatureUnavailableException::notImplementedOnApi();
    }

    public function createOrGetVatlyCustomer(array $options = []): CustomerResponse
    {
        if ($this->hasVatlyId()) {
            return $this->asVatlyCustomer();
        }

        return $this->createAsVatlyCustomer($options);
    }

    public function ensureHasVatlyCustomer(array $createVatlyCustomerOptions = []): void
    {
        if ($this->hasVatlyId()) {
            return;
        }

        $this->createAsVatlyCustomer($createVatlyCustomerOptions);
    }

    public function updateOrCreateVatlyCustomer(array $options = []): CustomerResponse
    {
        if ($this->hasVatlyId()) {
            return $this->updateVatlyCustomer($options);
        }

        return $this->createAsVatlyCustomer($options);
    }

    public function syncOrCreateVatlyCustomer(array $options = []): CustomerResponse
    {
        if ($this->hasVatlyId()) {
            return $this->syncVatlyCustomerDetails();
        }

        return $this->createAsVatlyCustomer($options);
    }

    public function asVatlyCustomer(): GetCustomerResponse
    {
        $this->assertCustomerExists();

        return app()->make(GetCustomer::class)->execute($this->vatly_id);
    }

    public function syncVatlyCustomerDetails(): CustomerResponse
    {
        return $this->updateVatlyCustomer([
            'name' => $this->getVatlyName(),
            'email' => $this->getVatlyEmail(),
        ]);
    }

    public static function findByVatlyCustomerId(string $id): ?static
    {
        return static::where('vatly_id', $id)->first();
    }

    public static function findByVatlyCustomerIdOrFail(string $id): static
    {
        return static::where('vatly_id', $id)->firstOrFail();
    }
}
