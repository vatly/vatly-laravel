<?php

declare(strict_types=1);

namespace Vatly\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Vatly\Fluent\Exceptions\InvalidWebhookSignatureException;
use Vatly\Fluent\Webhooks\SignatureVerifier;
use Vatly\Fluent\Webhooks\WebhookProcessor;

class VatlyInboundWebhookController
{
    public function __construct(
        private readonly WebhookProcessor $processor,
    ) {
        //
    }

    public function __invoke(Request $request): Response
    {
        if (config('app.debug')) {
            Log::info('Vatly Webhook request received!', $request->all());
        }

        try {
            $this->processor->handle(
                payload: (string) $request->getContent(),
                signature: (string) $request->header(SignatureVerifier::SIGNATURE_HEADER_NAME, ''),
            );
        } catch (InvalidWebhookSignatureException $exception) {
            // No secret configured: refuse without processing, and answer 503
            // so Vatly retries the delivery once VATLY_WEBHOOK_SECRET is set.
            if ($exception->isSecretNotConfigured()) {
                return response('webhook_secret_not_configured', 503);
            }

            return response('', 403);
        }

        return response('', 201);
    }
}
