<?php
/**
 * Community Entry Sharing Handlers
 * Allows users to select and share existing hair journey entries
 *
 * @package Myavana_Hair_Journey
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_get_shareable_entries')) {
/**
 * Get user's shareable entries
 */
function myavana_get_shareable_entries() {
    check_ajax_referer('myavana_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not authenticated']);
        return;
    }

    $user_id = get_current_user_id();
    global $wpdb;

    // Get user's entries
    $entries_table = $wpdb->prefix . 'myavana_hair_diary_entries';
    $shared_table = $wpdb->prefix . 'myavana_shared_entries';

    // Log for debugging
    error_log('Fetching entries for user: ' . $user_id);
    error_log('Table name: ' . $entries_table);

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$entries_table'");
    if (!$table_exists) {
        error_log('Table does not exist: ' . $entries_table);
        wp_send_json_error(['message' => 'Hair diary entries table not found. Please create an entry first.']);
        return;
    }

    // Get entries that haven't been shared yet
    $entries = $wpdb->get_results($wpdb->prepare("
        SELECT e.*,
               (SELECT community_post_id FROM {$shared_table} WHERE entry_id = e.id) as shared_post_id
        FROM {$entries_table} e
        WHERE e.user_id = %d
        ORDER BY e.entry_date DESC, e.created_at DESC
        LIMIT 100
    ", $user_id));

    // Log query error if any
    if ($wpdb->last_error) {
        error_log('SQL Error: ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
        return;
    }

    if (!$entries) {
        error_log('No entries found for user: ' . $user_id);
        wp_send_json_success(['entries' => []]);
        return;
    }

    error_log('Found ' . count($entries) . ' entries for user: ' . $user_id);

    // Format entries for display
    $formatted_entries = [];
    foreach ($entries as $entry) {
        // Parse photos if stored as JSON
        $photos = [];
        if (!empty($entry->photo_url)) {
            if (is_string($entry->photo_url) && (strpos($entry->photo_url, '[') === 0 || strpos($entry->photo_url, '{') === 0)) {
                $photos = json_decode($entry->photo_url, true);
                if (!is_array($photos)) {
                    $photos = [$entry->photo_url];
                }
            } else {
                $photos = [$entry->photo_url];
            }
        }

        // Get first photo for thumbnail
        $thumbnail = !empty($photos) ? $photos[0] : '';

        $formatted_entries[] = [
            'id' => $entry->id,
            'title' => $entry->title ?? '',
            'notes' => $entry->notes ?? '',
            'entry_date' => $entry->entry_date ?? $entry->created_at,
            'photo_url' => $thumbnail,
            'all_photos' => $photos,
            'health_rating' => $entry->health_rating ?? null,
            'mood' => $entry->mood ?? null,
            'is_shared' => !empty($entry->shared_post_id),
            'shared_post_id' => $entry->shared_post_id ?? null,
            'has_photos' => !empty($photos),
            'photo_count' => count($photos),
            'formatted_date' => date('M j, Y', strtotime($entry->entry_date ?? $entry->created_at))
        ];
    }

    wp_send_json_success(['entries' => $formatted_entries]);
}
add_action('wp_ajax_myavana_get_shareable_entries', 'myavana_get_shareable_entries');

/**
 * Share selected entry to community
 */
function myavana_share_selected_entry() {
    check_ajax_referer('myavana_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not authenticated']);
        return;
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $privacy = isset($_POST['privacy']) ? sanitize_text_field($_POST['privacy']) : 'public';
    $custom_title = isset($_POST['custom_title']) ? sanitize_text_field($_POST['custom_title']) : '';
    $custom_content = isset($_POST['custom_content']) ? sanitize_textarea_field($_POST['custom_content']) : '';

    if (!$entry_id) {
        wp_send_json_error(['message' => 'Invalid entry ID']);
        return;
    }

    // Check if entry belongs to user
    global $wpdb;
    $entries_table = $wpdb->prefix . 'myavana_hair_diary_entries';
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$entries_table} WHERE id = %d AND user_id = %d",
        $entry_id, get_current_user_id()
    ));

    if (!$entry) {
        wp_send_json_error(['message' => 'Entry not found or access denied']);
        return;
    }

    // Check if already shared
    if (!Myavana_Community_Integration::is_entry_shareable($entry_id)) {
        wp_send_json_error(['message' => 'This entry has already been shared to the community']);
        return;
    }

    // Override entry data with custom content if provided
    if (!empty($custom_title)) {
        $entry->title = $custom_title;
    }
    if (!empty($custom_content)) {
        $entry->notes = $custom_content;
    }

    // Share the entry
    $post_id = Myavana_Community_Integration::share_entry($entry_id, $privacy);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => $post_id->get_error_message()]);
        return;
    }

    // Get the created post
    $posts_table = $wpdb->prefix . 'myavana_community_posts';
    $post = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, u.display_name FROM {$posts_table} p
         LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
         WHERE p.id = %d",
        $post_id
    ));

    wp_send_json_success([
        'message' => 'Entry shared successfully!',
        'post_id' => $post_id,
        'post' => $post,
        'points_earned' => 15
    ]);
}
add_action('wp_ajax_myavana_share_selected_entry', 'myavana_share_selected_entry');

/**
 * Bulk share multiple entries
 */
function myavana_bulk_share_entries() {
    check_ajax_referer('myavana_nonce', 'nonce');

    error_log('=== BULK SHARE ENTRIES STARTED ===');

    if (!is_user_logged_in()) {
        error_log('ERROR: User not authenticated');
        wp_send_json_error(['message' => 'User not authenticated']);
        return;
    }

    $user_id = get_current_user_id();
    error_log('User ID: ' . $user_id);

    // Capture the bulk_privacy value from the form
    $entry_ids = isset($_POST['entry_ids']) ? $_POST['entry_ids'] : [];
    $privacy = isset($_POST['privacy']) ? sanitize_text_field($_POST['privacy']) : 'public';

    error_log('Entry IDs received: ' . print_r($entry_ids, true));
    error_log('Privacy level: ' . $privacy);

    if (empty($entry_ids) || !is_array($entry_ids)) {
        error_log('ERROR: No entries selected or not an array');
        wp_send_json_error(['message' => 'No entries selected']);
        return;
    }

    $shared = [];
    $failed = [];
    $already_shared = [];
    $debug_info = [];

    foreach ($entry_ids as $entry_id) {
        $entry_id = intval($entry_id);
        error_log('--- Processing entry ID: ' . $entry_id);

        $is_shareable = Myavana_Community_Integration::is_entry_shareable($entry_id);
        error_log('Is shareable: ' . ($is_shareable ? 'YES' : 'NO'));

        if (!$is_shareable) {
            $already_shared[] = $entry_id;
            error_log('Entry ' . $entry_id . ' already shared - skipping');
            continue;
        }

        error_log('Calling share_entry for entry ' . $entry_id);
        $post_id = Myavana_Community_Integration::share_entry($entry_id, $privacy);

        if (is_wp_error($post_id)) {
            error_log('ERROR: share_entry returned WP_Error: ' . $post_id->get_error_message());
            $failed[] = ['entry_id' => $entry_id, 'error' => $post_id->get_error_message()];
        } else {
            error_log('SUCCESS: Created community post ID: ' . $post_id);
            $shared[] = ['entry_id' => $entry_id, 'post_id' => $post_id];
        }
    }

    error_log('SUMMARY - Shared: ' . count($shared) . ', Already shared: ' . count($already_shared) . ', Failed: ' . count($failed));

    // Build success message
    $message = '';
    if (count($shared) > 0) {
        $message = count($shared) . ' ' . (count($shared) === 1 ? 'entry' : 'entries') . ' shared to community!';
    }
    if (count($already_shared) > 0) {
        $message .= (strlen($message) > 0 ? ' ' : '') . count($already_shared) . ' ' . (count($already_shared) === 1 ? 'entry was' : 'entries were') . ' already shared.';
    }
    if (count($failed) > 0) {
        $message .= (strlen($message) > 0 ? ' ' : '') . count($failed) . ' ' . (count($failed) === 1 ? 'entry' : 'entries') . ' failed to share.';
    }

    wp_send_json_success([
        'message' => $message ?: 'No new entries were shared.',
        'shared' => $shared,
        'already_shared' => $already_shared,
        'failed' => $failed
    ]);
}
add_action('wp_ajax_myavana_bulk_share_entries', 'myavana_bulk_share_entries');

/**
 * Get entry details for preview
 */
function myavana_get_entry_preview() {
    check_ajax_referer('myavana_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'User not authenticated']);
        return;
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;

    if (!$entry_id) {
        wp_send_json_error(['message' => 'Invalid entry ID']);
        return;
    }

    global $wpdb;
    $entries_table = $wpdb->prefix . 'myavana_hair_diary_entries';

    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$entries_table} WHERE id = %d AND user_id = %d",
        $entry_id, get_current_user_id()
    ));

    if (!$entry) {
        wp_send_json_error(['message' => 'Entry not found']);
        return;
    }

    // Parse photos
    $photos = [];
    if (!empty($entry->photo_url)) {
        if (is_string($entry->photo_url) && (strpos($entry->photo_url, '[') === 0 || strpos($entry->photo_url, '{') === 0)) {
            $photos = json_decode($entry->photo_url, true);
            if (!is_array($photos)) {
                $photos = [$entry->photo_url];
            }
        } else {
            $photos = [$entry->photo_url];
        }
    }

    // Extract hashtags from notes
    $hashtags = Myavana_Community_Integration::extract_hashtags($entry->notes ?? '');

    // Generate suggested title if empty
    $suggested_title = $entry->title;
    if (empty($suggested_title)) {
        $date_diff = floor((time() - strtotime($entry->entry_date ?? $entry->created_at)) / (60 * 60 * 24));
        if ($date_diff < 30) {
            $suggested_title = "My Hair Journey Update";
        } elseif ($date_diff < 90) {
            $suggested_title = "1 Month Progress Update";
        } elseif ($date_diff < 180) {
            $suggested_title = "3 Months of Growth!";
        } else {
            $suggested_title = "My Hair Transformation Journey";
        }
    }

    wp_send_json_success([
        'entry' => [
            'id' => $entry->id,
            'title' => $entry->title ?? '',
            'suggested_title' => $suggested_title,
            'notes' => $entry->notes ?? '',
            'photos' => $photos,
            'health_rating' => $entry->health_rating ?? null,
            'mood' => $entry->mood ?? null,
            'entry_date' => $entry->entry_date ?? $entry->created_at,
            'formatted_date' => date('F j, Y', strtotime($entry->entry_date ?? $entry->created_at)),
            'hashtags' => $hashtags,
            'ai_analysis' => $entry->ai_analysis ?? null,
            'ai_recommendations' => $entry->ai_recommendations ?? null
        ]
    ]);
}
add_action('wp_ajax_myavana_get_entry_preview', 'myavana_get_entry_preview');
}
