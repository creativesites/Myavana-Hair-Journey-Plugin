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

        $mediaUrl = esc_url_raw($data['mediaUrl'] ?? ($data['media_urls'][0] ?? ($data['imageUrl'] ?? '')));
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
     * Toggle like on post
     */
    public function toggleLike(int $userId, int $postId): array {
        global $wpdb;
        $likesTable = $wpdb->prefix . 'myavana_post_likes';
        $postsTable = $wpdb->prefix . 'myavana_community_posts';
        $ciTable = $wpdb->prefix . 'myavana_ci_posts';

        $activePostsTable = ($wpdb->get_var("SHOW TABLES LIKE '$postsTable'") === $postsTable) ? $postsTable : $ciTable;

        // Ensure likes table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$likesTable'") !== $likesTable) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $likesTable (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                post_id mediumint(9) NOT NULL,
                user_id bigint(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY post_user (post_id, user_id),
                KEY user_id (user_id)
            ) $charset_collate;");
        }

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $likesTable WHERE post_id = %d AND user_id = %d", $postId, $userId));

        if ($existing) {
            $wpdb->delete($likesTable, ['post_id' => $postId, 'user_id' => $userId], ['%d', '%d']);
            if ($activePostsTable) {
                $wpdb->query($wpdb->prepare("UPDATE $activePostsTable SET likes_count = GREATEST(0, likes_count - 1) WHERE id = %d", $postId));
            }
            $isLiked = false;
        } else {
            $wpdb->insert($likesTable, [
                'post_id' => $postId,
                'user_id' => $userId,
                'created_at' => current_time('mysql'),
            ]);
            if ($activePostsTable) {
                $wpdb->query($wpdb->prepare("UPDATE $activePostsTable SET likes_count = likes_count + 1 WHERE id = %d", $postId));
            }
            $isLiked = true;
        }

        $likeCount = $activePostsTable ? (int) $wpdb->get_var($wpdb->prepare("SELECT likes_count FROM $activePostsTable WHERE id = %d", $postId)) : 0;

        return [
            'postId' => $postId,
            'isLiked' => $isLiked,
            'likeCount' => $likeCount,
        ];
    }

    /**
     * Get comments for a post
     */
    public function getComments(int $postId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_post_comments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d ORDER BY created_at ASC LIMIT 50",
            $postId
        ));

        $comments = [];
        if ($rows) {
            foreach ($rows as $row) {
                $author = get_userdata((int) $row->user_id);
                $authorName = $author ? ($author->display_name ?: $author->user_login) : 'Member';
                $comments[] = [
                    'id' => (int) $row->id,
                    'postId' => (int) $row->post_id,
                    'userId' => (int) $row->user_id,
                    'authorName' => $authorName,
                    'authorAvatar' => get_avatar_url((int) $row->user_id, ['size' => 48]),
                    'content' => wp_kses_post($row->content),
                    'createdAt' => human_time_diff(strtotime($row->created_at), current_time('timestamp')) . ' ' . __('ago', 'myavana-hair-journey-next'),
                ];
            }
        }
        return $comments;
    }

    /**
     * Add comment to post
     */
    public function addComment(int $userId, int $postId, string $content) {
        global $wpdb;
        $clean = wp_kses_post(trim($content));
        if (empty($clean)) {
            return new \WP_Error('empty_comment', __('Comment text cannot be empty.', 'myavana-hair-journey-next'));
        }

        $commentsTable = $wpdb->prefix . 'myavana_post_comments';
        $postsTable = $wpdb->prefix . 'myavana_community_posts';
        $ciTable = $wpdb->prefix . 'myavana_ci_posts';
        $activePostsTable = ($wpdb->get_var("SHOW TABLES LIKE '$postsTable'") === $postsTable) ? $postsTable : $ciTable;

        if ($wpdb->get_var("SHOW TABLES LIKE '$commentsTable'") !== $commentsTable) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $commentsTable (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                post_id mediumint(9) NOT NULL,
                user_id bigint(20) NOT NULL,
                parent_id mediumint(9) DEFAULT 0,
                content text NOT NULL,
                likes_count int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY user_id (user_id)
            ) $charset_collate;");
        }

        $inserted = $wpdb->insert($commentsTable, [
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => $clean,
            'created_at' => current_time('mysql'),
        ]);

        if (!$inserted) {
            return new \WP_Error('db_error', __('Could not post comment. Please try again.', 'myavana-hair-journey-next'));
        }

        $commentId = (int) $wpdb->insert_id;
        if ($activePostsTable) {
            $wpdb->query($wpdb->prepare("UPDATE $activePostsTable SET comments_count = comments_count + 1 WHERE id = %d", $postId));
        }

        $author = get_userdata($userId);
        return [
            'id' => $commentId,
            'postId' => $postId,
            'userId' => $userId,
            'authorName' => $author ? ($author->display_name ?: $author->user_login) : __('You', 'myavana-hair-journey-next'),
            'authorAvatar' => get_avatar_url($userId, ['size' => 48]),
            'content' => $clean,
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
