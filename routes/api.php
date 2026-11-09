<?php

declare(strict_types=1);

/**
 * KujangWA Laravel 11 API Route Definitions
 *
 * Mount under routes/api.php with prefix /api/v1
 */

use App\Http\Controllers\WhatsAppGatewayController;
use Illuminate\Support\Facades\Route;

if (class_exists(Route::class)) {
    Route::prefix('v1')->group(static function (): void {
        Route::post('/messages/dispatch', [WhatsAppGatewayController::class, 'dispatchMessage']);
        Route::post('/otp/issue', [WhatsAppGatewayController::class, 'issueOtp']);
        Route::post('/webhooks/meta-cloud', [WhatsAppGatewayController::class, 'handleMetaWebhook']);
    });
}
