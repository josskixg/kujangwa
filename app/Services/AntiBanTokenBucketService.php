<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AntiBanTokenBucketService
{
    /**
     * Evaluate continuous Token-Bucket state and consume tokens for outbound WhatsApp dispatch.
     *
     * @param array{tokens: float, last_refill_ts: float} $bucketState
     * @return array{
     *   allowed: bool,
     *   tokens_remaining: float,
     *   retry_after_ms: int,
     *   last_refill_ts: float
     * }
     */
    public function consume(
        array $bucketState,
        float $cost,
        float $nowTs,
        float $capacity = 20.0,
        float $refillPerSec = 0.5
    ): array {
        $elapsed = max(0.0, $nowTs - $bucketState['last_refill_ts']);
        $available = min($capacity, $bucketState['tokens'] + ($elapsed * $refillPerSec));

        if ($available >= $cost) {
            $remaining = round($available - $cost, 4);
            return [
                'allowed'          => true,
                'tokens_remaining' => $remaining,
                'retry_after_ms'   => 0,
                'last_refill_ts'   => $nowTs,
            ];
        }

        $deficit = $cost - $available;
        $retryAfterMs = (int) ceil(($deficit / max(0.001, $refillPerSec)) * 1000.0);

        return [
            'allowed'          => false,
            'tokens_remaining' => round($available, 4),
            'retry_after_ms'   => $retryAfterMs,
            'last_refill_ts'   => $nowTs,
        ];
    }

    /**
     * Compute human-like WhatsApp read receipt + composing presence envelope to evade anti-bot heuristics.
     *
     * @return array{read_delay_ms: int, typing_duration_ms: int, total_dispatch_delay_ms: int, chars_per_sec: float}
     */
    public function computeHumanTypingEnvelope(string $message, int $seed = 42): array
    {
        $charCount = max(1, strlen(trim($message)));
        $hash = abs(crc32($message . ':' . $seed));

        // Base read delay: 450ms to 1,150ms
        $readDelayMs = 450 + ($hash % 701);

        // Human mobile typing speed: ~5.5 to 8.5 chars/sec + micro pauses
        $typingRateCps = 5.5 + (($hash % 31) / 10.0);
        $rawTypingMs = (int) round(($charCount / $typingRateCps) * 1000.0);

        // Clamp typing duration between 900ms and 6,500ms so UX stays responsive
        $typingDurationMs = max(900, min(6500, $rawTypingMs));

        return [
            'read_delay_ms'           => $readDelayMs,
            'typing_duration_ms'      => $typingDurationMs,
            'total_dispatch_delay_ms' => $readDelayMs + $typingDurationMs,
            'chars_per_sec'           => round($typingRateCps, 2),
        ];
    }

    /**
     * Route outbound message across multi-device sessions with circuit-breaker & Indonesian carrier affinity.
     *
     * @param list<array{
     *   id: string,
     *   provider: string,
     *   msisdn: string,
     *   weight: int,
     *   preferred_carriers: list<string>,
     *   health_score: int,
     *   circuit_state: string
     * }> $sessions
     * @return array{
     *   selected_session: array<string, mixed>,
     *   routing_score: float,
     *   failover_chain: list<string>
     * }
     */
    public function selectSessionRoute(array $sessions, string $targetCarrier, bool $isCriticalOtp = false): array
    {
        $candidates = [];

        foreach ($sessions as $session) {
            $state = strtoupper($session['circuit_state']);
            if ($state === 'OPEN' || $session['health_score'] < 30) {
                continue;
            }

            $score = ($session['health_score'] * 0.6) + ($session['weight'] * 0.4);

            if (
                in_array($targetCarrier, $session['preferred_carriers'], true)
                || in_array('*', $session['preferred_carriers'], true)
            ) {
                $score += 20.0;
            }

            if ($state === 'HALF_OPEN') {
                $score -= 25.0;
            }

            // Critical OTPs prefer official Meta Cloud API or highest health score session
            if ($isCriticalOtp && str_starts_with($session['provider'], 'meta_cloud')) {
                $score += 35.0;
            }

            $candidates[] = [
                'session' => $session,
                'score'   => round($score, 2),
            ];
        }

        if (count($candidates) === 0) {
            throw new RuntimeException('All WhatsApp gateway sessions are in OPEN circuit-breaker state.');
        }

        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $failoverChain = array_map(
            static fn(array $item): string => (string) $item['session']['id'],
            $candidates
        );

        return [
            'selected_session' => $candidates[0]['session'],
            'routing_score'    => $candidates[0]['score'],
            'failover_chain'   => $failoverChain,
        ];
    }
}
