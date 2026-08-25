<?php
/**
 * Gemini implementation of IntelligenceProviderInterface.
 *
 * Ported from the original AiProxyService's HTTP/auth mechanics (same
 * endpoint, same API key resolution), reshaped to request and validate
 * structured JSON instead of free text, and to serve any registered task
 * instead of a single hardcoded "consult" prompt.
 *
 * @package Myavana\Next\Domain\Intelligence\Providers
 */

namespace Myavana\Next\Domain\Intelligence\Providers;

use Myavana\Next\Domain\Intelligence\Contracts\IntelligenceProviderInterface;
use Myavana\Next\Domain\Intelligence\IntelligenceResponse;

if (!defined('ABSPATH')) {
    exit;
}

class GeminiProvider implements IntelligenceProviderInterface {
    private const NAME = 'gemini';
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function getName(): string {
        return self::NAME;
    }

    public function isConfigured(): bool {
        return $this->getApiKey() !== '';
    }

    public function generate(string $task, array $context, array $schema, array $options = []): IntelligenceResponse {
        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return IntelligenceResponse::fail('not_configured', self::NAME);
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $this->buildPrompt($task, $context, $schema)]],
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.4,
                'maxOutputTokens' => $options['maxOutputTokens'] ?? 500,
                // Structured facts in, structured JSON out — the caller
                // validates the shape regardless, but this materially cuts
                // down on stray prose/markdown fences to strip.
                'responseMimeType' => 'application/json',
            ],
        ];

        $url = add_query_arg(['key' => $apiKey], self::API_ENDPOINT);
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => $options['timeout'] ?? 20,
        ]);

        if (is_wp_error($response)) {
            return IntelligenceResponse::fail('network_error: ' . $response->get_error_message(), self::NAME);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return IntelligenceResponse::fail('http_' . $statusCode, self::NAME);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            return IntelligenceResponse::fail('empty_response', self::NAME);
        }

        $decoded = json_decode(trim($text), true);
        if (!is_array($decoded)) {
            return IntelligenceResponse::fail('malformed_json', self::NAME);
        }

        return IntelligenceResponse::ok($decoded, self::NAME);
    }

    private function buildPrompt(string $task, array $context, array $schema): string {
        $prompt = "You are MYAVANA's hair-care intelligence layer, generating a \"{$task}\" result for a member of the MYAVANA Hair Journey app.\n\n";
        $prompt .= "Facts (already computed from the member's real journal, routine, and profile data — treat every value below as ground truth, and do not invent any additional facts, numbers, or events not listed here):\n";
        $prompt .= wp_json_encode($context, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= $schema['instructions'] ?? '';
        $prompt .= "\n\nGuidelines:\n";
        $prompt .= "- Warm, encouraging, specific — never generic filler.\n";
        $prompt .= "- Frame everything as guidance, not a medical or dermatological diagnosis.\n";
        $prompt .= "- Base every claim only on the facts given above.\n";
        $prompt .= "- Respond with ONLY a single JSON object matching the required shape — no markdown code fences, no commentary before or after it.\n";

        return $prompt;
    }

    private function getApiKey(): string {
        if (defined('MYAVANA_GEMINI_API_KEY') && !empty(MYAVANA_GEMINI_API_KEY)) {
            return MYAVANA_GEMINI_API_KEY;
        }

        return (string) get_option('myavana_gemini_api_key', '');
    }
}
