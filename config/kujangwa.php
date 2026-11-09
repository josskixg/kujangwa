<?php

declare(strict_types=1);

return [
    'meta_app_secret' => (string) (getenv('KUJANGWA_META_APP_SECRET') ?: 'whsec_kujangwa_meta_cloud_2026_secret'),
    'bridge_webhook_secret' => (string) (getenv('KUJANGWA_BRIDGE_SECRET') ?: 'whsec_kujangwa_baileys_bridge_2026'),
    'otp_master_secret' => (string) (getenv('KUJANGWA_OTP_SECRET') ?: 'kujangwa_otp_hmac_sha256_master_key_2026'),
    'webhook_tolerance_seconds' => 300,
    'rate_limit' => [
        'capacity' => 20.0,
        'refill_per_second' => 0.5, // 30 messages/minute sustained per session
        'max_jaccard_similarity' => 0.72,
    ],
    'sessions' => [
        [
            'id' => 'sess_baileys_primary',
            'provider' => 'baileys_md',
            'msisdn' => '6281290001101',
            'weight' => 60,
            'preferred_carriers' => ['Telkomsel', 'By.U'],
            'health_score' => 98,
            'circuit_state' => 'CLOSED',
        ],
        [
            'id' => 'sess_gowa_standby',
            'provider' => 'gowa_v4',
            'msisdn' => '6285780002202',
            'weight' => 30,
            'preferred_carriers' => ['Indosat', 'XL_Axis', 'Three', 'Smartfren'],
            'health_score' => 94,
            'circuit_state' => 'CLOSED',
        ],
        [
            'id' => 'sess_meta_cloud_official',
            'provider' => 'meta_cloud_v21',
            'msisdn' => '6281100009999',
            'weight' => 10,
            'preferred_carriers' => ['*'],
            'health_score' => 100,
            'circuit_state' => 'CLOSED',
        ],
    ],
];
