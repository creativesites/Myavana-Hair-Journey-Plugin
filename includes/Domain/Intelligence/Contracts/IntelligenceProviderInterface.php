<?php
/**
 * Contract every AI provider (Gemini, OpenAI, Claude, DeepSeek, ...) must
 * implement. Nothing outside includes/Domain/Intelligence/Providers/ should
 * ever call a provider's SDK/API directly — the rest of the app only ever
 * talks to IntelligenceOrchestrator.
 *
 * @package Myavana\Next\Domain\Intelligence\Contracts
 */

namespace Myavana\Next\Domain\Intelligence\Contracts;

use Myavana\Next\Domain\Intelligence\IntelligenceResponse;

if (!defined('ABSPATH')) {
    exit;
}

interface IntelligenceProviderInterface {
    /**
     * Whether this provider has everything it needs to run right now (e.g.
     * an API key configured) — checked before ever attempting a call.
     */
    public function isConfigured(): bool;

    /**
     * A short machine-readable name for logging/cache keys ('gemini', ...).
     */
    public function getName(): string;

    /**
     * Generate a structured response for a named task.
     *
     * @param string $task     Task identifier, e.g. 'today_insight'.
     * @param array $context   Task-specific FACTS computed by the caller's
     *                         context builder — never raw DB records, never
     *                         credentials/tokens/PII beyond what the task needs.
     * @param array $schema    ['required' => string[], 'instructions' => string]
     *                         describing exactly what JSON shape to return.
     * @param array $options   Optional per-call overrides (temperature, timeout, ...).
     */
    public function generate(string $task, array $context, array $schema, array $options = []): IntelligenceResponse;
}
