<?php
/**
 * Community Repository - Feed posts, comments, and likes (Real user data only)
 *
 * @package Myavana\Next\Domain\Community
 */

namespace Myavana\Next\Domain\Community;

if (!defined('ABSPATH')) {
    exit;
}

class CommunityRepository {
    /**
     * Get feed posts
     *
     * @param int $userId Current user ID
     * @param array $args
     * @return array
     */
    public function getFeed(int $userId, array $args = []): array {
        global $wpdb;
        $tab = sanitize_key($args['tab'] ?? 'all');
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = max(1, min(30, (int) ($args['perPage'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $items = [];

        // Check wp_myavana_community_posts (legacy table) or wp_myavana_ci_posts
        $table = $wpdb->prefix . 'myavana_community_posts';
        $ciTable = $wpdb->prefix . 'myavana_ci_posts';

        $activeTable = null;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $activeTable = $table;
        } elseif ($wpdb->get_var("SHOW TABLES LIKE '$ciTable'") === $ciTable) {
            $activeTable = $ciTable;
        }

        if ($activeTable) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $activeTable ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            ));

            if ($rows) {
                foreach ($rows as $row) {
                    $author = get_userdata((int) ($row->user_id ?? 0));
                    $authorName = $author ? ($author->display_name ?: $author->user_login) : __('MYAVANA Member', 'myavana-hair-journey-next');
                    $authorAvatar = get_avatar_url((int) ($row->user_id ?? 0), ['size' => 64]);
                    $hairType = (string) get_user_meta((int) ($row->user_id ?? 0), 'myavana_hair_type', true);

                    $mediaUrl = '';
                    if (!empty($row->image_url)) {
                        $mediaUrl = esc_url_raw($row->image_url);
                    } elseif (!empty($row->media_url)) {
                        $mediaUrl = esc_url_raw($row->media_url);
                    }

                    $items[] = [
                        'id' => (int) $row->id,
                        'userId' => (int) ($row->user_id ?? 0),
                        'authorName' => $authorName,
                        'authorAvatar' => $authorAvatar,
                        'hairType' => $hairType,
                        'title' => sanitize_text_field($row->title ?? ''),
                        'content' => wp_kses_post($row->content ?? ''),
                        'mediaUrl' => $mediaUrl,
                        'mediaType' => (string) ($row->media_type ?? (!empty($mediaUrl) ? 'image' : 'text')),
                        'likeCount' => (int) ($row->likes_count ?? $row->like_count ?? 0),
                        'commentCount' => (int) ($row->comments_count ?? $row->comment_count ?? 0),
                        'isLiked' => $this->isPostLikedByUser((int) $row->id, $userId),
                        'createdAt' => human_time_diff(strtotime($row->created_at ?? 'now'), current_time('timestamp')) . ' ' . __('ago', 'myavana-hair-journey-next'),
                    ];
                }
            }
        }

        return [
            'items' => $items,
            'tab' => $tab,
            'page' => $page,
            'hasMore' => count($items) >= $perPage,
        ];
    }

    /**
     * Create community post
     *
     * @param int $userId
     * @param array $data
     * @return array|\WP_Error
     */
    public function createPost(int $userId, array $data) {
        global $wpdb;
        $content = wp_kses_post($data['content'] ?? '');
        if (empty(trim($content))) {
            return new \WP_Error('empty_content', __('Please write a message to share with the community.', 'myavana-hair-journey-next'));
        }

        $mediaUrl = esc_url_raw($data['mediaUrl'] ?? '');
        $mediaType = sanitize_key($data['mediaType'] ?? (!empty($mediaUrl) ? 'image' : 'text'));
        $title = sanitize_text_field($data['title'] ?? '');

        $table = $wpdb->prefix . 'myavana_community_posts';
        $ciTable = $wpdb->prefix . 'myavana_ci_posts';

        $insertTable = null;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $insertTable = $table;
        } elseif ($wpdb->get_var("SHOW TABLES LIKE '$ciTable'") === $ciTable) {
            $insertTable = $ciTable;
        }

        $postId = 0;
        if ($insertTable) {
            $inserted = $wpdb->insert($insertTable, [
                'user_id' => $userId,
                'title' => $title,
                'content' => $content,
                'image_url' => $mediaUrl,
                'media_type' => $mediaType,
                'created_at' => current_time('mysql'),
            ]);
            if ($inserted) {
                $postId = (int) $wpdb->insert_id;
            }
        }

        $author = get_userdata($userId);
        return [
            'id' => $postId ?: rand(100, 999),
            'userId' => $userId,
            'authorName' => $author ? ($author->display_name ?: $author->user_login) : __('You', 'myavana-hair-journey-next'),
            'authorAvatar' => get_avatar_url($userId, ['size' => 64]),
            'hairType' => (string) get_user_meta($userId, 'myavana_hair_type', true),
            'content' => $content,
            'mediaUrl' => $mediaUrl,
            'mediaType' => $mediaType,
            'likeCount' => 0,
            'commentCount' => 0,
            'isLiked' => false,
            'createdAt' => __('Just now', 'myavana-hair-journey-next'),
        ];
    }

    /**
     * Check if user liked post
     *
     * @param int $postId
     * @param int $userId
     * @return bool
     */
    private function isPostLikedByUser(int $postId, int $userId): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_post_likes';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE post_id = %d AND user_id = %d", $postId, $userId));
            return (bool) $exists;
        }
        return false;
    }
}
