<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiAuthenticationException;
use App\Exceptions\Ai\AiInvalidResponseException;
use App\Exceptions\Ai\AiRateLimitException;
use App\Exceptions\Ai\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiTextGenerator implements AiTextGeneratorInterface
{
    public function generate(string $systemInstruction, string $userPrompt): AiResult
    {
        return $this->call($systemInstruction, $userPrompt, []);
    }

    public function generateWithImages(string $systemInstruction, string $userPrompt, array $images): AiResult
    {
        return $this->call($systemInstruction, $userPrompt, $images);
    }

    private function call(string $system, string $user, array $images): AiResult
    {
        $apiKey = (string) config('ai.gemini.api_key', '');
        $model = (string) config('ai.gemini.model', 'gemini-3.6-flash');
        $configured = max(10, (int) config('ai.gemini.timeout', 60));
        $maxTokens = max(256, (int) config('ai.gemini.max_output_tokens', 4096));

        // مهلة HTTP يجب أن تبقى دائماً أقصر من حد تنفيذ PHP، وإلا قتل PHP
        // السكربت بخطأ Maximum execution time قبل أن يرد Gemini (خطأ قاتل لا يُلتقط).
        $phpLimit = (int) @ini_get('max_execution_time');
        $timeout = $configured;
        if ($phpLimit > 0) {
            $safeMax = $phpLimit - 15; // هامش لمعالجة DB والعرض بعد رد Gemini
            if ($safeMax < 10) {
                $safeMax = 10;
            }
            $timeout = min($configured, $safeMax);
        }

        if ($apiKey === '') {
            throw new AiAuthenticationException(__('api.ai_not_configured'));
        }

        $parts = [['text' => $user]];
        foreach ($images as $img) {
            if (empty($img['data']) || empty($img['mime'])) {
                continue;
            }
            $parts[] = ['inline_data' => ['mime_type' => $img['mime'], 'data' => $img['data']]];
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $started = microtime(true);
        try {
            // asJson forces UTF-8 JSON encoding (critical for Arabic prompts).
            // Gemini requires the key as a query parameter.
            // connectTimeout منفصل حتى لا يعلق الاتصال الأولي حتى نهاية timeout الكلي.
            $response = Http::asJson()->connectTimeout(10)->timeout($timeout)->retry(0)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey), $payload);
        } catch (ConnectionException $e) {
            Log::warning('[AI] gemini timeout', ['model' => $model, 'timeout' => $timeout]);
            throw new AiUnavailableException(__('api.ai_timeout'), previous: $e);
        } catch (\Throwable $e) {
            Log::warning('[AI] gemini connection failed', ['model' => $model, 'error' => get_class($e)]);
            throw new AiUnavailableException(__('api.ai_network'), previous: $e);
        }
        $latency = (int) ((microtime(true) - $started) * 1000);

        $status = $response->status();
        if ($status === 400) {
            $msg = (string) ($response->json('error.message') ?? '');
            Log::warning('[AI] gemini bad request', ['model' => $model, 'latency_ms' => $latency, 'error' => mb_substr($msg, 0, 200)]);
            if (str_contains(strtolower($msg), 'api key')) {
                throw new AiAuthenticationException(__('api.ai_key_rejected'));
            }
            if (str_contains($msg, 'not found') || str_contains($msg, 'is not found') || str_contains($msg, 'no longer available')) {
                throw new AiUnavailableException(__('api.ai_model_unavailable', ['model' => $model]));
            }
            throw new AiInvalidResponseException(__('api.ai_request_rejected'));
        }
        if ($status === 401 || $status === 403) {
            Log::warning('[AI] gemini auth failed', ['model' => $model, 'status' => $status, 'latency_ms' => $latency]);
            throw new AiAuthenticationException(__('api.ai_auth_failed'));
        }
        if ($status === 429) {
            $retryAfter = $this->parseRetryAfterFromResponse($response);
            $errMsg = (string) ($response->json('error.message') ?? '');
            $daily = $this->isDailyQuota($errMsg);
            Log::warning('[AI] gemini rate limited', [
                'model' => $model,
                'latency_ms' => $latency,
                'retry_after' => $retryAfter,
                'is_daily' => $daily,
                'error' => mb_substr($errMsg, 0, 300),
            ]);
            if ($retryAfter !== null) {
                throw new AiRateLimitException(__('api.ai_rate_wait', ['seconds' => $retryAfter]), retryAfter: $retryAfter, previous: null);
            }
            if ($daily) {
                throw new AiRateLimitException(__('api.ai_quota_daily'), retryAfter: null, previous: null);
            }
            throw new AiRateLimitException(__('api.ai_rate_generic'), retryAfter: null, previous: null);
        }
        if ($status >= 500 || $status === 0) {
            Log::warning('[AI] gemini unavailable', ['model' => $model, 'status' => $status, 'latency_ms' => $latency]);
            throw new AiUnavailableException(__('api.ai_busy_service'));
        }
        if (!$response->successful()) {
            Log::warning('[AI] gemini error', ['model' => $model, 'status' => $status, 'latency_ms' => $latency]);
            throw new AiUnavailableException(__('api.ai_generate_failed'));
        }

        $json = $response->json();
        $candidate = $json['candidates'][0] ?? null;
        $blockReason = (string) ($json['promptFeedback']['blockReason'] ?? $candidate['finishReason'] ?? '');
        if (in_array($blockReason, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'OTHER'], true)) {
            Log::warning('[AI] gemini safety block', ['model' => $model, 'reason' => $blockReason]);
            throw new AiInvalidResponseException(__('api.ai_safety'));
        }

        $text = $candidate['content']['parts'][0]['text'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            throw new AiInvalidResponseException(__('api.ai_empty'));
        }

        return $this->parse($text, (string) ($candidate['finishReason'] ?? ''));
    }

    private function parse(string $text, string $finishReason = ''): AiResult
    {
        $text = trim($text);

        $lower = mb_strtolower($text);
        foreach (['as an ai', 'as a language model', 'بصفتي نموذج ذكاء اصطناعي', 'بصفتي ذكاء اصطناعي'] as $banned) {
            if (str_contains($lower, mb_strtolower($banned))) {
                throw new AiInvalidResponseException(__('api.ai_invalid'));
            }
        }
        if (mb_strlen($text) < 20 || mb_strlen($text) > 30000) {
            throw new AiInvalidResponseException(__('api.ai_invalid_length'));
        }

        $uncertainties = [];
        if ($finishReason === 'MAX_TOKENS') {
            $uncertainties[] = __('api.ai_truncated');
        }

        $decoded = $this->extractJson($text);
        if (is_array($decoded)) {
            $summary = isset($decoded['summary']) && is_string($decoded['summary']) ? trim($decoded['summary']) : '';
            $reco = isset($decoded['recommendations']) && is_string($decoded['recommendations']) ? trim($decoded['recommendations']) : '';
            $body = isset($decoded['body']) && is_string($decoded['body']) ? trim($decoded['body']) : '';
            if ($summary !== '' || $reco !== '' || $body !== '') {
                $u = array_values(array_filter((array) ($decoded['uncertainties'] ?? []), 'is_string'));
                return new AiResult(
                    title: (string) ($decoded['title'] ?? ''),
                    summary: $summary,
                    body: $body,
                    warnings: array_values(array_filter((array) ($decoded['warnings'] ?? []), 'is_string')),
                    uncertainties: array_values(array_unique(array_merge($u, $uncertainties))),
                    raw: $text,
                    recommendations: $reco,
                );
            }
        }

        return new AiResult(title: '', summary: '', body: $text, warnings: [], uncertainties: $uncertainties, raw: $text);
    }

    private function extractJson(string $text): ?array
    {
        $clean = trim($text);
        // Strip markdown code fences (```json ... ```) models love to add.
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        if (str_starts_with($clean, '{') && ($d = json_decode($clean, true)) && is_array($d)) {
            return $d;
        }
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }
        if (is_numeric(trim($header))) {
            return max(1, (int) trim($header));
        }
        $ts = strtotime($header);
        if ($ts !== false) {
            return max(1, $ts - time());
        }

        return null;
    }

    /**
     * Gemini نادراً ما يرسل ترويسة Retry-After. المدة الحقيقية غالباً في
     * جسم الخطأ: error.details[].retryDelay ("35s") أو داخل error.message
     * ("Please retry in 42.3s"). نفحص الترويسة أولاً ثم الجسم.
     */
    private function parseRetryAfterFromResponse($response): ?int
    {
        $fromHeader = $this->parseRetryAfter($response->header('Retry-After'));
        if ($fromHeader !== null) {
            return min($fromHeader, 86400);
        }

        try {
            $json = $response->json();
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($json)) {
            return null;
        }
        $error = $json['error'] ?? null;
        if (!is_array($error)) {
            return null;
        }

        foreach ((array) ($error['details'] ?? []) as $detail) {
            if (!is_array($detail)) {
                continue;
            }
            // google.rpc.RetryInfo → {"retryDelay": "35s"}
            if (isset($detail['retryDelay']) && is_string($detail['retryDelay'])) {
                $parsed = $this->parseDelayString($detail['retryDelay']);
                if ($parsed !== null) {
                    return min($parsed, 86400);
                }
            }
        }

        $msg = (string) ($error['message'] ?? '');
        if ($msg !== '' && preg_match('/retry\s+in\s+([\d.]+)\s*s/i', $msg, $m)) {
            $parsed = (int) ceil((float) $m[1]);
            if ($parsed >= 1) {
                return min($parsed, 86400);
            }
        }

        return null;
    }

    private function parseDelayString(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        // صيغ مثل "35s" أو "12.5s" أو "35"
        if (preg_match('/^([\d.]+)\s*s?$/i', $value, $m)) {
            $parsed = (int) ceil((float) $m[1]);
            return $parsed >= 1 ? $parsed : null;
        }

        return null;
    }

    private function isDailyQuota(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'per day')
            || str_contains($lower, 'perday')
            || str_contains($lower, 'per_day')
            || str_contains($lower, 'daily')
            || str_contains($lower, 'generatecontent')
                && str_contains($lower, 'day');
    }
}
