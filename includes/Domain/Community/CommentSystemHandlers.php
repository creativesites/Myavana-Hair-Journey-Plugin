<?php
/**
 * MYAVANA Comment System AJAX Handlers
 * Modern comment system with reactions, editing, threading, and moderation
 *
 * @package Myavana\Next\Domain\Community
 */

namespace Myavana\Next\Domain\Community;

if (!defined('ABSPATH')) {
    exit;
}

class CommentSystemHandlers {
    private $user_id;
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = get_current_user_id();
        $this->init();
    }

    private function init() {
        // Unauthenticated allowed
        add_action('wp_ajax_nopriv_cs_get_comments', [$this, 'get_comments']);
        add_action('wp_ajax_cs_get_comments', [$this, 'get_comments']);

        // Authenticated only
        add_action('wp_ajax_cs_submit_comment', [$this, 'submit_comment']);
        add_action('wp_ajax_cs_save_comment', [$this, 'save_comment']);
        add_action('wp_ajax_cs_delete_comment', [$this, 'delete_comment']);
        add_action('wp_ajax_cs_toggle_reaction', [$this, 'toggle_reaction']);
    }

    /**
     * Get comments for a post
     */
    public function get_comments() {
        check_ajax_referer('wp_rest', 'nonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per_page = 20;

        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $offset = ($page - 1) * $per_page;
        $table = $this->wpdb->prefix . 'myavana_cs_comments';

        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE post_id = %d AND parent_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $post_id,
            $parent_id,
            $per_page,
            $offset
        );

        $comments = $this->wpdb->get_results($query);

        // Enrich comments with metadata
        $comments = array_map(fn($c) => $this->format_comment($c), $comments);

        wp_send_json_success([
            'comments' => $comments,
            'page' => $page,
            'per_page' => $per_page
        ]);
    }

    /**
     * Submit a new comment
     */
    public function submit_comment() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to comment');
        }

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

        if (!$post_id || !$content) {
            wp_send_json_error('Missing required fields');
        }

        if (strlen($content) > 2000) {
            wp_send_json_error('Comment too long (max 2000 characters)');
        }

        // Verify post exists and user can comment on it
        $post = get_post($post_id);
        if (!$post || !comments_open($post_id)) {
            wp_send_json_error('Comments are closed for this post');
        }

        // Create comment
        $table = $this->wpdb->prefix . 'myavana_cs_comments';
        $result = $this->wpdb->insert(
            $table,
            [
                'post_id' => $post_id,
                'user_id' => $this->user_id,
                'parent_id' => $parent_id,
                'content' => $content,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );

        if (!$result) {
            wp_send_json_error('Failed to post comment');
        }

        $comment_id = $this->wpdb->insert_id;
        $comment = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $comment_id)
        );

        wp_send_json_success($this->format_comment($comment));
    }

    /**
     * Save (update) a comment
     */
    public function save_comment() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in');
        }

        $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

        if (!$comment_id || !$content) {
            wp_send_json_error('Missing required fields');
        }

        $table = $this->wpdb->prefix . 'myavana_cs_comments';
        $comment = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $comment_id)
        );

        if (!$comment) {
            wp_send_json_error('Comment not found');
        }

        // Only author or admin can edit
        if ($comment->user_id != $this->user_id && !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Update comment
        $this->wpdb->update(
            $table,
            [
                'content' => $content,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $comment_id],
            ['%s', '%s'],
            ['%d']
        );

        wp_send_json_success(['id' => $comment_id]);
    }

    /**
     * Delete a comment
     */
    public function delete_comment() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in');
        }

        $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

        if (!$comment_id) {
            wp_send_json_error('Invalid comment ID');
        }

        $table = $this->wpdb->prefix . 'myavana_cs_comments';
        $comment = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $table WHERE id = %d", $comment_id)
        );

        if (!$comment) {
            wp_send_json_error('Comment not found');
        }

        // Only author or admin can delete
        if ($comment->user_id != $this->user_id && !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Soft delete (mark as deleted)
        $this->wpdb->update(
            $table,
            ['is_deleted' => 1],
            ['id' => $comment_id],
            ['%d'],
            ['%d']
        );

        wp_send_json_success(['id' => $comment_id]);
    }

    /**
     * Toggle reaction on a comment
     */
    public function toggle_reaction() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in');
        }

        $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
        $reaction = isset($_POST['reaction']) ? sanitize_text_field($_POST['reaction']) : '';

        if (!$comment_id || !$reaction) {
            wp_send_json_error('Missing required fields');
        }

        $table = $this->wpdb->prefix . 'myavana_cs_reactions';

        // Check if user already has this reaction
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM $table WHERE comment_id = %d AND user_id = %d AND reaction = %s",
                $comment_id,
                $this->user_id,
                $reaction
            )
        );

        if ($existing) {
            // Remove reaction
            $this->wpdb->delete(
                $table,
                ['id' => $existing->id],
                ['%d']
            );
        } else {
            // Add reaction
            $this->wpdb->insert(
                $table,
                [
                    'comment_id' => $comment_id,
                    'user_id' => $this->user_id,
                    'reaction' => $reaction,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s']
            );
        }

        // Get updated reaction counts
        $reactions = $this->get_comment_reactions($comment_id);
        wp_send_json_success($reactions);
    }

    /**
     * Get all reactions for a comment
     */
    private function get_comment_reactions($comment_id) {
        $table = $this->wpdb->prefix . 'myavana_cs_reactions';

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT reaction, COUNT(*) as count FROM $table WHERE comment_id = %d GROUP BY reaction",
                $comment_id
            )
        );

        $reactions = [];
        foreach ($results as $row) {
            $reactions[$row->reaction] = (int) $row->count;
        }

        return $reactions;
    }

    /**
     * Format comment with all metadata
     */
    private function format_comment($comment) {
        $user = get_userdata($comment->user_id);

        return [
            'id' => (int) $comment->id,
            'post_id' => (int) $comment->post_id,
            'user_id' => (int) $comment->user_id,
            'parent_id' => (int) ($comment->parent_id ?? 0),
            'content' => $comment->content,
            'display_name' => $user ? $user->display_name : 'Anonymous',
            'user_avatar' => myavana_get_user_avatar_url($comment->user_id, 40),
            'is_verified_journey' => (bool) get_user_meta($comment->user_id, 'myavana_verified_journey', true),
            'created_at' => $comment->created_at,
            'edited_at' => $comment->updated_at,
            'formatted_date' => human_time_diff(strtotime($comment->created_at)) . ' ago',
            'reactions' => $this->get_comment_reactions($comment->id),
            'is_deleted' => (bool) ($comment->is_deleted ?? false)
        ];
    }

    /**
     * Create comments table if it doesn't exist
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $comments_table = $wpdb->prefix . 'myavana_cs_comments';
        $reactions_table = $wpdb->prefix . 'myavana_cs_reactions';

        // Comments table
        $comments_sql = "CREATE TABLE IF NOT EXISTS $comments_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
            content LONGTEXT NOT NULL,
            is_deleted TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Reactions table
        $reactions_sql = "CREATE TABLE IF NOT EXISTS $reactions_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            reaction VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_reaction (comment_id, user_id, reaction),
            KEY comment_id (comment_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($comments_sql);
        dbDelta($reactions_sql);
    }
}

// Initialize on plugin load
add_action('plugins_loaded', function() {
    new CommentSystemHandlers();
});

// Create tables on plugin activation
register_activation_hook(MYAVANA_NEXT_FILE, [CommentSystemHandlers::class, 'create_tables']);
