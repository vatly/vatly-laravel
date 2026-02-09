<?php

declare(strict_types=1);

namespace Vatly\Laravel\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Vatly\Laravel\Builders\Concerns\ManagesTestmode;
use Vatly\Laravel\Exceptions\IncompleteInformationException;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckout;
use Vatly\Laravel\VatlyApiActions\CreateVatlyCheckoutResponse;

class CheckoutBuilder
{
    use ManagesTestmode;

    protected string $redirectUrlSuccess;

    protected string $redirectUrlCanceled;

    protected ?array $metadata = null;

    protected Collection $items;

    public function __construct(
        protected Model $owner,
        protected readonly CreateVatlyCheckout $createVatlyCheckout,
    ) {
        $this->items = new Collection;
    }

    public function payload(array $overrides = [], bool $filtered = true): array
    {
        $payload = array_merge([
            'products' => $this->items->toArray(),
            'customerId' => $this->owner->vatlyId(),
            'redirectUrlSuccess' => $this->redirectUrlSuccess,
            'redirectUrlCanceled' => $this->redirectUrlCanceled,
            'testmode' => $this->testmode,
            'metadata' => $this->metadata,
        ], $overrides);

        return $filtered ? array_filter($payload) : $payload;
    }

    public function create(
        Collection $items,
        string $redirectUrlSuccess,
        string $redirectUrlCanceled,
        array $payloadOverrides = [],
    ): CreateVatlyCheckoutResponse {
        $this
            ->withTestmode($this->testmode)
            ->withItems($items)
            ->withRedirectUrlSuccess($redirectUrlSuccess)
            ->withRedirectUrlCanceled($redirectUrlCanceled);

        $payload = $this->payload(overrides: $payloadOverrides);

        throw_if(
            empty($payload['products']),
            IncompleteInformationException::noCheckoutItems()
        );

        return $this->createVatlyCheckout->execute(
            $payload,
        );
    }

    public function withRedirectUrlSuccess(string $url): self
    {
        $this->redirectUrlSuccess = $url;

        return $this;
    }

    public function withRedirectUrlCanceled(string $url): self
    {
        $this->redirectUrlCanceled = $url;

        return $this;
    }

    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function withItems(Collection $items): self
    {
        $items->each(fn ($item) => $this->items->add($item));

        return $this;
    }
}
