<?php
/**
 * Value object returned by every IntelligenceProviderInterface::generate()
 * call — success/failure plus the decoded structured data, never a raw
 * provider payload.
 *
 * @package Myavana\Next\Domain\Intelligence
 */

namespace Myavana\Next\Domain\Intelligence;

if (!defined('ABSPATH')) {
    exit;
}

class IntelligenceResponse {
    public bool $success;
    public array $data;
    public string $provider;
    public string $error;

    private function __construct(bool $success, array $data, string $provider, string $error) {
        $this->success = $success;
        $this->data = $data;
        $this->provider = $provider;
        $this->error = $error;
    }

    public static function ok(array $data, string $provider): self {
        return new self(true, $data, $provider, '');
    }

    public static function fail(string $error, string $provider = ''): self {
        return new self(false, [], $provider, $error);
    }
}
