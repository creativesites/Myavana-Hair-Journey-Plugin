<?php
/**
 * MYAVANA Community Improvements - AJAX Handlers
 *
 * All backend handlers for community improvement features
 * Naming convention: myavana_ci_* to avoid conflicts
 *
 * @package Myavana_Hair_Journey
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_ci_upload_post_media')) {
/**
 * Upload image/video media for community post edits.
 *
 * @param array  $file       Upload file array from $_FILES.
 * @param string $media_type Allowed values: image|video.
 * @return array|WP_Error
 */
function myavana_ci_upload_post_media($file, $media_type = 'image') {
    if (!is_array($file) || empty($file['name'])) {
        return new WP_Error('no_file', 'No file provided');
    }

    $error_code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'Upload failed. Please try again.');
    }

    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $upload_overrides = [
        'test_form' => false,
    ];

    if ($media_type === 'video') {
        $max_size_bytes = 40 * 1024 * 1024; // 40MB
        $file_size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($file_size > $max_size_bytes) {
            return new WP_Error('file_too_large', 'Video file is too large. Maximum size is 40MB.');
        }

        $upload_overrides['mimes'] = [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
        ];
    } else {
        $upload_overrides['mimes'] = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
        ];
    }

    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    if (!is_array($uploaded_file) || isset($uploaded_file['error'])) {
        $message = is_array($uploaded_file) && isset($uploaded_file['error'])
            ? (string) $uploaded_file['error']
            : 'Upload failed. Please try again.';
        return new WP_Error('upload_failed', $message);
    }

    return [
        'url' => esc_url_raw($uploaded_file['url']),
        'file' => $uploaded_file['file'],
    ];
}

/**
 * AJAX Handler: Edit community post
 * Action: myavana_ci_edit_post
 */
function myavana_ci_edit_post_handler() {
    // Verify nonce
    check_ajax_referer('myavana_nonce', 'nonce');

    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in to edit posts');
    }

    // Get and validate inputs
    $post_id = absint($_POST['post_id'] ?? 0);

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    // Get post and verify ownership
    global $wpdb;
    $table_name = $wpdb->prefix . 'myavana_community_posts';
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $post_id
    ));

    if (!$post) {
        wp_send_json_error('Post not found');
    }

    if ($post->user_id != $current_user_id) {
        wp_send_json_error('You do not have permission to edit this post');
    }

    // Check if this is a pin/unpin operation
    if (isset($_POST['is_pinned'])) {
        $is_pinned = absint($_POST['is_pinned']);

        $updated = $wpdb->update(
            $table_name,
            ['is_pinned' => $is_pinned],
            ['id' => $post_id],
            ['%d'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error('Failed to update pin status');
        }

        wp_send_json_success([
            'message' => $is_pinned ? 'Post pinned' : 'Post unpinned',
            'is_pinned' => $is_pinned
        ]);
    }

    // Otherwise, update post content + media fields.
    $title_raw = (string) wp_unslash($_POST['title'] ?? '');
    $content_raw = (string) wp_unslash($_POST['content'] ?? '');
    $title_raw = preg_replace('/\\\\+&#0*39;|\\\\+&#x0*27;|\\\\+&apos;/i', "'", $title_raw);
    $content_raw = preg_replace('/\\\\+&#0*39;|\\\\+&#x0*27;|\\\\+&apos;/i', "'", $content_raw);
    $title_raw = html_entity_decode($title_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content_raw = html_entity_decode($content_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $title = sanitize_text_field($title_raw);
    $content = sanitize_textarea_field($content_raw);
    $post_type = sanitize_text_field($_POST['post_type'] ?? $post->post_type);
    $privacy_level = sanitize_text_field($_POST['privacy_level'] ?? $post->privacy_level);
    $video_url_input = esc_url_raw(wp_unslash($_POST['video_url'] ?? ''));
    $hashtags_input = sanitize_text_field(wp_unslash($_POST['hashtags'] ?? ''));
    $remove_image = !empty($_POST['remove_image']);
    $remove_video = !empty($_POST['remove_video']);

    $allowed_post_types = ['progress', 'transformation', 'routine', 'products', 'tips', 'video', 'general'];
    if (!in_array($post_type, $allowed_post_types, true)) {
        $post_type = 'general';
    }

    $allowed_privacy_levels = ['public', 'followers', 'private'];
    if (!in_array($privacy_level, $allowed_privacy_levels, true)) {
        $privacy_level = 'public';
    }

    if (!$title || !$content) {
        wp_send_json_error('Missing required fields');
    }

    $image_url = (string) ($post->image_url ?? '');
    $video_url = (string) ($post->video_url ?? '');

    if ($remove_image) {
        $image_url = '';
    }

    if ($remove_video) {
        $video_url = '';
    }

    if (!empty($_FILES['image']) && !empty($_FILES['image']['name'])) {
        $image_upload = myavana_ci_upload_post_media($_FILES['image'], 'image');
        if (is_wp_error($image_upload)) {
            wp_send_json_error($image_upload->get_error_message());
        }
        $image_url = $image_upload['url'];
        $remove_image = false;
    }

    if (!empty($_FILES['video']) && !empty($_FILES['video']['name'])) {
        $video_upload = myavana_ci_upload_post_media($_FILES['video'], 'video');
        if (is_wp_error($video_upload)) {
            wp_send_json_error($video_upload->get_error_message());
        }
        $video_url = $video_upload['url'];
        $remove_video = false;
    } elseif ($video_url_input !== '') {
        $video_url = $video_url_input;
        $remove_video = false;
    }

    if ($post_type === 'video' && $video_url === '') {
        wp_send_json_error('Video posts require a video URL or uploaded video.');
    }

    $media_type = 'text';
    if ($video_url !== '') {
        $media_type = 'video';
    } elseif ($image_url !== '') {
        $media_type = 'image';
    }

    $hashtags = implode(',', myavana_ci_normalize_hashtags($hashtags_input, 12));

    // Update post
    $updated_at = current_time('mysql');
    $updated = $wpdb->update(
        $table_name,
        [
            'title' => $title,
            'content' => $content,
            'image_url' => $image_url,
            'video_url' => $video_url,
            'media_type' => $media_type,
            'post_type' => $post_type,
            'privacy_level' => $privacy_level,
            'hashtags' => $hashtags,
            'updated_at' => $updated_at,
        ],
        ['id' => $post_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        wp_send_json_error('Failed to update post');
    }

    $updated_post = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, title, content, image_url, video_url, media_type, post_type, privacy_level, hashtags, likes_count, comments_count, shares_count, is_pinned, created_at, updated_at
         FROM $table_name
         WHERE id = %d",
        $post_id
    ), ARRAY_A);

    if (!$updated_post) {
        wp_send_json_error('Post updated but failed to load updated data');
    }

    $updated_post['id'] = (int) $updated_post['id'];
    $updated_post['user_id'] = (int) $updated_post['user_id'];
    $updated_post['likes_count'] = (int) $updated_post['likes_count'];
    $updated_post['comments_count'] = (int) $updated_post['comments_count'];
    $updated_post['shares_count'] = (int) $updated_post['shares_count'];
    $updated_post['is_pinned'] = (int) $updated_post['is_pinned'];
    $updated_post['formatted_date'] = human_time_diff(strtotime($updated_post['created_at']), current_time('timestamp')) . ' ago';
    $updated_post['display_name'] = wp_get_current_user()->display_name;
    $updated_post['user_avatar'] = get_avatar_url($current_user_id);

    wp_send_json_success([
        'message' => 'Post updated successfully',
        'post' => $updated_post,
    ]);
}
add_action('wp_ajax_myavana_ci_edit_post', 'myavana_ci_edit_post_handler');

/**
 * AJAX Handler: Delete community post
 * Action: myavana_ci_delete_post
 */
function myavana_ci_delete_post_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'myavana_community_posts';

    // Verify ownership
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id FROM $table_name WHERE id = %d",
        $post_id
    ));

    if (!$post || $post->user_id != $current_user_id) {
        wp_send_json_error('Permission denied');
    }

    // Delete related data first
    $comments_table = $wpdb->prefix . 'myavana_post_comments';
    $reactions_table = $wpdb->prefix . 'myavana_ci_post_reactions';
    $bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';

    $wpdb->delete($comments_table, ['post_id' => $post_id], ['%d']);
    $wpdb->delete($reactions_table, ['post_id' => $post_id], ['%d']);
    $wpdb->delete($bookmarks_table, ['post_id' => $post_id], ['%d']);

    // Delete post
    $deleted = $wpdb->delete($table_name, ['id' => $post_id], ['%d']);

    if ($deleted) {
        wp_send_json_success(['message' => 'Post deleted successfully']);
    } else {
        wp_send_json_error('Failed to delete post');
    }
}
add_action('wp_ajax_myavana_ci_delete_post', 'myavana_ci_delete_post_handler');

/**
 * AJAX Handler: Like/Unlike comment
 * Action: myavana_ci_like_comment
 */
function myavana_ci_like_comment_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $comment_id = absint($_POST['comment_id'] ?? 0);
    if (!$comment_id) {
        wp_send_json_error('Invalid comment ID');
    }

    global $wpdb;
    $likes_table = $wpdb->prefix . 'myavana_ci_comment_likes';

    // Check if already liked
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $likes_table WHERE comment_id = %d AND user_id = %d",
        $comment_id,
        $current_user_id
    ));

    if ($existing) {
        // Unlike
        $wpdb->delete(
            $likes_table,
            ['comment_id' => $comment_id, 'user_id' => $current_user_id],
            ['%d', '%d']
        );
        $action = 'unliked';
    } else {
        // Like
        $wpdb->insert(
            $likes_table,
            [
                'comment_id' => $comment_id,
                'user_id' => $current_user_id,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
        $action = 'liked';
    }

    // Get updated count
    $likes_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $likes_table WHERE comment_id = %d",
        $comment_id
    ));

    wp_send_json_success([
        'action' => $action,
        'likes_count' => (int)$likes_count
    ]);
}
add_action('wp_ajax_myavana_ci_like_comment', 'myavana_ci_like_comment_handler');

/**
 * AJAX Handler: React to post (like, love, celebrate, insightful)
 * Action: myavana_ci_react_to_post
 */
function myavana_ci_react_to_post_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $reaction_type = sanitize_text_field($_POST['reaction_type'] ?? '');

    // Valid reaction types
    $valid_reactions = ['like', 'love', 'celebrate', 'insightful'];
    if (!$post_id || !in_array($reaction_type, $valid_reactions)) {
        wp_send_json_error('Invalid parameters');
    }

    global $wpdb;
    $reactions_table = $wpdb->prefix . 'myavana_ci_post_reactions';

    // Check existing reaction
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $reactions_table WHERE post_id = %d AND user_id = %d",
        $post_id,
        $current_user_id
    ));

    if ($existing) {
        if ($existing->reaction_type === $reaction_type) {
            // Remove reaction (toggle off)
            $wpdb->delete(
                $reactions_table,
                ['post_id' => $post_id, 'user_id' => $current_user_id],
                ['%d', '%d']
            );
            $action = 'removed';
        } else {
            // Update to new reaction
            $wpdb->update(
                $reactions_table,
                ['reaction_type' => $reaction_type, 'created_at' => current_time('mysql')],
                ['post_id' => $post_id, 'user_id' => $current_user_id],
                ['%s', '%s'],
                ['%d', '%d']
            );
            $action = 'changed';
        }
    } else {
        // Add new reaction
        $wpdb->insert(
            $reactions_table,
            [
                'post_id' => $post_id,
                'user_id' => $current_user_id,
                'reaction_type' => $reaction_type,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s']
        );
        $action = 'added';
    }

    // Get all reaction counts for this post
    $reactions = $wpdb->get_results($wpdb->prepare(
        "SELECT reaction_type, COUNT(*) as count
         FROM $reactions_table
         WHERE post_id = %d
         GROUP BY reaction_type",
        $post_id
    ), ARRAY_A);

    $counts = [];
    foreach ($reactions as $r) {
        $counts[$r['reaction_type']] = (int)$r['count'];
    }

    // Check if current user has any reaction
    $user_reaction = $wpdb->get_var($wpdb->prepare(
        "SELECT reaction_type FROM $reactions_table
         WHERE post_id = %d AND user_id = %d",
        $post_id,
        $current_user_id
    ));

    // Ensure all reaction types are represented
    $all_reactions = ['like' => 0, 'love' => 0, 'celebrate' => 0, 'insightful' => 0];
    $reactions = array_merge($all_reactions, $counts);

    wp_send_json_success([
        'action' => $action,
        'reactions' => $reactions,
        'user_reaction' => $user_reaction,
        'total_count' => array_sum($reactions)
    ]);
}
add_action('wp_ajax_myavana_ci_react_to_post', 'myavana_ci_react_to_post_handler');

/**
 * AJAX Handler: Reply to comment (threaded comments)
 * Action: myavana_ci_reply_to_comment
 */
function myavana_ci_reply_to_comment_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $parent_comment_id = absint($_POST['parent_comment_id'] ?? ($_POST['parent_id'] ?? 0));
    $content = sanitize_textarea_field($_POST['content'] ?? '');

    if (!$post_id || !$parent_comment_id || !$content) {
        wp_send_json_error('Missing required fields');
    }

    global $wpdb;
    $comments_table = $wpdb->prefix . 'myavana_post_comments';

    // Verify parent comment exists
    $parent_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $comments_table WHERE id = %d AND post_id = %d",
        $parent_comment_id,
        $post_id
    ));

    if (!$parent_exists) {
        wp_send_json_error('Parent comment not found');
    }

    // Insert reply
    $inserted = $wpdb->insert(
        $comments_table,
        [
            'post_id' => $post_id,
            'user_id' => $current_user_id,
            'parent_id' => $parent_comment_id,
            'content' => $content,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%d', '%d', '%s', '%s']
    );

    if (!$inserted) {
        wp_send_json_error('Failed to add reply');
    }

    $comment_id = $wpdb->insert_id;

    // Get user info
    $user = get_userdata($current_user_id);

    wp_send_json_success([
        'message' => 'Reply added successfully',
        'comment' => [
            'id' => $comment_id,
            'post_id' => $post_id,
            'parent_id' => $parent_comment_id,
            'user_id' => $current_user_id,
            'display_name' => $user->display_name,
            'user_avatar' => get_avatar_url($current_user_id),
            'content' => $content,
            'likes_count' => 0,
            'is_liked' => false,
            'formatted_date' => 'Just now'
        ]
    ]);
}
add_action('wp_ajax_myavana_ci_reply_to_comment', 'myavana_ci_reply_to_comment_handler');

/**
 * AJAX Handler: Report inappropriate content
 * Action: myavana_ci_report_content
 */
function myavana_ci_report_content_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $content_type = sanitize_text_field($_POST['content_type'] ?? '');
    $content_id = absint($_POST['content_id'] ?? 0);
    $reason = sanitize_text_field($_POST['reason'] ?? '');
    $details = sanitize_textarea_field($_POST['details'] ?? '');

    if (!$content_type || !$content_id || !$reason) {
        wp_send_json_error('Missing required fields');
    }

    if (!in_array($content_type, ['post', 'comment'])) {
        wp_send_json_error('Invalid content type');
    }

    global $wpdb;
    $reports_table = $wpdb->prefix . 'myavana_ci_content_reports';

    // Check if already reported by this user
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $reports_table
         WHERE content_type = %s AND content_id = %d AND reporter_user_id = %d",
        $content_type,
        $content_id,
        $current_user_id
    ));

    if ($existing) {
        wp_send_json_error('You have already reported this content');
    }

    // Insert report
    $inserted = $wpdb->insert(
        $reports_table,
        [
            'content_type' => $content_type,
            'content_id' => $content_id,
            'reporter_user_id' => $current_user_id,
            'reason' => $reason,
            'details' => $details,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%d', '%s', '%s', '%s', '%s']
    );

    if (!$inserted) {
        wp_send_json_error('Failed to submit report');
    }

    wp_send_json_success([
        'message' => 'Thank you for your report. We will review it shortly.'
    ]);
}
add_action('wp_ajax_myavana_ci_report_content', 'myavana_ci_report_content_handler');

/**
 * AJAX Handler: Load more comments with pagination
 * Action: myavana_ci_load_comments
 */
function myavana_ci_load_comments_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $post_id = absint($_POST['post_id'] ?? 0);
    $page = absint($_POST['page'] ?? 1);
    $per_page = absint($_POST['per_page'] ?? 10);
    $offset = ($page - 1) * $per_page;

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $comments_table = $wpdb->prefix . 'myavana_post_comments';
    $likes_table = $wpdb->prefix . 'myavana_ci_comment_likes';

    // Get comments (only top-level)
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name,
         (SELECT COUNT(*) FROM $likes_table WHERE comment_id = c.id) as likes_count,
         (SELECT COUNT(*) FROM $likes_table WHERE comment_id = c.id AND user_id = %d) as is_liked,
         (SELECT COUNT(*) FROM $comments_table WHERE parent_id = c.id) as replies_count
         FROM $comments_table c
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
         WHERE c.post_id = %d AND (c.parent_id = 0 OR c.parent_id IS NULL)
         ORDER BY c.created_at DESC
         LIMIT %d OFFSET %d",
        $current_user_id,
        $post_id,
        $per_page,
        $offset
    ), ARRAY_A);

    // Get total count
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $comments_table
         WHERE post_id = %d AND (parent_id = 0 OR parent_id IS NULL)",
        $post_id
    ));

    // Format comments
    $formatted_comments = [];
    foreach ($comments as $comment) {
        $formatted_comments[] = [
            'id' => (int)$comment['id'],
            'post_id' => (int)$post_id,
            'user_id' => (int)$comment['user_id'],
            'display_name' => $comment['display_name'],
            'user_avatar' => get_avatar_url($comment['user_id']),
            'content' => $comment['content'],
            'likes_count' => (int)$comment['likes_count'],
            'is_liked' => (bool)$comment['is_liked'],
            'reply_count' => (int)$comment['replies_count'],
            'formatted_date' => human_time_diff(strtotime($comment['created_at']), current_time('timestamp')) . ' ago'
        ];
    }

    wp_send_json_success([
        'comments' => $formatted_comments,
        'has_more' => ($offset + $per_page) < $total,
        'total_count' => (int)$total
    ]);
}
add_action('wp_ajax_myavana_ci_load_comments', 'myavana_ci_load_comments_handler');
add_action('wp_ajax_nopriv_myavana_ci_load_comments', 'myavana_ci_load_comments_handler');

/**
 * AJAX Handler: Get comment replies
 * Action: myavana_ci_get_replies
 */
function myavana_ci_get_replies_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $comment_id = absint($_POST['comment_id'] ?? 0);

    if (!$comment_id) {
        wp_send_json_error('Invalid comment ID');
    }

    global $wpdb;
    $current_user_id = get_current_user_id();
    $comments_table = $wpdb->prefix . 'myavana_post_comments';
    $likes_table = $wpdb->prefix . 'myavana_ci_comment_likes';

    // Get replies
    $replies = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name,
         (SELECT COUNT(*) FROM $likes_table WHERE comment_id = c.id) as likes_count,
         (SELECT COUNT(*) FROM $likes_table WHERE comment_id = c.id AND user_id = %d) as is_liked
         FROM $comments_table c
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
         WHERE c.parent_id = %d
         ORDER BY c.created_at ASC",
        $current_user_id,
        $comment_id
    ), ARRAY_A);

    // Format replies
    $formatted_replies = [];
    foreach ($replies as $reply) {
        $formatted_replies[] = [
            'id' => (int)$reply['id'],
            'user_id' => (int)$reply['user_id'],
            'display_name' => $reply['display_name'],
            'user_avatar' => get_avatar_url($reply['user_id']),
            'content' => $reply['content'],
            'likes_count' => (int)$reply['likes_count'],
            'is_liked' => (bool)$reply['is_liked'],
            'formatted_date' => human_time_diff(strtotime($reply['created_at']), current_time('timestamp')) . ' ago'
        ];
    }

    wp_send_json_success([
        'replies' => $formatted_replies
    ]);
}
add_action('wp_ajax_myavana_ci_get_replies', 'myavana_ci_get_replies_handler');
add_action('wp_ajax_nopriv_myavana_ci_get_replies', 'myavana_ci_get_replies_handler');

/**
 * AJAX Handler: Manage bookmark collections
 * Action: myavana_ci_manage_collection
 */
function myavana_ci_manage_collection_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $action_type = sanitize_text_field($_POST['action_type'] ?? ($_POST['operation'] ?? ''));
    $collection_id = absint($_POST['collection_id'] ?? 0);
    $collection_name = sanitize_text_field($_POST['collection_name'] ?? ($_POST['name'] ?? ''));
    $post_id = absint($_POST['post_id'] ?? 0);
    $collection_ids_raw = sanitize_text_field($_POST['collection_ids'] ?? '');
    $has_collection_ids_param = array_key_exists('collection_ids', $_POST);

    global $wpdb;
    $collections_table = $wpdb->prefix . 'myavana_ci_bookmark_collections';
    $collection_items_table = $wpdb->prefix . 'myavana_ci_collection_items';

    switch ($action_type) {
        case 'create':
            if (!$collection_name) {
                wp_send_json_error('Collection name is required');
            }

            $inserted = $wpdb->insert(
                $collections_table,
                [
                    'user_id' => $current_user_id,
                    'name' => $collection_name,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s']
            );

            if ($inserted) {
                wp_send_json_success([
                    'collection_id' => $wpdb->insert_id,
                    'name' => $collection_name
                ]);
            } else {
                wp_send_json_error('Failed to create collection');
            }
            break;

        case 'delete':
            $owner = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM $collections_table WHERE id = %d",
                $collection_id
            ));

            if ($owner != $current_user_id) {
                wp_send_json_error('Permission denied');
            }

            $wpdb->delete($collection_items_table, ['collection_id' => $collection_id], ['%d']);
            $wpdb->delete($collections_table, ['id' => $collection_id], ['%d']);

            wp_send_json_success(['message' => 'Collection deleted']);
            break;

        case 'add_post':
            if (!$post_id) {
                wp_send_json_error('Invalid post ID');
            }

            // Multi-collection sync (preferred path from frontend)
            $selected_collection_ids = array_values(array_filter(array_map('absint', explode(',', $collection_ids_raw))));
            if ($has_collection_ids_param) {
                $user_collection_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM $collections_table WHERE user_id = %d",
                    $current_user_id
                ));
                $user_collection_ids = array_map('intval', $user_collection_ids);

                if (!empty($selected_collection_ids)) {
                    $invalid_ids = array_diff($selected_collection_ids, $user_collection_ids);
                    if (!empty($invalid_ids)) {
                        wp_send_json_error('Permission denied for one or more collections');
                    }
                }

                // Remove this post from all of the current user's collections first
                if (!empty($user_collection_ids)) {
                    $placeholders = implode(',', array_fill(0, count($user_collection_ids), '%d'));
                    $query = $wpdb->prepare(
                        "DELETE FROM $collection_items_table
                         WHERE post_id = %d AND collection_id IN ($placeholders)",
                        array_merge([$post_id], $user_collection_ids)
                    );
                    $wpdb->query($query);
                }

                // Re-add to selected collections
                foreach ($selected_collection_ids as $selected_collection_id) {
                    $wpdb->insert(
                        $collection_items_table,
                        [
                            'collection_id' => $selected_collection_id,
                            'post_id' => $post_id,
                            'added_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s']
                    );
                }

                wp_send_json_success([
                    'message' => !empty($selected_collection_ids) ? 'Post saved to selected collections' : 'Post removed from all collections'
                ]);
            }

            // Backward-compatible single-collection path
            $owner = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM $collections_table WHERE id = %d",
                $collection_id
            ));

            if ($owner != $current_user_id) {
                wp_send_json_error('Permission denied');
            }

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $collection_items_table
                 WHERE collection_id = %d AND post_id = %d",
                $collection_id,
                $post_id
            ));

            if ($exists) {
                wp_send_json_error('Post already in this collection');
            }

            $wpdb->insert(
                $collection_items_table,
                [
                    'collection_id' => $collection_id,
                    'post_id' => $post_id,
                    'added_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s']
            );

            wp_send_json_success(['message' => 'Added to collection']);
            break;

        case 'remove_post':
            $wpdb->delete(
                $collection_items_table,
                ['collection_id' => $collection_id, 'post_id' => $post_id],
                ['%d', '%d']
            );

            wp_send_json_success(['message' => 'Removed from collection']);
            break;

        case 'list':
            if ($post_id) {
                $collections = $wpdb->get_results($wpdb->prepare(
                    "SELECT c.*,
                            COUNT(ci.id) as post_count,
                            MAX(CASE WHEN ci.post_id = %d THEN 1 ELSE 0 END) as has_post
                     FROM $collections_table c
                     LEFT JOIN $collection_items_table ci ON c.id = ci.collection_id
                     WHERE c.user_id = %d
                     GROUP BY c.id
                     ORDER BY c.created_at DESC",
                    $post_id,
                    $current_user_id
                ), ARRAY_A);
            } else {
                $collections = $wpdb->get_results($wpdb->prepare(
                    "SELECT c.*, COUNT(ci.id) as post_count, 0 as has_post
                     FROM $collections_table c
                     LEFT JOIN $collection_items_table ci ON c.id = ci.collection_id
                     WHERE c.user_id = %d
                     GROUP BY c.id
                     ORDER BY c.created_at DESC",
                    $current_user_id
                ), ARRAY_A);
            }

            wp_send_json_success(['collections' => $collections]);
            break;

        default:
            wp_send_json_error('Invalid action type');
    }
}
add_action('wp_ajax_myavana_ci_manage_collection', 'myavana_ci_manage_collection_handler');

/**
 * AJAX Handler: Get post analytics
 * Action: myavana_ci_get_post_analytics
 */
function myavana_ci_get_post_analytics_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $analytics_type = sanitize_text_field($_POST['analytics_type'] ?? 'reactions');

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    global $wpdb;
    $posts_table = $wpdb->prefix . 'myavana_community_posts';
    $post_owner = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM $posts_table WHERE id = %d",
        $post_id
    ));

    if ($post_owner != $current_user_id) {
        wp_send_json_error('You can only view analytics for your own posts');
    }

    $data = [];

    switch ($analytics_type) {
        case 'reactions':
            $reactions_table = $wpdb->prefix . 'myavana_ci_post_reactions';
            $reactions = $wpdb->get_results($wpdb->prepare(
                "SELECT r.reaction_type, r.created_at, u.display_name, u.ID as user_id
                 FROM $reactions_table r
                 LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
                 WHERE r.post_id = %d
                 ORDER BY r.created_at DESC
                 LIMIT 100",
                $post_id
            ), ARRAY_A);

            foreach ($reactions as &$reaction) {
                $reaction['user_avatar'] = get_avatar_url($reaction['user_id']);
                $reaction['formatted_date'] = human_time_diff(strtotime($reaction['created_at']), current_time('timestamp')) . ' ago';
            }

            $data['reactions'] = $reactions;
            break;

        default:
            wp_send_json_error('Invalid analytics type');
    }

    wp_send_json_success($data);
}
add_action('wp_ajax_myavana_ci_get_post_analytics', 'myavana_ci_get_post_analytics_handler');

/**
 * AJAX Handler: Manage draft posts
 * Action: myavana_ci_manage_draft
 */
function myavana_ci_manage_draft_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $action_type = sanitize_text_field($_POST['action_type'] ?? ($_POST['operation'] ?? ''));
    $draft_id = absint($_POST['draft_id'] ?? 0);

    global $wpdb;
    $drafts_table = $wpdb->prefix . 'myavana_ci_post_drafts';

    switch ($action_type) {
        case 'save':
            $title = sanitize_text_field($_POST['title'] ?? '');
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            $post_type = sanitize_text_field($_POST['post_type'] ?? 'general');

            if ($draft_id) {
                $wpdb->update(
                    $drafts_table,
                    [
                        'title' => $title,
                        'content' => $content,
                        'post_type' => $post_type,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $draft_id, 'user_id' => $current_user_id],
                    ['%s', '%s', '%s', '%s'],
                    ['%d', '%d']
                );

                wp_send_json_success([
                    'draft_id' => $draft_id,
                    'message' => 'Draft updated'
                ]);
            } else {
                $wpdb->insert(
                    $drafts_table,
                    [
                        'user_id' => $current_user_id,
                        'title' => $title,
                        'content' => $content,
                        'post_type' => $post_type,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s']
                );

                wp_send_json_success([
                    'draft_id' => $wpdb->insert_id,
                    'message' => 'Draft saved'
                ]);
            }
            break;

        case 'load':
            $drafts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $drafts_table
                 WHERE user_id = %d
                 ORDER BY updated_at DESC",
                $current_user_id
            ), ARRAY_A);

            wp_send_json_success(['drafts' => $drafts]);
            break;

        case 'delete':
            $wpdb->delete(
                $drafts_table,
                ['id' => $draft_id, 'user_id' => $current_user_id],
                ['%d', '%d']
            );

            wp_send_json_success(['message' => 'Draft deleted']);
            break;

        case 'publish':
            $draft = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $drafts_table
                 WHERE id = %d AND user_id = %d",
                $draft_id,
                $current_user_id
            ), ARRAY_A);

            if (!$draft) {
                wp_send_json_error('Draft not found');
            }

            $posts_table = $wpdb->prefix . 'myavana_community_posts';
            $wpdb->insert(
                $posts_table,
                [
                    'user_id' => $current_user_id,
                    'title' => $draft['title'],
                    'content' => $draft['content'],
                    'post_type' => $draft['post_type'],
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );

            $post_id = $wpdb->insert_id;
            $wpdb->delete($drafts_table, ['id' => $draft_id], ['%d']);

            wp_send_json_success([
                'post_id' => $post_id,
                'message' => 'Draft published successfully'
            ]);
            break;

        default:
            wp_send_json_error('Invalid action type');
    }
}
add_action('wp_ajax_myavana_ci_manage_draft', 'myavana_ci_manage_draft_handler');

/**
 * Normalize hashtag input into an array of clean tags.
 */
function myavana_ci_normalize_hashtags($hashtags, $limit = 10) {
    $candidates = [];

    if (is_array($hashtags)) {
        $candidates = $hashtags;
    } elseif (is_string($hashtags)) {
        $candidates = preg_split('/[\s,]+/', $hashtags);
    }

    $normalized = [];
    foreach ($candidates as $tag) {
        $tag = strtolower(trim((string) $tag));
        $tag = preg_replace('/^#+/', '', $tag);
        $tag = preg_replace('/[^a-z0-9_]/', '', $tag);
        if (strlen($tag) > 1) {
            $normalized[] = $tag;
        }
    }

    return array_slice(array_values(array_unique($normalized)), 0, $limit);
}

/**
 * Build a deterministic fallback AI response for community post assistance.
 */
function myavana_ci_build_post_ai_fallback($assist_type, $title, $content, $post_type) {
    $post_type_label_map = [
        'progress' => 'progress update',
        'transformation' => 'transformation update',
        'routine' => 'routine update',
        'products' => 'product review',
        'tips' => 'tips post',
        'general' => 'community update'
    ];

    $post_type_label = $post_type_label_map[$post_type] ?? 'community update';
    $raw_text = trim($title . ' ' . $content);
    preg_match_all('/#?([a-zA-Z0-9_]{3,24})/', $raw_text, $matches);
    $keyword_tags = isset($matches[1]) ? $matches[1] : [];
    $fallback_tags = myavana_ci_normalize_hashtags(array_merge($keyword_tags, ['hairjourney', 'myavana', $post_type]));

    if ($assist_type === 'hashtags') {
        return [
            'title' => $title,
            'content' => $content,
            'hashtags' => !empty($fallback_tags) ? $fallback_tags : ['hairjourney', 'myavana'],
            'source' => 'fallback',
            'message' => 'Hashtags are ready.'
        ];
    }

    if ($assist_type === 'improve') {
        $improved_content = $content;
        if ($improved_content === '') {
            $improved_content = 'Sharing a quick update from my hair journey.';
        }
        if (substr($improved_content, -1) !== '.') {
            $improved_content .= '.';
        }

        return [
            'title' => $title,
            'content' => $improved_content . ' Staying consistent and learning what works best for my hair.',
            'hashtags' => !empty($fallback_tags) ? $fallback_tags : ['hairjourney', 'myavana'],
            'source' => 'fallback',
            'message' => 'Draft polished and ready to post.'
        ];
    }

    $generated_title = $title !== '' ? $title : 'My ' . ucwords($post_type_label);
    $generated_content = $content !== ''
        ? $content
        : 'Sharing today\'s ' . $post_type_label . '. What has helped your hair thrive this week?';

    return [
        'title' => $generated_title,
        'content' => $generated_content,
        'hashtags' => !empty($fallback_tags) ? $fallback_tags : ['hairjourney', 'myavana'],
        'source' => 'fallback',
        'message' => 'Caption generated. You can edit before posting.'
    ];
}

/**
 * Call Gemini text model for community AI assist.
 */
function myavana_ci_call_gemini_text($prompt) {
    $api_key = trim((string) get_option('myavana_gemini_api_key'));
    if ($api_key === '') {
        return new WP_Error('missing_key', 'Gemini API key not configured');
    }

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        'gemini-2.0-flash',
        rawurlencode($api_key)
    );

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30,
        'body' => wp_json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.6,
                'maxOutputTokens' => 600,
                'responseMimeType' => 'application/json'
            ]
        ])
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status !== 200) {
        return new WP_Error('gemini_status', $body['error']['message'] ?? 'Gemini request failed');
    }

    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        return new WP_Error('empty_response', 'No AI response generated');
    }

    return $text;
}

/**
 * Strip markdown code fences from AI JSON output.
 */
function myavana_ci_strip_json_fences($text) {
    $text = trim((string) $text);
    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches)) {
        return trim($matches[1]);
    }
    return $text;
}

/**
 * AJAX Handler: AI post assistance for community composer.
 * Action: myavana_ci_ai_post_assist
 */
function myavana_ci_ai_post_assist_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    $assist_type = sanitize_text_field($_POST['assist_type'] ?? '');
    $title = sanitize_text_field($_POST['title'] ?? '');
    $content = sanitize_textarea_field($_POST['content'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? 'general');

    if (!in_array($assist_type, ['caption', 'improve', 'hashtags'], true)) {
        wp_send_json_error('Invalid AI assist type');
    }

    $prompt = '';
    if ($assist_type === 'caption') {
        $prompt = "Create engaging copy for a hair-care community post.\n"
            . "Post type: {$post_type}\nCurrent title: {$title}\nCurrent content: {$content}\n"
            . "Return strict JSON with keys: title, content, hashtags (array of plain words, no #), message.";
    } elseif ($assist_type === 'improve') {
        $prompt = "Improve this hair-care community post draft for clarity, warmth, and authenticity.\n"
            . "Post type: {$post_type}\nTitle: {$title}\nContent: {$content}\n"
            . "Return strict JSON with keys: title, content, hashtags (array of plain words, no #), message.";
    } else {
        $prompt = "Generate relevant social hashtags for this hair-care community post.\n"
            . "Post type: {$post_type}\nTitle: {$title}\nContent: {$content}\n"
            . "Return strict JSON with keys: hashtags (array of plain words, no #), message.";
    }

    $fallback_payload = myavana_ci_build_post_ai_fallback($assist_type, $title, $content, $post_type);
    $ai_result = myavana_ci_call_gemini_text($prompt);

    if (is_wp_error($ai_result)) {
        wp_send_json_success($fallback_payload);
    }

    $decoded = json_decode(myavana_ci_strip_json_fences($ai_result), true);
    if (!is_array($decoded)) {
        wp_send_json_success($fallback_payload);
    }

    $final_title = sanitize_text_field($decoded['title'] ?? $fallback_payload['title']);
    $final_content = sanitize_textarea_field($decoded['content'] ?? $fallback_payload['content']);
    $final_message = sanitize_text_field($decoded['message'] ?? $fallback_payload['message']);
    $final_hashtags = myavana_ci_normalize_hashtags($decoded['hashtags'] ?? $fallback_payload['hashtags']);

    if (empty($final_hashtags)) {
        $final_hashtags = $fallback_payload['hashtags'];
    }

    wp_send_json_success([
        'title' => $final_title,
        'content' => $final_content,
        'hashtags' => $final_hashtags,
        'source' => 'gemini',
        'message' => $final_message
    ]);
}
add_action('wp_ajax_myavana_ci_ai_post_assist', 'myavana_ci_ai_post_assist_handler');

/**
 * AJAX Handler: Get saved community posts for current user.
 * Action: myavana_ci_get_saved_posts
 */
function myavana_ci_get_saved_posts_handler() {
    check_ajax_referer('myavana_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    global $wpdb;
    $bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';
    $posts_table = $wpdb->prefix . 'myavana_community_posts';
    $collections_table = $wpdb->prefix . 'myavana_ci_bookmark_collections';
    $collection_items_table = $wpdb->prefix . 'myavana_ci_collection_items';

    $has_collections_table = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $collections_table));
    $has_collection_items_table = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $collection_items_table));

    if ($has_collections_table && $has_collection_items_table) {
        // Saved posts can originate from:
        // 1) quick bookmark table (myavana_post_bookmarks)
        // 2) collection saves (myavana_ci_collection_items + myavana_ci_bookmark_collections)
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT saved.post_id AS id,
                    p.user_id,
                    p.title,
                    p.content,
                    p.image_url,
                    p.video_url,
                    p.post_type,
                    p.created_at,
                    u.display_name,
                    MAX(saved.saved_at) AS saved_at
             FROM (
                SELECT b.post_id, b.bookmarked_at AS saved_at
                FROM {$bookmarks_table} b
                WHERE b.user_id = %d

                UNION ALL

                SELECT ci.post_id, ci.added_at AS saved_at
                FROM {$collections_table} c
                INNER JOIN {$collection_items_table} ci ON ci.collection_id = c.id
                WHERE c.user_id = %d
             ) saved
             INNER JOIN {$posts_table} p ON p.id = saved.post_id
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             GROUP BY saved.post_id, p.user_id, p.title, p.content, p.image_url, p.video_url, p.post_type, p.created_at, u.display_name
             ORDER BY saved_at DESC
             LIMIT 100",
            $current_user_id,
            $current_user_id
        ), ARRAY_A);
    } else {
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.user_id, p.title, p.content, p.image_url, p.video_url, p.post_type, p.created_at, u.display_name
             FROM {$bookmarks_table} b
             INNER JOIN {$posts_table} p ON b.post_id = p.id
             LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
             WHERE b.user_id = %d
             ORDER BY b.bookmarked_at DESC
             LIMIT 100",
            $current_user_id
        ), ARRAY_A);
    }

    foreach ($posts as &$post) {
        $post['id'] = (int) $post['id'];
        $post['user_id'] = (int) $post['user_id'];
        $post['formatted_date'] = human_time_diff(strtotime($post['created_at']), current_time('timestamp')) . ' ago';
    }

    wp_send_json_success(['posts' => $posts]);
}
add_action('wp_ajax_myavana_ci_get_saved_posts', 'myavana_ci_get_saved_posts_handler');

/**
 * AJAX Handler: Check for new activity
 * Action: myavana_ci_check_activity
 */
function myavana_ci_check_activity_handler() {
    // Verify nonce
    check_ajax_referer('myavana_nonce', 'nonce');

    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    // Get last check timestamp
    $last_check = absint($_POST['last_check'] ?? 0);
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');

    global $wpdb;
    $posts_table = $wpdb->prefix . 'myavana_community_posts';

    // Build query based on filter
    $where_clauses = ["p.created_at > FROM_UNIXTIME(%d)"];
    $query_args = [$last_check];

    switch ($filter) {
        case 'following':
            $following_ids = get_user_meta($current_user_id, 'myavana_following', true);
            if (empty($following_ids)) {
                wp_send_json_success([
                    'has_new_activity' => false,
                    'count' => 0
                ]);
                return;
            }
            $placeholders = implode(',', array_fill(0, count($following_ids), '%d'));
            $where_clauses[] = "p.user_id IN ($placeholders)";
            $query_args = array_merge($query_args, $following_ids);
            break;

        case 'my-posts':
            $where_clauses[] = "p.user_id = %d";
            $query_args[] = $current_user_id;
            break;

        case 'all':
        default:
            // No additional filter needed
            break;
    }

    $where_sql = implode(' AND ', $where_clauses);

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $posts_table p WHERE $where_sql",
        ...$query_args
    ));

    wp_send_json_success([
        'has_new_activity' => $count > 0,
        'count' => intval($count)
    ]);
}
add_action('wp_ajax_myavana_ci_check_activity', 'myavana_ci_check_activity_handler');

/**
 * AJAX Handler: Get user community posts for profile page
 * Action: get_user_community_posts
 */
function myavana_get_user_community_posts_handler() {
    // Verify nonce
    check_ajax_referer('myavana_ajax_nonce', 'nonce');

    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    // Get user ID (can view own or others' posts)
    $user_id = absint($_POST['user_id'] ?? $current_user_id);

    global $wpdb;
    $posts_table = $wpdb->prefix . 'myavana_community_posts';
    $likes_table = $wpdb->prefix . 'myavana_post_likes';
    $comments_table = $wpdb->prefix . 'myavana_post_comments';

    // Get user's posts
    // $posts = $wpdb->get_results($wpdb->prepare(
    //     "SELECT id, user_id, content, image_url, visibility, created_at
    //      FROM $posts_table
    //      WHERE user_id = %d AND status = 'published'
    //      ORDER BY created_at DESC
    //      LIMIT 50",
    //     $user_id
    // ));   
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, content, image_url, privacy_level, created_at
        FROM $posts_table
        WHERE user_id = %d
        ORDER BY created_at DESC
        LIMIT 50",
        $user_id
    ));


    // Add engagement counts for each post
    foreach ($posts as &$post) {
        $post->likes_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $likes_table WHERE post_id = %d",
            $post->id
        )));

        $post->comments_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $comments_table WHERE post_id = %d",
            $post->id
        )));

        $post->formatted_date = human_time_diff(strtotime($post->created_at)) . ' ago';
        $post->user_avatar = get_avatar_url($post->user_id);
    }
    // Enhance posts with additional data
        // foreach ($posts as &$post) {
        //     $post->user_avatar = get_avatar_url($post->user_id);
        //     // $post->user_profile_url = '#'; // Could be customized
        //     // $post->is_liked = $this->is_post_liked($post->id, $user_id);
        //     // $post->is_bookmarked = $this->is_post_bookmarked($post->id, $user_id);
        //     // $post->recent_comments = $this->get_recent_comments($post->id, 3);
        //     // $post->formatted_date = human_time_diff(strtotime($post->created_at)) . ' ago';

        //     // Add reaction data
        //     // $reactions_data = $this->get_post_reactions($post->id, $user_id);
        //     // $post->reactions = $reactions_data['reactions'];
        //     // $post->user_reaction = $reactions_data['user_reaction'];
        // }

    wp_send_json_success($posts);
}
add_action('wp_ajax_get_user_community_posts', 'myavana_get_user_community_posts_handler');

/**
     * Get user's social stats
     */
    function get_user_social_stats($user_id = null) {
        if (!$user_id) {
            $user_id = $this->user_id;
        }
        
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        $likes_table = $wpdb->prefix . 'myavana_post_likes';
        
        $stats = array();
        
        // Posts count
        $stats['posts_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE user_id = %d",
            $user_id
        ));
        
        // Followers count
        $stats['followers_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE following_id = %d",
            $user_id
        ));
        
        // Following count
        $stats['following_count'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE follower_id = %d",
            $user_id
        ));
        
        // Total likes received
        $stats['total_likes'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(p.likes_count) FROM $posts_table p WHERE p.user_id = %d",
            $user_id
        )) ?: 0;
        
        // Engagement rate (last 30 days)
        $stats['recent_engagement'] = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(p.likes_count + p.comments_count) 
             FROM $posts_table p 
             WHERE p.user_id = %d AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        )) ?: 0;
        
        return $stats;
    }
    
    // Helper methods
    function is_post_liked($post_id, $user_id) {
        global $wpdb;

        $likes_table = $wpdb->prefix . 'myavana_post_likes';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $likes_table WHERE post_id = %d AND user_id = %d",
            $post_id, $user_id
        ));
    }

    function is_post_bookmarked($post_id, $user_id) {
        global $wpdb;

        $bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $bookmarks_table WHERE post_id = %d AND user_id = %d",
            $post_id, $user_id
        ));
    }
    
    function get_recent_comments($post_id, $limit = 3) {
        global $wpdb;

        $comments_table = $wpdb->prefix . 'myavana_post_comments';
        $users_table = $wpdb->users;

        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name FROM $comments_table c
             LEFT JOIN $users_table u ON c.user_id = u.ID
             WHERE c.post_id = %d AND c.parent_id = 0
             ORDER BY c.created_at DESC LIMIT %d",
            $post_id, $limit
        ));

        foreach ($comments as &$comment) {
            $comment->user_avatar = get_avatar_url($comment->user_id, 32);
            $comment->formatted_date = human_time_diff(strtotime($comment->created_at)) . ' ago';
        }

        return $comments;
    }

    /**
     * Get reactions data for a post
     */
    function get_post_reactions($post_id, $user_id) {
        global $wpdb;

        $reactions_table = $wpdb->prefix . 'myavana_ci_post_reactions';

        // Get all reaction counts
        $reactions = $wpdb->get_results($wpdb->prepare(
            "SELECT reaction_type, COUNT(*) as count
             FROM $reactions_table
             WHERE post_id = %d
             GROUP BY reaction_type",
            $post_id
        ), ARRAY_A);

        $counts = [];
        foreach ($reactions as $r) {
            $counts[$r['reaction_type']] = (int)$r['count'];
        }

        // Ensure all reaction types are represented
        $all_reactions = ['like' => 0, 'love' => 0, 'celebrate' => 0, 'insightful' => 0];
        $reaction_counts = array_merge($all_reactions, $counts);

        // Get user's reaction
        $user_reaction = $wpdb->get_var($wpdb->prepare(
            "SELECT reaction_type FROM $reactions_table
             WHERE post_id = %d AND user_id = %d",
            $post_id,
            $user_id
        ));

        return [
            'reactions' => $reaction_counts,
            'user_reaction' => $user_reaction
        ];
    }

/**
 * AJAX Handler: Save profile settings
 * Action: save_profile_settings
 */
function myavana_save_profile_settings_handler() {
    // Verify nonce
    check_ajax_referer('myavana_ajax_nonce', 'nonce');

    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_send_json_error('You must be logged in');
    }

    // Get settings from POST
    $settings = $_POST['settings'] ?? [];

    if (empty($settings)) {
        wp_send_json_error('No settings provided');
    }

    // Save each setting to user meta
    $allowed_settings = [
        'profileVisibility',
        'showActivityStatus',
        'emailNotifications',
        'communityNotifications'
    ];

    foreach ($allowed_settings as $setting_key) {
        if (isset($settings[$setting_key])) {
            $value = $settings[$setting_key];
            // Convert boolean values
            if ($value === 'true' || $value === true) {
                $value = 1;
            } elseif ($value === 'false' || $value === false) {
                $value = 0;
            }
            update_user_meta($current_user_id, 'myavana_' . $setting_key, $value);
        }
    }

    wp_send_json_success('Settings saved successfully');
}
add_action('wp_ajax_save_profile_settings', 'myavana_save_profile_settings_handler');

/**
 * AJAX Handler: Export user data
 * Action: export_user_data
 */
function myavana_export_user_data_handler() {
    // Verify nonce
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'myavana_ajax_nonce')) {
        wp_die('Security check failed');
    }

    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        wp_die('You must be logged in');
    }

    global $wpdb;

    // Gather all user data
    $export_data = [];

    // User info
    $user = get_userdata($current_user_id);
    $export_data['user_info'] = [
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'registered' => $user->user_registered
    ];

    // Hair journey entries
    $entries_args = [
        'post_type' => 'hair_journey_entry',
        'author' => $current_user_id,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'post_date',
        'order' => 'DESC',
    ];
    $entries = get_posts($entries_args);
    $export_data['hair_journey_entries'] = [];

    foreach ($entries as $entry) {
        $export_data['hair_journey_entries'][] = [
            'title' => $entry->post_title,
            'content' => $entry->post_content,
            'date' => $entry->post_date,
            'health_rating' => get_post_meta($entry->ID, 'health_rating', true),
            'mood' => get_post_meta($entry->ID, 'mood_demeanor', true),
            'products' => get_post_meta($entry->ID, 'products_used', true)
        ];
    }

    // Community posts
    $posts_table = $wpdb->prefix . 'myavana_community_posts';
    $community_posts = $wpdb->get_results($wpdb->prepare(
        "SELECT content, image_url, visibility, created_at
         FROM $posts_table
         WHERE user_id = %d",
        $current_user_id
    ), ARRAY_A);
    $export_data['community_posts'] = $community_posts;

    // Goals
    $export_data['goals'] = get_user_meta($current_user_id, 'myavana_hair_goals_structured', true) ?: [];

    // Routine
    $export_data['routine'] = get_user_meta($current_user_id, 'myavana_current_routine', true) ?: [];

    // Hair profile
    $profile_table = $wpdb->prefix . 'myavana_profiles';
    $hair_profile = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $profile_table WHERE user_id = %d",
        $current_user_id
    ), ARRAY_A);
    $export_data['hair_profile'] = $hair_profile;

    // Create JSON file
    $json_data = json_encode($export_data, JSON_PRETTY_PRINT);
    $filename = 'myavana-data-export-' . $current_user_id . '-' . date('Y-m-d') . '.json';

    // Set headers for download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json_data));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    echo $json_data;
    exit;
}
add_action('wp_ajax_export_user_data', 'myavana_export_user_data_handler');
}
