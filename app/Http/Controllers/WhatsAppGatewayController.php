<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AntiBanTokenBucketService;
use App\Services\SpintaxTemplateEngine;
use App\Services\WhatsAppWebhookSignatureService;
use InvalidArgumentException;

final class WhatsAppGatewayController
{
    public function __construct(
        private readonly WhatsAppWebhookSignatureService $webhookService,
        private readonly SpintaxTemplateEngine $spintaxEngine,
        private readonly AntiBanTokenBucketService $rateLimiter
    ) {
    }

    /**
     * POST /api/v1/messages/dispatch
     * Normalize MSISDN, compile Spintax AST, evaluate token bucket, and route to healthiest session.
     *
     * @param array<string, mixed> $request
     * @param list<array<string, mixed>> $sessions
     * @return array<string, mixed>
     */
    public function dispatchMessage(array $request, array $sessions): array
    {
        $rawPhone = (string) ($request['phone'] ?? '');
        $template = (string) ($request['template'] ?? '');
        $variables = is_array($request['variables'] ?? null) ? $request['variables'] : [];
        $seed = isset($request['seed']) ? (int) $request['seed'] : 101;
        $isOtp = (bool) ($request['is_otp'] ?? false);

        $msisdnMeta = $this->webhookService->normalizeMsisdn($rawPhone);
        $compiled = $this->spintaxEngine->compile($template, $variables, $seed);

        if (!$compiled['formatting_valid']) {
            throw new InvalidArgumentException(
                'Unclosed WhatsApp formatting markers: ' . implode(', ', $compiled['unclosed_markers'])
            );
        }

        $route = $this->rateLimiter->selectSessionRoute($sessions, $msisdnMeta['carrier'], $isOtp);
        $typingEnvelope = $this->rateLimiter->computeHumanTypingEnvelope($compiled['compiled_text'], $seed);

        $bucketState = [
            'tokens'         => (float) ($request['bucket_tokens'] ?? 15.0),
            'last_refill_ts' => (float) ($request['bucket_last_ts'] ?? 1794200000.0),
        ];
        $nowTs = (float) ($request['now_ts'] ?? 1794200002.0);
        $bucket = $this->rateLimiter->consume($bucketState, 1.0, $nowTs, 20.0, 0.5);

        return [
            'status'          => $bucket['allowed'] ? 'QUEUED_FOR_DISPATCH' : 'THROTTLED_TOKEN_BUCKET',
            'recipient'       => $msisdnMeta,
            'message'         => $compiled,
            'routing'         => $route,
            'anti_ban_jitter' => $typingEnvelope,
            'rate_limit'      => $bucket,
        ];
    }

    /**
     * POST /api/v1/otp/issue
     * Issue stateless RFC 6238 HMAC-SHA256 OTP and compile localized WhatsApp authentication message.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function issueOtp(array $request, string $secret): array
    {
        $msisdnMeta = $this->webhookService->normalizeMsisdn((string) ($request['phone'] ?? ''));
        $purpose = (string) ($request['purpose'] ?? 'login_2fa');
        $timestamp = (int) ($request['timestamp'] ?? time());

        $otpData = $this->spintaxEngine->generateStatelessOtp(
            $msisdnMeta['e164'],
            $purpose,
            $timestamp,
            $secret,
            6,
            300
        );

        $template = '{Kode OTP|Kode Verifikasi|Token Keamanan} *{{app_name}}* Anda adalah *{{otp}}*. {Jangan berikan kode ini kepada siapapun|Rahasiakan kode ini dari pihak manapun}. Berlaku 5 menit.';
        $compiled = $this->spintaxEngine->compile($template, [
            'app_name' => (string) ($request['app_name'] ?? 'KujangWA'),
            'otp'      => $otpData['otp'],
        ], $timestamp);

        return [
            'status'    => 'OTP_MINTED',
            'recipient' => $msisdnMeta,
            'otp_meta'  => $otpData,
            'payload'   => $compiled['compiled_text'],
        ];
    }

    /**
     * POST /api/v1/webhooks/meta-cloud
     * Verify X-Hub-Signature-256 and parse inbound Meta WhatsApp Cloud API events.
     *
     * @return array<string, mixed>
     */
    public function handleMetaWebhook(string $rawBody, string $signatureHeader, string $appSecret): array
    {
        if (!$this->webhookService->verifyMetaWebhook($rawBody, $signatureHeader, $appSecret)) {
            return [
                'status' => 'REJECTED',
                'reason' => 'INVALID_X_HUB_SIGNATURE_256',
            ];
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return [
                'status' => 'REJECTED',
                'reason' => 'MALFORMED_JSON',
            ];
        }

        $parsed = $this->webhookService->parseGraphWebhookPayload($decoded);

        return [
            'status'         => 'ACCEPTED',
            'messages_count' => count($parsed['messages']),
            'statuses_count' => count($parsed['statuses']),
            'events'         => $parsed,
        ];
    }
}
