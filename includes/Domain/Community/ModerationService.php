<?php
/**
 * Moderation Service - Content safety, privacy, and post reporting
 *
 * @package Myavana\Next\Domain\Community
 */

namespace Myavana\Next\Domain\Community;

if (!defined('ABSPATH')) {
    exit;
}

class ModerationService {
    /**
     * Clean and sanitize post content
     *
     * @param string $content
     * @return string
     */
    public function sanitizePostContent(string $content): string {
        return wp_kses($content, [
            'strong' => [],
            'em' => [],
            'p' => [],
            'br' => [],
            'a' => ['href' => [], 'title' => [], 'target' => []],
        ]);
    }

    /**
     * Validate user permissions to interact with a post
     *
     * @param int $postAuthorId
     * @param int $currentUserId
     * @return bool
     */
    public function canEditOrDelete(int $postAuthorId, int $currentUserId): bool {
        if ($postAuthorId === $currentUserId) {
            return true;
        }

        return current_user_can('manage_options');
    }
}
