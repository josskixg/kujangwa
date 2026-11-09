<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class SpintaxTemplateEngine
{
    /**
     * Compile WhatsApp message template with {{variable}} interpolation and recursive {A|B|{C|D}} Spintax AST.
     *
     * @param array<string, scalar> $variables
     * @return array{
     *   compiled_text: string,
     *   permutation_count: int,
     *   char_length: int,
     *   formatting_valid: bool,
     *   unclosed_markers: list<string>
     * }
     */
    public function compile(string $template, array $variables = [], ?int $seed = null): array
    {
        if (trim($template) === '') {
            throw new InvalidArgumentException('Template cannot be empty.');
        }

        $permutationCount = $this->countPermutations($template);

        $interpolated = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', static function (array $m) use ($variables): string {
            $key = $m[1];
            return array_key_exists($key, $variables) ? (string) $variables[$key] : $m[0];
        }, $template) ?? $template;

        $stepCounter = 0;
        $compiled = $this->resolveSpintaxRecursive($interpolated, $seed, $stepCounter);

        $formatCheck = $this->validateWhatsAppFormatting($compiled);

        return [
            'compiled_text'     => $compiled,
            'permutation_count' => $permutationCount,
            'char_length'       => strlen($compiled),
            'formatting_valid'  => $formatCheck['valid'],
            'unclosed_markers'  => $formatCheck['unclosed'],
        ];
    }

    /**
     * Count total unique permutations of a nested Spintax template AST.
     */
    public function countPermutations(string $template): int
    {
        // Strip {{variable}} tokens first so braces don't collide with Spintax
        $clean = preg_replace('/\{\{[^}]+\}\}/', 'VAR', $template) ?? $template;

        $pattern = '/\{([^{}]+)\}/';
        // Replace innermost {a|b|c} with their branch count multiplier
        // Using recursive evaluation over AST segments
        return $this->evalPermutationNode($clean);
    }

    private function evalPermutationNode(string $expr): int
    {
        $total = 1;
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            if ($expr[$i] === '{') {
                $depth = 1;
                $start = $i + 1;
                $i++;
                while ($i < $len && $depth > 0) {
                    if ($expr[$i] === '{') {
                        $depth++;
                    } elseif ($expr[$i] === '}') {
                        $depth--;
                    }
                    $i++;
                }
                $inner = substr($expr, $start, ($i - 1) - $start);
                $branches = $this->splitTopLevelPipe($inner);
                $branchSum = 0;
                foreach ($branches as $branch) {
                    $branchSum += $this->evalPermutationNode($branch);
                }
                $total *= max(1, $branchSum);
            } else {
                $i++;
            }
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelPipe(string $inner): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($ch === '{') {
                $depth++;
                $current .= $ch;
            } elseif ($ch === '}') {
                $depth--;
                $current .= $ch;
            } elseif ($ch === '|' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $parts[] = $current;

        return $parts;
    }

    private function resolveSpintaxRecursive(string $input, ?int $seed, int &$stepCounter): string
    {
        $pattern = '/\{([^{}]+)\}/';
        while (preg_match($pattern, $input) === 1) {
            $input = preg_replace_callback($pattern, static function (array $matches) use ($seed, &$stepCounter): string {
                $options = explode('|', $matches[1]);
                $count = count($options);
                if ($count <= 1) {
                    return $matches[1];
                }
                $stepCounter++;
                if ($seed !== null) {
                    $hash = abs(crc32(sprintf('%d:%d:%s', $seed, $stepCounter, $matches[1])));
                    $idx = $hash % $count;
                } else {
                    $idx = random_int(0, $count - 1);
                }
                return $options[$idx];
            }, $input) ?? $input;
        }

        return $input;
    }

    /**
     * Validate balanced WhatsApp formatting tokens (*bold*, _italic_, ~strike~).
     *
     * @return array{valid: bool, unclosed: list<string>}
     */
    public function validateWhatsAppFormatting(string $text): array
    {
        $unclosed = [];
        foreach (['*' => 'bold', '_' => 'italic', '~' => 'strikethrough'] as $marker => $name) {
            $count = substr_count($text, $marker);
            if ($count % 2 !== 0) {
                $unclosed[] = $name;
            }
        }

        return [
            'valid'    => count($unclosed) === 0,
            'unclosed' => $unclosed,
        ];
    }

    /**
     * Compute average pairwise 3-gram Jaccard similarity across batch messages to prevent WhatsApp spam bans.
     *
     * @param list<string> $messages
     * @return array{avg_jaccard: float, uniqueness_pct: float, verdict: string}
     */
    public function calculateBatchSimilarity(array $messages, float $maxThreshold = 0.72): array
    {
        $n = count($messages);
        if ($n < 2) {
            return [
                'avg_jaccard'    => 0.0,
                'uniqueness_pct' => 100.0,
                'verdict'        => 'SAFE_ANTI_BAN',
            ];
        }

        $shingleSets = array_map([$this, 'extractShingles'], $messages);
        $pairCount = 0;
        $sumJaccard = 0.0;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $setA = $shingleSets[$i];
                $setB = $shingleSets[$j];
                $intersection = count(array_intersect_key($setA, $setB));
                $union = count($setA) + count($setB) - $intersection;
                $sumJaccard += $union > 0 ? ($intersection / $union) : 1.0;
                $pairCount++;
            }
        }

        $avgJaccard = round($sumJaccard / max(1, $pairCount), 4);
        $uniquenessPct = round((1.0 - $avgJaccard) * 100.0, 2);

        return [
            'avg_jaccard'    => $avgJaccard,
            'uniqueness_pct' => $uniquenessPct,
            'verdict'        => $avgJaccard <= $maxThreshold ? 'SAFE_ANTI_BAN' : 'HIGH_SPAM_RISK',
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function extractShingles(string $text): array
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($text)) ?? '');
        $len = strlen($normalized);
        $shingles = [];
        for ($i = 0; $i <= $len - 3; $i++) {
            $shingles[substr($normalized, $i, 3)] = true;
        }
        return $shingles;
    }

    /**
     * Stateless RFC 6238 / RFC 4226 Dynamic Truncation HMAC-SHA256 OTP Generator bound to MSISDN + purpose.
     *
     * @return array{otp: string, time_step: int, expires_at: int, signature_tag: string}
     */
    public function generateStatelessOtp(
        string $msisdn,
        string $purpose,
        int $timestamp,
        string $secret,
        int $digits = 6,
        int $stepSeconds = 300
    ): array {
        $timeStep = intdiv($timestamp, $stepSeconds);
        $context = sprintf('kujangwa_otp:%s:%s:%d', $msisdn, strtolower(trim($purpose)), $timeStep);
        $hmacHex = hash_hmac('sha256', $context, $secret);
        $hmacBytes = hex2bin($hmacHex);

        if ($hmacBytes === false || strlen($hmacBytes) < 32) {
            throw new InvalidArgumentException('HMAC-SHA256 generation failed.');
        }

        // Dynamic truncation per RFC 4226 Section 5.4
        $offset = ord($hmacBytes[31]) & 0x0F;
        $binary = ((ord($hmacBytes[$offset]) & 0x7F) << 24)
            | ((ord($hmacBytes[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmacBytes[$offset + 2]) & 0xFF) << 8)
            | (ord($hmacBytes[$offset + 3]) & 0xFF);

        $modulo = 10 ** $digits;
        $otp = str_pad((string) ($binary % $modulo), $digits, '0', STR_PAD_LEFT);

        return [
            'otp'           => $otp,
            'time_step'     => $timeStep,
            'expires_at'    => ($timeStep + 1) * $stepSeconds,
            'signature_tag' => substr($hmacHex, 0, 16),
        ];
    }

    /**
     * Verify stateless HMAC-SHA256 OTP within ±$window steps in constant time.
     *
     * @return array{valid: bool, matched_step_delta: ?int}
     */
    public function verifyStatelessOtp(
        string $msisdn,
        string $purpose,
        string $code,
        int $timestamp,
        string $secret,
        int $digits = 6,
        int $stepSeconds = 300,
        int $window = 1
    ): array {
        $cleanCode = trim($code);
        for ($delta = -$window; $delta <= $window; $delta++) {
            $probeTs = $timestamp + ($delta * $stepSeconds);
            $candidate = $this->generateStatelessOtp($msisdn, $purpose, $probeTs, $secret, $digits, $stepSeconds);
            if (hash_equals($candidate['otp'], $cleanCode)) {
                return [
                    'valid'              => true,
                    'matched_step_delta' => $delta,
                ];
            }
        }

        return [
            'valid'              => false,
            'matched_step_delta' => null,
        ];
    }
}
