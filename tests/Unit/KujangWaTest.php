<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/WhatsAppWebhookSignatureService.php';
require_once __DIR__ . '/../../app/Services/SpintaxTemplateEngine.php';
require_once __DIR__ . '/../../app/Services/AntiBanTokenBucketService.php';
require_once __DIR__ . '/../../app/Http/Controllers/WhatsAppGatewayController.php';

use App\Http\Controllers\WhatsAppGatewayController;
use App\Services\AntiBanTokenBucketService;
use App\Services\SpintaxTemplateEngine;
use App\Services\WhatsAppWebhookSignatureService;

$passed = 0;
$failed = 0;

function assertTrue(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$label}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$label}\n";
    }
}

echo "=== KujangWA Cryptographic & Protocol Unit Test Suite ===\n";

$webhookSvc = new WhatsAppWebhookSignatureService();
$spintaxSvc = new SpintaxTemplateEngine();
$bucketSvc  = new AntiBanTokenBucketService();
$controller = new WhatsAppGatewayController($webhookSvc, $spintaxSvc, $bucketSvc);

// 1. E.164 MSISDN Normalizer & Indonesian Telco Carrier Detection
$tsel = $webhookSvc->normalizeMsisdn('0812-3456-7890');
assertTrue(
    $tsel['e164'] === '6281234567890' && $tsel['carrier'] === 'Telkomsel' && $tsel['jid'] === '6281234567890@s.whatsapp.net',
    'Normalize 0812-3456-7890 -> 6281234567890 (Telkomsel)'
);

$isat = $webhookSvc->normalizeMsisdn('+62 857 9876 5432');
assertTrue(
    $isat['e164'] === '6285798765432' && $isat['carrier'] === 'Indosat',
    'Normalize +62 857 9876 5432 -> 6285798765432 (Indosat)'
);

$xl = $webhookSvc->normalizeMsisdn('6281811223344@s.whatsapp.net');
assertTrue(
    $xl['e164'] === '6281811223344' && $xl['carrier'] === 'XL_Axis',
    'Strip @s.whatsapp.net JID and detect XL_Axis prefix (62818)'
);

// 2. Meta WhatsApp Cloud API X-Hub-Signature-256 HMAC Verification
$secret = 'whsec_kujangwa_meta_cloud_2026_secret';
$rawJson = json_encode([
    'object' => 'whatsapp_business_account',
    'entry' => [[
        'id' => 'WABA_01',
        'changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => '1092837465'],
                'messages' => [[
                    'id' => 'wamid.HBgNNjI4MTIzNDU2Nzg5MA==',
                    'from' => '081299887766',
                    'timestamp' => '1794200100',
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button_reply',
                        'button_reply' => ['id' => 'btn_confirm_pay', 'title' => 'Konfirmasi Bayar'],
                    ],
                ]],
                'statuses' => [[
                    'id' => 'wamid.HBgNNjI4MTIzNDU2Nzg5MA==',
                    'recipient_id' => '6281299887766',
                    'status' => 'read',
                    'timestamp' => '1794200105',
                    'pricing' => ['billable' => true, 'category' => 'authentication'],
                ]],
            ],
        ]],
    ]],
], JSON_THROW_ON_ERROR);

$validSig = $webhookSvc->signMetaWebhook($rawJson, $secret);
assertTrue(
    $webhookSvc->verifyMetaWebhook($rawJson, $validSig, $secret) === true,
    'Verify valid Meta X-Hub-Signature-256 HMAC-SHA256 digest'
);
assertTrue(
    $webhookSvc->verifyMetaWebhook($rawJson . 'tamper', $validSig, $secret) === false,
    'Reject tampered Meta webhook payload in constant time'
);

// 3. Bridge Webhook Timestamp Replay Window Protection
$bridgeSig = $webhookSvc->signBridgeWebhook($rawJson, 1794200000, $secret);
$bridgeOk = $webhookSvc->verifyBridgeWebhook($rawJson, 1794200000, $bridgeSig, $secret, 1794200120, 300);
assertTrue(
    $bridgeOk['valid'] === true && $bridgeOk['reason'] === 'VERIFIED',
    'Accept self-hosted bridge webhook within +-300s replay window'
);
$bridgeReplay = $webhookSvc->verifyBridgeWebhook($rawJson, 1794200000, $bridgeSig, $secret, 1794200600, 300);
assertTrue(
    $bridgeReplay['valid'] === false && $bridgeReplay['reason'] === 'TIMESTAMP_OUT_OF_WINDOW',
    'Reject replayed bridge webhook exceeding 300s clock skew'
);

// 4. Meta Graph Webhook Payload Parser (Interactive Button + Status Receipt)
$parsedEvents = $webhookSvc->parseGraphWebhookPayload(json_decode($rawJson, true));
assertTrue(
    count($parsedEvents['messages']) === 1
    && $parsedEvents['messages'][0]['interactive_id'] === 'btn_confirm_pay'
    && $parsedEvents['messages'][0]['from_e164'] === '6281299887766'
    && $parsedEvents['statuses'][0]['status'] === 'read',
    'Extract interactive button_reply ID and delivery read receipt from Graph API payload'
);

// 5. Recursive Nested Spintax AST Compiler & Exact Permutation Math
$template = '{Halo|Selamat {Pagi|Siang|Malam}} *{{nama}}*, tagihan #{{inv}} sebesar *Rp {{total}}* {sudah lunas|telah diterima}.';
// First block: Halo (1) + Selamat {Pagi|Siang|Malam} (3) = 4 branches; Second block: 2 branches -> 4 * 2 = 8 permutations
$compiledA = $spintaxSvc->compile($template, ['nama' => 'Budi', 'inv' => 'INV-901', 'total' => '250.000'], 42);
assertTrue(
    $compiledA['permutation_count'] === 8 && $compiledA['formatting_valid'] === true && str_contains($compiledA['compiled_text'], '*Budi*'),
    'Calculate exact nested Spintax AST permutations (4 x 2 = 8) & interpolate variables'
);

// 6. Unclosed WhatsApp Markdown Marker Detection
$brokenFormat = $spintaxSvc->compile('Halo *Budi, pesanan _sedang dikirim_', [], 1);
assertTrue(
    $brokenFormat['formatting_valid'] === false && in_array('bold', $brokenFormat['unclosed_markers'], true),
    'Detect unclosed WhatsApp *bold* formatting marker before dispatch'
);

// 7. Batch 3-gram Jaccard Anti-Ban Uniqueness Scoring
$batchDiverse = [
    'Halo Budi, invoice #INV-101 sebesar Rp 150.000 sudah kami terima dengan baik.',
    'Selamat Pagi Siti, pembayaran tagihan #INV-202 senilai Rp 450.000 berhasil diverifikasi.',
    'Hai Andi, terima kasih telah melunasi order #ORD-998 pada sistem kasir toko kami.',
];
$simResult = $spintaxSvc->calculateBatchSimilarity($batchDiverse, 0.72);
assertTrue(
    $simResult['verdict'] === 'SAFE_ANTI_BAN' && $simResult['avg_jaccard'] < 0.35,
    'Score diverse Spintax batch via 3-gram Jaccard similarity as SAFE_ANTI_BAN (<0.35)'
);

$batchClone = [
    'Halo Pelanggan, klaim hadiah gratis sekarang di link ini!',
    'Halo Pelanggan, klaim hadiah gratis sekarang di link itu!',
];
$simSpam = $spintaxSvc->calculateBatchSimilarity($batchClone, 0.72);
assertTrue(
    $simSpam['verdict'] === 'HIGH_SPAM_RISK' && $simSpam['avg_jaccard'] > 0.72,
    'Flag near-identical blast messages as HIGH_SPAM_RISK via 3-gram Jaccard (>0.72)'
);

// 8. Stateless RFC 6238 / RFC 4226 HMAC-SHA256 OTP Minting & Drift Verification
$otpSecret = 'kujangwa_otp_hmac_sha256_master_key_2026';
$mintedOtp = $spintaxSvc->generateStatelessOtp('6281234567890', 'login_2fa', 1794200000, $otpSecret, 6, 300);
$verifyExact = $spintaxSvc->verifyStatelessOtp('6281234567890', 'login_2fa', $mintedOtp['otp'], 1794200050, $otpSecret, 6, 300, 1);
$verifyDrift = $spintaxSvc->verifyStatelessOtp('6281234567890', 'login_2fa', $mintedOtp['otp'], 1794200310, $otpSecret, 6, 300, 1);
$verifyWrongPhone = $spintaxSvc->verifyStatelessOtp('6281299999999', 'login_2fa', $mintedOtp['otp'], 1794200050, $otpSecret, 6, 300, 1);
assertTrue(
    strlen($mintedOtp['otp']) === 6 && $verifyExact['valid'] === true && $verifyDrift['valid'] === true && $verifyWrongPhone['valid'] === false,
    'Verify stateless HMAC-SHA256 OTP across +1 drift step and reject cross-MSISDN reuse'
);

// 9. Continuous Token-Bucket Rate-Limiter Refill & Throttling Math
$bucketOk = $bucketSvc->consume(['tokens' => 2.0, 'last_refill_ts' => 1000.0], 3.0, 1004.0, 20.0, 0.5);
// elapsed 4s * 0.5/s = +2.0 tokens -> 4.0 available - 3.0 cost = 1.0 remaining
assertTrue(
    $bucketOk['allowed'] === true && abs($bucketOk['tokens_remaining'] - 1.0) < 0.001,
    'Refill Token-Bucket continuously (2.0 + 4s*0.5 - 3.0 = 1.0 token remaining)'
);
$bucketThrottle = $bucketSvc->consume(['tokens' => 0.2, 'last_refill_ts' => 1000.0], 1.0, 1000.6, 20.0, 0.5);
// available = 0.2 + 0.3 = 0.5; deficit = 0.5 at 0.5/s -> retry_after_ms = 1000ms
assertTrue(
    $bucketThrottle['allowed'] === false && $bucketThrottle['retry_after_ms'] === 1000,
    'Throttle burst when Token-Bucket depleted and return exact retry_after_ms (1000ms)'
);

// 10. Multi-Session Circuit-Breaker & Carrier-Affinity Router + Controller Integration
$sessions = require __DIR__ . '/../../config/kujangwa.php';
$dispatchRes = $controller->dispatchMessage([
    'phone'          => '0857-1122-3344', // Indosat -> should route to sess_gowa_standby or highest affinity
    'template'       => '{Halo|Hai} *{{user}}*, pesanan #{{order}} sedang diproses.',
    'variables'      => ['user' => 'Firdaus', 'order' => 'KW-881'],
    'seed'           => 77,
    'is_otp'         => true, // Critical OTP -> boosts official Meta Cloud API session
    'bucket_tokens'  => 10.0,
    'bucket_last_ts' => 1794200000.0,
    'now_ts'         => 1794200002.0,
], $sessions['sessions']);

assertTrue(
    $dispatchRes['status'] === 'QUEUED_FOR_DISPATCH'
    && $dispatchRes['recipient']['carrier'] === 'Indosat'
    && $dispatchRes['routing']['selected_session']['id'] === 'sess_meta_cloud_official'
    && $dispatchRes['anti_ban_jitter']['total_dispatch_delay_ms'] >= 1350,
    'End-to-end Controller dispatch routes critical OTP to Meta Cloud API with anti-ban typing jitter'
);

echo "---------------------------------------------------------\n";
echo "Total Assertions: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
