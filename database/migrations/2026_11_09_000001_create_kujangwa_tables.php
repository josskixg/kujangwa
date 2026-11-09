<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kujangwa_sessions', static function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('provider', 32)->index(); // baileys_md, gowa_v4, meta_cloud_v21
            $table->string('msisdn', 20)->unique();
            $table->unsignedTinyInteger('weight')->default(50);
            $table->json('preferred_carriers');
            $table->unsignedTinyInteger('health_score')->default(100);
            $table->enum('circuit_state', ['CLOSED', 'HALF_OPEN', 'OPEN'])->default('CLOSED')->index();
            $table->decimal('bucket_tokens', 8, 4)->default(20.0000);
            $table->decimal('bucket_last_refill_ts', 16, 4)->default(0.0000);
            $table->timestamps();
        });

        Schema::create('kujangwa_message_logs', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('wamid', 128)->nullable()->index();
            $table->string('session_id', 64)->index();
            $table->string('recipient_e164', 20)->index();
            $table->string('carrier', 32)->index();
            $table->text('compiled_body');
            $table->unsignedInteger('spintax_permutations')->default(1);
            $table->unsignedSmallInteger('typing_delay_ms')->default(1000);
            $table->enum('delivery_status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued')->index();
            $table->timestamps();
        });

        Schema::create('kujangwa_webhook_receipts', static function (Blueprint $table): void {
            $table->id();
            $table->string('wamid', 128)->index();
            $table->string('source', 32); // meta_cloud, bridge_hmac
            $table->string('signature_sha256', 72);
            $table->string('event_status', 32);
            $table->json('raw_payload');
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kujangwa_webhook_receipts');
        Schema::dropIfExists('kujangwa_message_logs');
        Schema::dropIfExists('kujangwa_sessions');
    }
};
