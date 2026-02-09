<?php

declare(strict_types=1);

namespace Vatly\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Vatly\Laravel\Exceptions\VatlyWebhookSignatureException;
use Vatly\Laravel\VatlyConfig;
use Vatly\Laravel\VatlyHelpers;

class VerifyVatlyWebhookSignature
{
    const X_VATLY_SIGNATURE_HEADER_KEY = 'X-Vatly-Signature';

    public function __construct(
        protected readonly VatlyConfig $vatlyConfig,
    ) {
        //
    }

    /**
     * Middleware that checks if the request has a valid Vatly webhook signature.
     * Note that this middleware is skipped if no webhook secret is set.
     *
     * @throws \Vatly\Laravel\Exceptions\VatlyWebhookSignatureException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = $this->vatlyConfig->getWebhookSecret();

        if (empty($secret)) {
            return $next($request);
        }

        if (! $request->hasHeader(self::X_VATLY_SIGNATURE_HEADER_KEY)) {
            throw VatlyWebhookSignatureException::missingSignature();
        }

        $signature = $request->header(self::X_VATLY_SIGNATURE_HEADER_KEY);
        $jsonPayload = (string) $request->getContent();

        if (! VatlyHelpers::verifyWebhookSignature(
            webhookSignature: $signature,
            jsonPayload: $jsonPayload,
            secret: $secret,
        )) {
            throw VatlyWebhookSignatureException::invalidSignature();
        }

        return $next($request);
    }
}
