# KujangWA — Self-Hosted WhatsApp Cloud API & Multi-Session Router

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel 11 Ready](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Design System](https://img.shields.io/badge/Design%20System-Raycast%20Obsidian-FF6363?logo=raycast&logoColor=white)](DESIGN.md)
[![Unit Tests](https://img.shields.io/badge/Unit%20Tests-16%2F16%20Passed-5fc992)](#unit-tests)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

<p align="center">
  <img src="logo.png" width="160" height="160" alt="KujangWA Logo" style="border-radius: 32px;" />
</p>

> **Self-hosted, high-throughput WhatsApp Cloud API & multi-device bridge router (Baileys/GoWA/WuzAPI) with anti-ban Token-Bucket rate limiting, recursive Spintax AST permutation compiler, stateless RFC 6238 HMAC-SHA256 OTP minting, and Indonesian telco carrier affinity.**

---

## ⚡ Live Interactive Operator Console

Experience the live interactive **Raycast Obsidian** operator surface directly in your browser:  
👉 **[https://josskixg.github.io/kujangwa/](https://josskixg.github.io/kujangwa/)**

- **Recursive Spintax AST Compiler**: Live variable interpolation & permutation count math.
- **Continuous Token-Bucket**: Real-time refill simulation (+0.5 tokens/sec) and deficit throttling.
- **3-Gram Jaccard Anti-Ban Scoring**: Pairwise blast message similarity detection (<0.72 threshold).
- **Stateless HMAC-SHA256 OTP**: Zero-database OTP generation & drift verification.
- **Meta X-Hub-Signature-256 Inspector**: Constant-time signature audit and payload tamper detection.

---

## 🏗️ Architecture & Core Algorithms

```
                          ┌─────────────────────────────────────┐
                          │     Inbound API / Webhook Trigger   │
                          └──────────────────┬──────────────────┘
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       ▼                                           ▼
          ┌─────────────────────────┐                 ┌─────────────────────────┐
          │  MSISDN Normalizer      │                 │  Meta X-Hub Signature   │
          │  (E.164 + Telco Prefix) │                 │  HMAC-SHA256 Verifier   │
          └────────────┬────────────┘                 └────────────┬────────────┘
                       │                                           │
                       ▼                                           ▼
          ┌─────────────────────────┐                 ┌─────────────────────────┐
          │   Spintax AST Engine    │                 │  Bridge Replay Guard    │
          │   Recursive Permutations│                 │  ±300s Timestamp Check  │
          └────────────┬────────────┘                 └────────────┬────────────┘
                       │                                           │
                       ▼                                           ▼
          ┌─────────────────────────┐                 ┌─────────────────────────┐
          │  3-Gram Jaccard Anti-Ban│                 │  Stateless RFC 6238 OTP │
          │  Pairwise Similarity    │                 │  Dynamic Truncation     │
          └────────────┬────────────┘                 └────────────┬────────────┘
                       │
                       ▼
          ┌────────────────────────────────────────────────────────┐
          │     Continuous Token-Bucket Rate Limiter (20.0 cap)    │
          │     + Human Typing Presence Envelope (450ms–6500ms)    │
          └────────────────────────────┬───────────────────────────┘
                                       │
                                       ▼
          ┌────────────────────────────────────────────────────────┐
          │    Multi-Session Router with Indonesian Telco Affinity │
          │    (Telkomsel / Indosat / XL / Meta Official API)      │
          └────────────────────────────────────────────────────────┘
```

1. **Deterministic E.164 & Telco Carrier Detection**: Normalizes `08xx`, `+62xx`, and `@s.whatsapp.net` JIDs into clean E.164 numbers and maps 5-digit prefixes to Indonesian carriers (*Telkomsel, Indosat, XL/Axis, Three, Smartfren*).
2. **Recursive Spintax AST Engine**: Parses nested `{A|B|{C|D}}` blocks and calculates true combinatorial permutation counts while verifying balanced WhatsApp Markdown syntax (`*bold*`, `_italic_`).
3. **Continuous Token-Bucket Rate Limiter**: Evaluates continuous fractional refills (`+0.5 ref/sec`) to protect numbers from WhatsApp automated spam bans, paired with randomized human-like typing presence envelopes (`composing` state + read receipts).
4. **Stateless HMAC-SHA256 OTP (RFC 6238 / RFC 4226)**: Generates 6-digit verification codes mathematically bound to destination phone number and purpose tag. Requires **zero database writes** and verifies candidate codes across `±1` step drift in constant time (`hash_equals`).
5. **Meta Webhook Signature Verification**: Constant-time verification of `X-Hub-Signature-256` headers with replay protection for self-hosted bridge webhooks.

---

## 🚀 Quickstart & Installation

```bash
# Clone the repository
git clone https://github.com/josskixg/kujangwa.git
cd kujangwa

# Execute standalone PHP 8.2 test suite
php tests/Unit/KujangWaTest.php
```

### Laravel 11 Integration

Copy the service files into your Laravel 11 project:

```bash
cp -r app/Services/* app/Services/
cp -r app/Http/Controllers/* app/Http/Controllers/
cp config/kujangwa.php config/
cp database/migrations/* database/migrations/
```

Register API endpoints in `routes/api.php`:

```php
use App\Http\Controllers\WhatsAppGatewayController;

Route::prefix('v1/whatsapp')->group(function () {
    Route::post('/dispatch', [WhatsAppGatewayController::class, 'dispatchMessage']);
    Route::post('/otp/issue', [WhatsAppGatewayController::class, 'issueOtp']);
    Route::post('/webhooks/meta-cloud', [WhatsAppGatewayController::class, 'handleMetaWebhook']);
});
```

---

## 🧪 Unit Tests

Run the full cryptographic and protocol unit test suite:

```bash
php tests/Unit/KujangWaTest.php
```

```
=== KujangWA Cryptographic & Protocol Unit Test Suite ===
  [PASS] Normalize 0812-3456-7890 -> 6281234567890 (Telkomsel)
  [PASS] Normalize +62 857 9876 5432 -> 6285798765432 (Indosat)
  [PASS] Strip @s.whatsapp.net JID and detect XL_Axis prefix (62818)
  [PASS] Verify valid Meta X-Hub-Signature-256 HMAC-SHA256 digest
  [PASS] Reject tampered Meta webhook payload in constant time
  [PASS] Accept self-hosted bridge webhook within +-300s replay window
  [PASS] Reject replayed bridge webhook exceeding 300s clock skew
  [PASS] Extract interactive button_reply ID and delivery read receipt from Graph API payload
  [PASS] Calculate exact nested Spintax AST permutations (4 x 2 = 8) & interpolate variables
  [PASS] Detect unclosed WhatsApp *bold* formatting marker before dispatch
  [PASS] Score diverse Spintax batch via 3-gram Jaccard similarity as SAFE_ANTI_BAN (<0.35)
  [PASS] Flag near-identical blast messages as HIGH_SPAM_RISK via 3-gram Jaccard (>0.72)
  [PASS] Verify stateless HMAC-SHA256 OTP across +1 drift step and reject cross-MSISDN reuse
  [PASS] Refill Token-Bucket continuously (2.0 + 4s*0.5 - 3.0 = 1.0 token remaining)
  [PASS] Throttle burst when Token-Bucket depleted and return exact retry_after_ms (1000ms)
  [PASS] End-to-end Controller dispatch routes critical OTP to Meta Cloud API with anti-ban typing jitter
---------------------------------------------------------
Total Assertions: 16 | Passed: 16 | Failed: 0
```

---

## 📄 License

MIT License. Built for production reliability and zero-bloat commercial deployment.
