<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class WhatsAppWebhookSignatureService
{
    /**
     * Indonesian Telco Prefix Matrix (4-digit prefix after 62)
     *
     * @var array<string, list<string>>
     */
    private const ID_CARRIER_PREFIXES = [
        'Telkomsel' => ['62811', '62812', '62813', '62821', '62822', '62823', '62851', '62852', '62853'],
        'Indosat'   => ['62814', '62815', '62816', '62855', '62856', '62857', '62858'],
        'XL_Axis'   => ['62817', '62818', '62819', '62859', '62877', '62878', '62831', '62832', '62833', '62838'],
        'Three'     => ['62895', '62896', '62897', '62898', '62899'],
        'Smartfren' => ['62881', '62882', '62883', '62884', '62885', '62886', '62887', '62888', '62889'],
    ];

    /**
     * Normalize raw Indonesian/international phone number or WhatsApp JID into canonical E.164 + carrier metadata.
     *
     * @return array{e164: string, jid: string, carrier: string, country_code: string, valid: bool}
     */
    public function normalizeMsisdn(string $rawPhone): array
    {
        $stripped = trim($rawPhone);
        if (str_contains($stripped, '@')) {
            $stripped = explode('@', $stripped)[0];
        }

        $digits = preg_replace('/\D+/', '', $stripped) ?? '';
        if ($digits === '') {
            throw new InvalidArgumentException('Phone number cannot be empty.');
        }

        if (str_starts_with($digits, '08')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8') && strlen($digits) >= 9 && strlen($digits) <= 13) {
            $digits = '62' . $digits;
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $len = strlen($digits);
        if ($len < 10 || $len > 15) {
            throw new InvalidArgumentException(sprintf('Invalid E.164 digit length (%d): %s', $len, $digits));
        }

        $countryCode = str_starts_with($digits, '62') ? 'ID' : 'INTL';
        $carrier = 'International';

        if ($countryCode === 'ID') {
            $prefix5 = substr($digits, 0, 5);
            $carrier = 'ID_Other';
            foreach (self::ID_CARRIER_PREFIXES as $carrierName => $prefixes) {
                if (in_array($prefix5, $prefixes, true)) {
                    $carrier = $carrierName;
                    break;
                }
            }
        }

        return [
            'e164'         => $digits,
            'jid'          => $digits . '@s.whatsapp.net',
            'carrier'      => $carrier,
            'country_code' => $countryCode,
            'valid'        => true,
        ];
    }

    /**
     * Generate Meta WhatsApp Cloud API X-Hub-Signature-256 header value.
     */
    public function signMetaWebhook(string $rawBody, string $appSecret): string
    {
        return 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    }

    /**
     * Verify Meta WhatsApp Cloud API X-Hub-Signature-256 header in constant time.
     */
    public function verifyMetaWebhook(string $rawBody, string $signatureHeader, string $appSecret): bool
    {
        $trimmed = trim($signatureHeader);
        if (!str_starts_with($trimmed, 'sha256=')) {
            return false;
        }
        $expected = $this->signMetaWebhook($rawBody, $appSecret);
        return hash_equals($expected, $trimmed);
    }

    /**
     * Sign self-hosted bridge webhook payload (Baileys / GoWA / WuzAPI) with timestamp replay binding.
     */
    public function signBridgeWebhook(string $rawBody, int $timestamp, string $secret): string
    {
        $canonical = sprintf('v1:%d:%s', $timestamp, $rawBody);
        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Verify self-hosted bridge webhook with timestamp replay window protection.
     *
     * @return array{valid: bool, reason: string, skew_seconds: int}
     */
    public function verifyBridgeWebhook(
        string $rawBody,
        int $timestamp,
        string $signatureHex,
        string $secret,
        int $nowTimestamp,
        int $toleranceSeconds = 300
    ): array {
        $skew = abs($nowTimestamp - $timestamp);
        if ($skew > $toleranceSeconds) {
            return [
                'valid'        => false,
                'reason'       => 'TIMESTAMP_OUT_OF_WINDOW',
                'skew_seconds' => $skew,
            ];
        }

        $expected = $this->signBridgeWebhook($rawBody, $timestamp, $secret);
        if (!hash_equals($expected, strtolower(trim($signatureHex)))) {
            return [
                'valid'        => false,
                'reason'       => 'INVALID_HMAC_SIGNATURE',
                'skew_seconds' => $skew,
            ];
        }

        return [
            'valid'        => true,
            'reason'       => 'VERIFIED',
            'skew_seconds' => $skew,
        ];
    }

    /**
     * Parse Meta WhatsApp Cloud API webhook payload into normalized messages and delivery receipts.
     *
     * @param array<string, mixed> $payload
     * @return array{messages: list<array<string, mixed>>, statuses: list<array<string, mixed>>}
     */
    public function parseGraphWebhookPayload(array $payload): array
    {
        $messages = [];
        $statuses = [];

        $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
        foreach ($entries as $entry) {
            $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
            foreach ($changes as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                $phoneId = (string) ($value['metadata']['phone_number_id'] ?? 'unknown');

                $rawMessages = is_array($value['messages'] ?? null) ? $value['messages'] : [];
                foreach ($rawMessages as $msg) {
                    $type = (string) ($msg['type'] ?? 'text');
                    $textBody = '';
                    $buttonId = null;

                    if ($type === 'text') {
                        $textBody = (string) ($msg['text']['body'] ?? '');
                    } elseif ($type === 'interactive') {
                        $interType = (string) ($msg['interactive']['type'] ?? '');
                        if ($interType === 'button_reply') {
                            $buttonId = (string) ($msg['interactive']['button_reply']['id'] ?? '');
                            $textBody = (string) ($msg['interactive']['button_reply']['title'] ?? '');
                        } elseif ($interType === 'list_reply') {
                            $buttonId = (string) ($msg['interactive']['list_reply']['id'] ?? '');
                            $textBody = (string) ($msg['interactive']['list_reply']['title'] ?? '');
                        }
                    }

                    $fromRaw = (string) ($msg['from'] ?? '');
                    $norm = $fromRaw !== '' ? $this->normalizeMsisdn($fromRaw) : null;

                    $messages[] = [
                        'wamid'           => (string) ($msg['id'] ?? ''),
                        'phone_number_id' => $phoneId,
                        'from_e164'       => $norm['e164'] ?? $fromRaw,
                        'carrier'         => $norm['carrier'] ?? 'Unknown',
                        'type'            => $type,
                        'body'            => $textBody,
                        'interactive_id'  => $buttonId,
                        'timestamp'       => (int) ($msg['timestamp'] ?? 0),
                    ];
                }

                $rawStatuses = is_array($value['statuses'] ?? null) ? $value['statuses'] : [];
                foreach ($rawStatuses as $st) {
                    $statuses[] = [
                        'wamid'        => (string) ($st['id'] ?? ''),
                        'recipient_id' => (string) ($st['recipient_id'] ?? ''),
                        'status'       => (string) ($st['status'] ?? 'unknown'),
                        'timestamp'    => (int) ($st['timestamp'] ?? 0),
                        'billable'     => (bool) ($st['pricing']['billable'] ?? false),
                        'category'     => (string) ($st['pricing']['category'] ?? 'utility'),
                    ];
                }
            }
        }

        return [
            'messages' => $messages,
            'statuses' => $statuses,
        ];
    }
}
