<?php

declare(strict_types=1);

namespace Vatly\Laravel\Exceptions;

use Illuminate\Http\JsonResponse;

class VatlyWebhookSignatureException extends BaseVatlyException
{
    protected int $errorCode = 401; // Unauthorized

    public static function missingSignature(): self
    {
        return new self('Missing Vatly webhook signature.');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid Vatly webhook signature.');
    }

    public function report(): ?bool
    {
        return true;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], $this->errorCode);
    }
}
