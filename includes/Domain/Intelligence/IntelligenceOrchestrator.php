<?php
/**
 * MYAVANA Intelligence Orchestrator - the one door the rest of the app
 * knocks on for AI-generated content.
 *
 *   MYAVANA UI -> REST route -> Application service -> IntelligenceOrchestrator
 *       -> configured IntelligenceProviderInterface -> Gemini/OpenAI/Claude/DeepSeek
 *
 * Nothing outside includes/Domain/Intelligence/ should ever construct a
 * provider or call an external AI API directly. Handles provider selection,
 * caching, and response validation; returns [] (never throws, never a
 * partial/malformed result) whenever AI isn't available or didn't produce a
 * valid response, so callers always have one simple contract: empty array
 * means "fall back to non-AI content," never "something broke."
 *
 * @package Myavana\Next\Domain\Intelligence
 */

namespace Myavana\Next\Domain\Intelligence;

use Myavana\Next\Domain\Intelligence\Contracts\IntelligenceProviderInterface;
use Myavana\Next\Domain\Intelligence\Providers\GeminiProvider;

if (!defined('ABSPATH')) {
    exit;
}

class IntelligenceOrchestrator {
    private const PROVIDER_OPTION = 'myavana_next_ai_provider';
    private const DEFAULT_PROVIDER = 'gemini';
    private const CACHE_PREFIX = 'myavana_intel_';

    /** @var array<string, IntelligenceProviderInterface> */
    private array $providers;

    /**
     * @param array<string, IntelligenceProviderInterface>|null $providers Override for tests.
     */
    public function __construct(?array $providers = null) {
        $this->providers = $providers ?? [
            'gemini' => new GeminiProvider(),
            // OpenAI/Claude/DeepSeek register here once implemented — adding
            // a provider is a new class + one line here, nothing in Today
            // or any other caller needs to change. Not built yet: the
            // roadmap starts with Gemini only, and a provider should only
            // ever be used when explicitly configured, never sight-unseen.
        ];
    }

    /**
     * Generate (or return a cached) structured result for a task.
     *
     * @param string $task     Task identifier, e.g. 'today_insight'.
     * @param int $userId      Whose cache entry this is.
     * @param array $context   Task-specific facts (see the task's context builder).
     * @param array $schema    ['required' => string[], 'instructions' => string].
     * @param array $options   ['ttl' => seconds, 'forceRefresh' => bool, ...provider options].
     * @return array Empty array if AI is unavailable/failed/invalid — the caller supplies its own fallback.
     */
    public function generate(string $task, int $userId, array $context, array $schema, array $options = []): array {
        $cacheKey = $this->cacheKey($task, $userId);

        if (empty($options['forceRefresh'])) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $provider = $this->resolveProvider();
        if (!$provider || !$provider->isConfigured()) {
            return [];
        }

        $response = $provider->generate($task, $context, $schema, $options);
        if (!$response->success || !$this->isValid($response->data, $schema)) {
            if (!$response->success) {
                error_log(sprintf('[MYAVANA Intelligence] %s task "%s" failed: %s', $response->provider ?: 'unknown', $task, $response->error));
            }
            return [];
        }

        $ttl = (int) ($options['ttl'] ?? 6 * HOUR_IN_SECONDS);
        set_transient($cacheKey, $response->data, $ttl);

        return $response->data;
    }

    /**
     * Drop a cached result so the next generate() call produces fresh
     * content — e.g. after the member logs a new journal entry, so
     * "today's insight" isn't stale for the rest of the cache window.
     */
    public function invalidate(string $task, int $userId): void {
        delete_transient($this->cacheKey($task, $userId));
    }

    public function isAnyProviderConfigured(): bool {
        $provider = $this->resolveProvider();
        return $provider !== null && $provider->isConfigured();
    }

    private function resolveProvider(): ?IntelligenceProviderInterface {
        $key = (string) get_option(self::PROVIDER_OPTION, self::DEFAULT_PROVIDER);
        return $this->providers[$key] ?? null;
    }

    private function cacheKey(string $task, int $userId): string {
        return self::CACHE_PREFIX . $task . '_' . $userId;
    }

    /**
     * Cheap structural check — every required field present and non-null.
     * Not a full schema validator; the point is to reject an obviously
     * broken/incomplete response before it's cached or shown, not to
     * police every field's type.
     */
    private function isValid(array $data, array $schema): bool {
        foreach ($schema['required'] ?? [] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                return false;
            }
        }
        return true;
    }
}
