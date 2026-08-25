<?php
/**
 * Social Features and Community Integration
 * 
 * This class provides social networking features for the hair journey community:
 * - User profiles and following system
 * - Community posts and sharing
 * - Comments and reactions
 * - Hair journey sharing and inspiration
 * - Community challenges and groups
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Myavana_Social_Features')) {
class Myavana_Social_Features {
    
    private $user_id;
    
    public function __construct() {
        $this->user_id = get_current_user_id();
        $this->init();
    }
    
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_get_community_feed', array($this, 'get_community_feed'));
        add_action('wp_ajax_nopriv_get_community_feed', array($this, 'get_community_feed'));
        add_action('wp_ajax_create_community_post', array($this, 'create_community_post'));
        add_action('wp_ajax_like_post', array($this, 'like_post'));
        add_action('wp_ajax_comment_on_post', array($this, 'comment_on_post'));
        add_action('wp_ajax_follow_user', array($this, 'follow_user'));
        add_action('wp_ajax_join_challenge', array($this, 'join_challenge'));
        add_action('wp_ajax_get_user_followers', array($this, 'get_user_followers'));
        add_action('wp_ajax_get_trending_posts', array($this, 'get_trending_posts'));
        add_action('wp_ajax_get_post_comments', array($this, 'get_post_comments'));
        add_action('wp_ajax_bookmark_post', array($this, 'bookmark_post'));
        add_action('wp_ajax_track_post_share', array($this, 'track_post_share'));
        add_action('wp_ajax_get_user_profile', array($this, 'get_user_profile'));
        add_action('wp_ajax_nopriv_get_user_profile', array($this, 'get_user_profile'));
        add_action('wp_ajax_myavana_ci_discovery', array($this, 'get_discovery_data'));
        add_action('wp_ajax_nopriv_myavana_ci_discovery', array($this, 'get_discovery_data'));
        
        // Database setup
        add_action('init', array($this, 'create_social_tables'));
    }

    /**
     * Always resolve current user ID at request time (avoids stale constructor state).
     */
    private function sync_user_id() {
        $this->user_id = get_current_user_id();
        return (int) $this->user_id;
    }
    
    /**
     * Create necessary database tables for social features
     */
    public function create_social_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Community posts table
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $posts_sql = "CREATE TABLE $posts_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            image_url varchar(500),
            video_url varchar(500) DEFAULT NULL,
            media_type varchar(30) DEFAULT 'text',
            post_type varchar(50) DEFAULT 'general',
            privacy_level varchar(20) DEFAULT 'public',
            likes_count int(11) DEFAULT 0,
            comments_count int(11) DEFAULT 0,
            shares_count int(11) DEFAULT 0,
            views_count int(11) DEFAULT 0,
            is_pinned tinyint(1) DEFAULT 0,
            is_featured tinyint(1) DEFAULT 0,
            source_entry_id bigint(20) DEFAULT NULL,
            ai_metadata longtext DEFAULT NULL,
            hashtags varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_type (post_type),
            KEY created_at (created_at),
            KEY source_entry_id (source_entry_id),
            KEY media_type (media_type),
            KEY is_pinned (is_pinned)
        ) $charset_collate;";
        
        // Post likes table
        $likes_table = $wpdb->prefix . 'myavana_post_likes';
        $likes_sql = "CREATE TABLE $likes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_user (post_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Post comments table
        $comments_table = $wpdb->prefix . 'myavana_post_comments';
        $comments_sql = "CREATE TABLE $comments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            parent_id mediumint(9) DEFAULT 0,
            content text NOT NULL,
            likes_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        // User followers table
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        $followers_sql = "CREATE TABLE $followers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            follower_id bigint(20) NOT NULL,
            following_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY follow_relationship (follower_id, following_id),
            KEY follower_id (follower_id),
            KEY following_id (following_id)
        ) $charset_collate;";
        
        // Community challenges table
        $challenges_table = $wpdb->prefix . 'myavana_community_challenges';
        $challenges_sql = "CREATE TABLE $challenges_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            challenge_type varchar(50) NOT NULL,
            start_date datetime NOT NULL,
            end_date datetime NOT NULL,
            participants_count int(11) DEFAULT 0,
            prize_description text,
            rules longtext,
            hashtag varchar(100),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY challenge_type (challenge_type),
            KEY start_date (start_date),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Challenge participants table
        $challenge_participants_table = $wpdb->prefix . 'myavana_challenge_participants';
        $challenge_participants_sql = "CREATE TABLE $challenge_participants_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            challenge_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            progress_data longtext,
            completion_status varchar(20) DEFAULT 'active',
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY challenge_user (challenge_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Shared entries tracking table
        $shared_entries_table = $wpdb->prefix . 'myavana_shared_entries';
        $shared_entries_sql = "CREATE TABLE $shared_entries_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            community_post_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            shared_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_id (entry_id),
            KEY user_id (user_id),
            KEY community_post_id (community_post_id)
        ) $charset_collate;";

        // Routine library table
        $routines_table = $wpdb->prefix . 'myavana_shared_routines';
        $routines_sql = "CREATE TABLE $routines_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description longtext NOT NULL,
            routine_data longtext NOT NULL,
            hair_type varchar(50),
            goal_type varchar(100),
            products_used text,
            frequency varchar(50),
            effectiveness_score decimal(3,2) DEFAULT 0.00,
            times_tried int(11) DEFAULT 0,
            likes_count int(11) DEFAULT 0,
            privacy_level varchar(20) DEFAULT 'public',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY hair_type (hair_type),
            KEY goal_type (goal_type),
            KEY effectiveness_score (effectiveness_score)
        ) $charset_collate;";

        // Routine bookmarks table
        $routine_bookmarks_table = $wpdb->prefix . 'myavana_routine_bookmarks';
        $routine_bookmarks_sql = "CREATE TABLE $routine_bookmarks_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            routine_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            tried tinyint(1) DEFAULT 0,
            effectiveness_rating int(11) DEFAULT NULL,
            notes text,
            bookmarked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY routine_user (routine_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Post bookmarks table
        $post_bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';
        $post_bookmarks_sql = "CREATE TABLE $post_bookmarks_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            collection varchar(100) DEFAULT 'saved',
            notes text,
            bookmarked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_user (post_id, user_id),
            KEY user_id (user_id),
            KEY collection (collection)
        ) $charset_collate;";

        // Notifications table
        $notifications_table = $wpdb->prefix . 'myavana_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            action_url varchar(500),
            related_user_id bigint(20) DEFAULT NULL,
            related_post_id mediumint(9) DEFAULT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($posts_sql);
        dbDelta($likes_sql);
        dbDelta($comments_sql);
        dbDelta($followers_sql);
        dbDelta($challenges_sql);
        dbDelta($challenge_participants_sql);
        dbDelta($shared_entries_sql);
        dbDelta($routines_sql);
        dbDelta($routine_bookmarks_sql);
        dbDelta($post_bookmarks_sql);
        dbDelta($notifications_sql);
    }
    
    /**
     * Get community feed for the user
     */
    public function get_community_feed() {
        $this->sync_user_id();
        error_log('=== GET COMMUNITY FEED CALLED ===');

        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            error_log('ERROR: Nonce verification failed');
            wp_die('Security check failed');
        }

        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = min(20, max(1, intval($_POST['per_page'] ?? 10)));
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
        $hashtag = sanitize_text_field(wp_unslash($_POST['hashtag'] ?? ''));
        $media_filter = sanitize_text_field(wp_unslash($_POST['media_filter'] ?? ''));
        $mode = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'discover'));
        $circle = sanitize_text_field(wp_unslash($_POST['circle'] ?? ''));
        $hashtag = ltrim(strtolower($hashtag), '#');
        $mode = in_array($mode, ['discover', 'following'], true) ? $mode : 'discover';
        $circle = strtolower(trim($circle));

        error_log('Feed params - Page: ' . $page . ', Per page: ' . $per_page . ', Filter: ' . $filter . ', Mode: ' . $mode . ', Circle: ' . $circle . ', User ID: ' . $this->user_id . ', Search: ' . $search . ', Hashtag: ' . $hashtag . ', Media: ' . $media_filter);

        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $users_table = $wpdb->users;
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        
        $offset = ($page - 1) * $per_page;

        // Show: (1) public posts, (2) user's own posts, (3) followers-only posts from people user follows
        $privacy_clause = "(p.privacy_level = 'public' OR p.user_id = {$this->user_id} OR (p.privacy_level = 'followers' AND p.user_id IN (SELECT following_id FROM $followers_table WHERE follower_id = {$this->user_id})))";

        $where_conditions = [$privacy_clause];
        $query_args = [];

        switch ($filter) {
            case 'following':
                $where_conditions[] = "p.user_id IN (SELECT following_id FROM $followers_table WHERE follower_id = %d)";
                $query_args[] = $this->user_id;
                break;
            case 'trending':
                $where_conditions[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'featured':
                $where_conditions[] = "p.is_featured = 1";
                break;
            case 'media_image':
                $where_conditions[] = "(p.image_url IS NOT NULL AND p.image_url <> '')";
                break;
            case 'media_video':
                $where_conditions[] = "((p.video_url IS NOT NULL AND p.video_url <> '') OR p.post_type = 'video' OR p.media_type = 'video')";
                break;
            case 'media_text':
                $where_conditions[] = "(COALESCE(p.image_url, '') = '' AND COALESCE(p.video_url, '') = '')";
                break;
            default:
                break;
        }

        if ($mode === 'following' && $filter !== 'following') {
            $where_conditions[] = "p.user_id IN (SELECT following_id FROM $followers_table WHERE follower_id = %d)";
            $query_args[] = $this->user_id;
        }

        if ($media_filter === 'image') {
            $where_conditions[] = "(p.image_url IS NOT NULL AND p.image_url <> '')";
        } elseif ($media_filter === 'video') {
            $where_conditions[] = "((p.video_url IS NOT NULL AND p.video_url <> '') OR p.post_type = 'video' OR p.media_type = 'video')";
        } elseif ($media_filter === 'text') {
            $where_conditions[] = "(COALESCE(p.image_url, '') = '' AND COALESCE(p.video_url, '') = '')";
        }

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(p.title LIKE %s OR p.content LIKE %s OR p.hashtags LIKE %s)";
            $query_args[] = $search_like;
            $query_args[] = $search_like;
            $query_args[] = $search_like;
        }

        if (!empty($hashtag)) {
            $hashtag_like = '%' . $wpdb->esc_like($hashtag) . '%';
            $where_conditions[] = "(FIND_IN_SET(%s, REPLACE(COALESCE(p.hashtags, ''), ' ', '')) > 0 OR p.content LIKE %s)";
            $query_args[] = $hashtag;
            $query_args[] = '%#' . $wpdb->esc_like($hashtag) . '%';
            // Backup catch-all if content style varies
            $where_conditions[] = "(p.hashtags LIKE %s)";
            $query_args[] = $hashtag_like;
        }

        if (!empty($circle)) {
            $circle_keyword_map = [
                'type-4c' => ['4c', 'type 4c', 'type4c'],
                'transitioning' => ['transition', 'transitioning', 'big chop'],
                'length-retention' => ['length', 'retention', 'growth'],
                'protective-style' => ['protective style', 'braids', 'twists', 'wig'],
            ];

            if (isset($circle_keyword_map[$circle])) {
                $circle_clauses = [];
                foreach ($circle_keyword_map[$circle] as $keyword) {
                    $like = '%' . $wpdb->esc_like($keyword) . '%';
                    $circle_clauses[] = "(p.title LIKE %s OR p.content LIKE %s OR p.hashtags LIKE %s)";
                    $query_args[] = $like;
                    $query_args[] = $like;
                    $query_args[] = $like;
                }

                if (!empty($circle_clauses)) {
                    $where_conditions[] = '(' . implode(' OR ', $circle_clauses) . ')';
                }
            }
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        $relevance_expression = '0';
        if ($mode === 'discover' && $this->user_id > 0) {
            $relevance_parts = [];

            $profiles_table = $wpdb->prefix . 'myavana_profiles';
            $current_hair_type = $wpdb->get_var($wpdb->prepare(
                "SELECT hair_type FROM {$profiles_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $this->user_id
            ));
            if (empty($current_hair_type)) {
                $current_hair_type = get_user_meta($this->user_id, 'hair_type', true);
            }

            if (!empty($current_hair_type)) {
                $hair_like = esc_sql('%' . $wpdb->esc_like(strtolower((string) $current_hair_type)) . '%');
                $relevance_parts[] = "(CASE WHEN LOWER(p.content) LIKE '{$hair_like}' OR LOWER(COALESCE(p.hashtags, '')) LIKE '{$hair_like}' THEN 18 ELSE 0 END)";
            }

            $user_goals = get_user_meta($this->user_id, 'myavana_hair_goals_structured', true);
            if (is_array($user_goals) && !empty($user_goals)) {
                $goal_keywords = [];
                foreach ($user_goals as $goal_item) {
                    if (!is_array($goal_item)) {
                        continue;
                    }
                    $goal_title = strtolower(trim((string) ($goal_item['title'] ?? '')));
                    if ($goal_title !== '') {
                        $goal_keywords = array_merge($goal_keywords, preg_split('/\s+/', $goal_title));
                    }
                }
                $goal_keywords = array_values(array_unique(array_filter($goal_keywords, function($word) {
                    return strlen($word) > 3;
                })));

                foreach (array_slice($goal_keywords, 0, 3) as $keyword) {
                    $goal_like = esc_sql('%' . $wpdb->esc_like($keyword) . '%');
                    $relevance_parts[] = "(CASE WHEN LOWER(p.title) LIKE '{$goal_like}' OR LOWER(p.content) LIKE '{$goal_like}' OR LOWER(COALESCE(p.hashtags, '')) LIKE '{$goal_like}' THEN 7 ELSE 0 END)";
                }
            }

            if (!empty($relevance_parts)) {
                $relevance_expression = implode(' + ', $relevance_parts);
            }
        }

        $order_by_clause = 'p.is_pinned DESC, p.created_at DESC';
        if ($mode === 'discover') {
            $order_by_clause = 'p.is_pinned DESC, relevance_score DESC, (COALESCE(p.likes_count, 0) + COALESCE(p.comments_count, 0) + COALESCE(p.shares_count, 0)) DESC, p.created_at DESC';
        }

        $sql = "
            SELECT p.*, u.display_name, u.user_email,
                   ({$relevance_expression}) as relevance_score,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}myavana_post_likes WHERE post_id = p.id) as likes_count,
                   (SELECT COUNT(*) FROM {$wpdb->prefix}myavana_post_comments WHERE post_id = p.id) as comments_count
            FROM $posts_table p
            LEFT JOIN $users_table u ON p.user_id = u.ID
            $where_clause
            ORDER BY {$order_by_clause}
            LIMIT %d OFFSET %d
        ";

        error_log('Executing feed query with WHERE: ' . $where_clause);

        $query_args[] = $per_page;
        $query_args[] = $offset;
        $posts = $wpdb->get_results($wpdb->prepare($sql, ...$query_args));

        if ($wpdb->last_error) {
            error_log('ERROR: Feed query failed - ' . $wpdb->last_error);
            error_log('Last query: ' . $wpdb->last_query);
        }

        error_log('Feed returned ' . count($posts) . ' posts');
        if (count($posts) > 0) {
            error_log('First post ID: ' . $posts[0]->id . ', Title: ' . $posts[0]->title);
        }

        // Enhance posts with additional data
        $entry_count_cache = [];
        foreach ($posts as &$post) {
            $post->user_avatar = get_avatar_url($post->user_id);
            $post->user_profile_url = '#'; // Could be customized
            $post->is_liked = $this->is_post_liked($post->id, $this->user_id);
            $post->is_bookmarked = $this->is_post_bookmarked($post->id, $this->user_id);
            $post->recent_comments = $this->get_recent_comments($post->id, 3);
            $post->formatted_date = human_time_diff(strtotime($post->created_at)) . ' ago';

            // Add reaction data
            $reactions_data = $this->get_post_reactions($post->id, $this->user_id);
            $post->reactions = $reactions_data['reactions'];
            $post->user_reaction = $reactions_data['user_reaction'];
            $post->media_type = !empty($post->video_url) ? 'video' : (!empty($post->image_url) ? 'image' : 'text');
            $post->engagement_score = intval($post->likes_count) + intval($post->comments_count) + intval($post->shares_count);

            $post_user_id = (int) $post->user_id;
            if (!isset($entry_count_cache[$post_user_id])) {
                $entry_count_cache[$post_user_id] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(ID)
                     FROM {$wpdb->posts}
                     WHERE post_type = 'hair_journey_entry'
                       AND post_status = 'publish'
                       AND post_author = %d",
                    $post_user_id
                ));
            }
            $post->is_verified_journey = $entry_count_cache[$post_user_id] >= 30;
        }

        wp_send_json_success($posts);
    }
    
    /**
     * Create a new community post
     */
    public function create_community_post() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in to create a post');
        }
        
        $title_raw = (string) wp_unslash($_POST['title'] ?? '');
        $content_raw = (string) wp_unslash($_POST['content'] ?? '');

        // Normalize escaped HTML entities (e.g. \&#039;) and decode to plain text.
        $title_raw = preg_replace('/\\\\+&#0*39;|\\\\+&#x0*27;|\\\\+&apos;/i', "'", $title_raw);
        $content_raw = preg_replace('/\\\\+&#0*39;|\\\\+&#x0*27;|\\\\+&apos;/i', "'", $content_raw);
        $title_raw = html_entity_decode($title_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content_raw = html_entity_decode($content_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $title = sanitize_text_field($title_raw);
        $content = sanitize_textarea_field($content_raw);
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'general');
        $privacy_level = sanitize_text_field($_POST['privacy_level'] ?? 'public');
        $source_entry_id = absint($_POST['source_entry_id'] ?? 0);
        $video_url = esc_url_raw(wp_unslash($_POST['video_url'] ?? ''));
        $media_type = 'text';
        $raw_hashtags = sanitize_text_field($_POST['hashtags'] ?? '');
        $hashtags = '';
        if ($raw_hashtags !== '') {
            $hashtag_parts = preg_split('/[\s,]+/', strtolower($raw_hashtags));
            $clean_hashtags = [];
            foreach ($hashtag_parts as $tag) {
                $tag = preg_replace('/^#+/', '', trim((string) $tag));
                $tag = preg_replace('/[^a-z0-9_]/', '', $tag);
                if (strlen($tag) > 1) {
                    $clean_hashtags[] = $tag;
                }
            }
            $hashtags = implode(',', array_slice(array_values(array_unique($clean_hashtags)), 0, 12));
        }

        $ai_metadata = '';
        if (!empty($_POST['ai_metadata'])) {
            $ai_metadata_raw = wp_unslash($_POST['ai_metadata']);
            $ai_metadata_decoded = json_decode($ai_metadata_raw, true);
            if (is_array($ai_metadata_decoded)) {
                $ai_metadata = wp_json_encode($ai_metadata_decoded);
            }
        }
        
        // Handle image upload
        $image_url = '';
        if (!empty($_FILES['image'])) {
            $upload_result = $this->handle_image_upload($_FILES['image']);
            if ($upload_result['success']) {
                $image_url = $upload_result['url'];
                $media_type = 'image';
            }
        }

        // Handle optional video upload
        if (!empty($_FILES['video'])) {
            $video_upload_result = $this->handle_video_upload($_FILES['video']);
            if ($video_upload_result['success']) {
                $video_url = $video_upload_result['url'];
                $media_type = 'video';
            }
        }

        // If linked to an existing journey entry, hydrate defaults from that entry
        if ($source_entry_id > 0) {
            $entry_post = get_post($source_entry_id);
            if (!$entry_post || $entry_post->post_type !== 'hair_journey_entry' || (int) $entry_post->post_author !== (int) $this->user_id) {
                $source_entry_id = 0;
            } else {
                if ($title === '') {
                    $title = sanitize_text_field(get_the_title($source_entry_id));
                }
                if ($content === '') {
                    $content = sanitize_textarea_field(wp_strip_all_tags((string) $entry_post->post_content));
                }
                if ($image_url === '') {
                    $thumbnail = get_the_post_thumbnail_url($source_entry_id, 'large');
                    if (!empty($thumbnail)) {
                        $image_url = esc_url_raw($thumbnail);
                    }
                }
                if ($image_url === '') {
                    $gallery_images = get_post_meta($source_entry_id, '_entry_gallery', true);
                    if (is_array($gallery_images) && !empty($gallery_images)) {
                        $first_attachment = (int) reset($gallery_images);
                        if ($first_attachment > 0) {
                            $gallery_image_url = wp_get_attachment_image_url($first_attachment, 'large');
                            if (!empty($gallery_image_url)) {
                                $image_url = esc_url_raw($gallery_image_url);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($video_url)) {
            $media_type = 'video';
        } elseif (!empty($image_url)) {
            $media_type = 'image';
        }

        if ($title === '' && $content === '' && empty($image_url) && empty($video_url)) {
            wp_send_json_error('Add text, photo, or video before posting.');
            return;
        }

        if ($title === '') {
            if (!empty($video_url) || $post_type === 'video') {
                $title = 'Video Hair Update';
            } elseif (!empty($image_url)) {
                $title = 'Hair Journey Photo Update';
            } elseif ($content !== '') {
                $title = wp_trim_words($content, 8, '...');
            } else {
                $title = 'Hair Journey Update';
            }
        }

        if ($post_type === 'video' && empty($video_url)) {
            wp_send_json_error('Video post selected but no video was provided.');
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'myavana_community_posts';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $this->user_id,
                'title' => $title,
                'content' => $content,
                'image_url' => $image_url,
                'video_url' => $video_url,
                'media_type' => $media_type,
                'post_type' => $post_type,
                'privacy_level' => $privacy_level,
                'source_entry_id' => $source_entry_id > 0 ? $source_entry_id : null,
                'ai_metadata' => $ai_metadata,
                'hashtags' => $hashtags,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            $post_id = $wpdb->insert_id;

            // Award points for creating post
            Myavana_Community_Integration::award_community_points($this->user_id, 'create_post');

            // Get the created post data
            $post = $wpdb->get_row($wpdb->prepare(
                "SELECT p.*, u.display_name FROM $table_name p
                 LEFT JOIN {$wpdb->users} u ON p.user_id = u.ID
                 WHERE p.id = %d",
                $post_id
            ));

            wp_send_json_success(array(
                'message' => 'Post created successfully',
                'post' => $post
            ));
        } else {
            wp_send_json_error('Failed to create post');
        }
    }
    
    /**
     * Like/unlike a post
     */
    public function like_post() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        
        global $wpdb;
        
        $likes_table = $wpdb->prefix . 'myavana_post_likes';
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        
        // Check if already liked
        $existing_like = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $likes_table WHERE post_id = %d AND user_id = %d",
            $post_id, $this->user_id
        ));
        
        if ($existing_like) {
            // Unlike the post
            $wpdb->delete(
                $likes_table,
                array('post_id' => $post_id, 'user_id' => $this->user_id),
                array('%d', '%d')
            );
            
            // Decrease likes count
            $wpdb->query($wpdb->prepare(
                "UPDATE $posts_table SET likes_count = likes_count - 1 WHERE id = %d",
                $post_id
            ));
            
            $action = 'unliked';
        } else {
            // Like the post
            $wpdb->insert(
                $likes_table,
                array(
                    'post_id' => $post_id,
                    'user_id' => $this->user_id,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
            
            // Increase likes count
            $wpdb->query($wpdb->prepare(
                "UPDATE $posts_table SET likes_count = likes_count + 1 WHERE id = %d",
                $post_id
            ));

            $action = 'liked';

            // Get post owner
            $post_owner = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM $posts_table WHERE id = %d",
                $post_id
            ));

            // Award points to post owner for first like
            $like_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $likes_table WHERE post_id = %d",
                $post_id
            ));

            if ($like_count == 1 && $post_owner) {
                Myavana_Community_Integration::award_community_points($post_owner, 'first_like');
            }

            // Create notification for post owner
            if ($post_owner && $post_owner != $this->user_id) {
                Myavana_Community_Integration::create_notification($post_owner, 'new_like', [
                    'user' => wp_get_current_user()->display_name,
                    'related_user_id' => $this->user_id,
                    'related_post_id' => $post_id,
                    'action_url' => '#post-' . $post_id
                ]);
            }
        }

        // Get updated likes count
        $likes_count = $wpdb->get_var($wpdb->prepare(
            "SELECT likes_count FROM $posts_table WHERE id = %d",
            $post_id
        ));

        wp_send_json_success(array(
            'action' => $action,
            'likes_count' => $likes_count
        ));
    }
    
    /**
     * Comment on a post
     */
    public function comment_on_post() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $content = sanitize_textarea_field($_POST['content']);
        $parent_id = intval($_POST['parent_id'] ?? 0);
        
        global $wpdb;
        
        $comments_table = $wpdb->prefix . 'myavana_post_comments';
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        
        $result = $wpdb->insert(
            $comments_table,
            array(
                'post_id' => $post_id,
                'user_id' => $this->user_id,
                'parent_id' => $parent_id,
                'content' => $content,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            // Update comments count
            $wpdb->query($wpdb->prepare(
                "UPDATE $posts_table SET comments_count = comments_count + 1 WHERE id = %d",
                $post_id
            ));

            $comment_id = $wpdb->insert_id;

            // Get post owner
            $post_owner = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM $posts_table WHERE id = %d",
                $post_id
            ));

            // Award points for helping someone (commenting)
            Myavana_Community_Integration::award_community_points($this->user_id, 'help_someone');

            // Award points to post owner for first comment
            $comment_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $comments_table WHERE post_id = %d",
                $post_id
            ));

            if ($comment_count == 1 && $post_owner) {
                Myavana_Community_Integration::award_community_points($post_owner, 'first_comment');
            }

            // Create notification for post owner
            if ($post_owner && $post_owner != $this->user_id) {
                Myavana_Community_Integration::create_notification($post_owner, 'new_comment', [
                    'user' => wp_get_current_user()->display_name,
                    'related_user_id' => $this->user_id,
                    'related_post_id' => $post_id,
                    'action_url' => '#post-' . $post_id
                ]);
            }

            // Get the created comment with user data
            $comment = $wpdb->get_row($wpdb->prepare(
                "SELECT c.*, u.display_name FROM $comments_table c
                 LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
                 WHERE c.id = %d",
                $comment_id
            ));

            $comment->user_avatar = get_avatar_url($comment->user_id);
            $comment->formatted_date = human_time_diff(strtotime($comment->created_at)) . ' ago';

            wp_send_json_success(array(
                'message' => 'Comment added successfully',
                'comment' => $comment
            ));
        } else {
            wp_send_json_error('Failed to add comment');
        }
    }
    
    /**
     * Follow/unfollow a user
     */
    public function follow_user() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in');
            return;
        }
        
        $user_to_follow = intval($_POST['user_id']);
        
        if ($user_to_follow === $this->user_id) {
            wp_send_json_error('Cannot follow yourself');
            return;
        }
        
        global $wpdb;
        
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        
        // Check if already following
        $existing_follow = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $followers_table WHERE follower_id = %d AND following_id = %d",
            $this->user_id, $user_to_follow
        ));
        
        if ($existing_follow) {
            // Unfollow
            $wpdb->delete(
                $followers_table,
                array('follower_id' => $this->user_id, 'following_id' => $user_to_follow),
                array('%d', '%d')
            );
            
            $action = 'unfollowed';
        } else {
            // Follow
            $wpdb->insert(
                $followers_table,
                array(
                    'follower_id' => $this->user_id,
                    'following_id' => $user_to_follow,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
            
            $action = 'followed';
        }
        
        // Get updated follower counts
        $follower_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE following_id = %d",
            $user_to_follow
        ));
        
        $following_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE follower_id = %d",
            $user_to_follow
        ));
        
        wp_send_json_success(array(
            'action' => $action,
            'follower_count' => $follower_count,
            'following_count' => $following_count
        ));
    }
    
    /**
     * Join a community challenge
     */
    public function join_challenge() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in');
            return;
        }
        
        $challenge_id = intval($_POST['challenge_id']);
        
        global $wpdb;
        
        $participants_table = $wpdb->prefix . 'myavana_challenge_participants';
        $challenges_table = $wpdb->prefix . 'myavana_community_challenges';
        
        // Check if already joined
        $existing_participation = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $participants_table WHERE challenge_id = %d AND user_id = %d",
            $challenge_id, $this->user_id
        ));
        
        if ($existing_participation) {
            wp_send_json_error('Already joined this challenge');
            return;
        }
        
        // Join the challenge
        $result = $wpdb->insert(
            $participants_table,
            array(
                'challenge_id' => $challenge_id,
                'user_id' => $this->user_id,
                'completion_status' => 'active',
                'joined_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            // Update participants count
            $wpdb->query($wpdb->prepare(
                "UPDATE $challenges_table SET participants_count = participants_count + 1 WHERE id = %d",
                $challenge_id
            ));

            // Scaffold challenge into user's journey goals (community -> journey loop)
            $challenge = $wpdb->get_row($wpdb->prepare(
                "SELECT id, title, description, end_date
                 FROM $challenges_table
                 WHERE id = %d
                 LIMIT 1",
                $challenge_id
            ));
            $this->create_challenge_goal_scaffold($challenge);
            
            wp_send_json_success('Successfully joined the challenge!');
        } else {
            wp_send_json_error('Failed to join challenge');
        }
    }
    
    /**
     * Get comments for a post
     */
    public function get_post_comments() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        $post_id = intval($_POST['post_id']);

        global $wpdb;

        $comments_table = $wpdb->prefix . 'myavana_post_comments';
        $users_table = $wpdb->users;
        $likes_table = $wpdb->prefix . 'myavana_ci_comment_likes';

        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name
             FROM $comments_table c
             LEFT JOIN $users_table u ON c.user_id = u.ID
             WHERE c.post_id = %d AND c.parent_id = 0
             ORDER BY c.created_at ASC",
            $post_id
        ));

        // Enhance comments with user data
        foreach ($comments as &$comment) {
            $comment->user_avatar = get_avatar_url($comment->user_id, 40);
            $comment->formatted_date = human_time_diff(strtotime($comment->created_at)) . ' ago';
            $comment->post_id = $post_id;

            // Get reply count
            $comment->reply_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $comments_table WHERE parent_id = %d",
                $comment->id
            ));

            // Get like count and check if current user liked it
            $comment->likes_count = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $likes_table WHERE comment_id = %d",
                $comment->id
            ));

            $comment->is_liked = (bool)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $likes_table WHERE comment_id = %d AND user_id = %d",
                $comment->id,
                $this->user_id
            ));
        }

        wp_send_json_success($comments);
    }

    /**
     * Create a lightweight goal scaffold when a user joins a challenge.
     */
    private function create_challenge_goal_scaffold($challenge) {
        if (!$challenge || empty($challenge->id) || empty($challenge->title) || !$this->user_id) {
            return;
        }

        $goals = get_user_meta($this->user_id, 'myavana_hair_goals_structured', true);
        if (!is_array($goals)) {
            $goals = [];
        }

        foreach ($goals as $goal_item) {
            if (is_array($goal_item) && !empty($goal_item['source_challenge_id']) && (int) $goal_item['source_challenge_id'] === (int) $challenge->id) {
                return;
            }
        }

        $end_date = '';
        if (!empty($challenge->end_date)) {
            $end_ts = strtotime((string) $challenge->end_date);
            if ($end_ts) {
                $end_date = gmdate('Y-m-d', $end_ts);
            }
        }

        $goals[] = [
            'id' => 'challenge-' . (int) $challenge->id . '-' . time(),
            'title' => sanitize_text_field($challenge->title),
            'description' => sanitize_textarea_field((string) ($challenge->description ?? '')),
            'progress' => 0,
            'start_date' => current_time('Y-m-d'),
            'end_date' => $end_date,
            'source' => 'community_challenge',
            'source_challenge_id' => (int) $challenge->id,
        ];

        update_user_meta($this->user_id, 'myavana_hair_goals_structured', $goals);
    }

    /**
     * Bookmark/unbookmark a post
     */
    public function bookmark_post() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        if (!$this->user_id) {
            wp_send_json_error('You must be logged in');
            return;
        }

        $post_id = intval($_POST['post_id']);
        $collection = sanitize_text_field($_POST['collection'] ?? 'saved');

        global $wpdb;

        $bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';

        // Check if already bookmarked
        $existing_bookmark = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $bookmarks_table WHERE post_id = %d AND user_id = %d",
            $post_id, $this->user_id
        ));

        if ($existing_bookmark) {
            // Unbookmark
            $wpdb->delete(
                $bookmarks_table,
                array('post_id' => $post_id, 'user_id' => $this->user_id),
                array('%d', '%d')
            );

            wp_send_json_success(array(
                'action' => 'unbookmarked',
                'message' => 'Post removed from saved'
            ));
        } else {
            // Bookmark
            $result = $wpdb->insert(
                $bookmarks_table,
                array(
                    'post_id' => $post_id,
                    'user_id' => $this->user_id,
                    'collection' => $collection,
                    'bookmarked_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s')
            );

            if ($result) {
                wp_send_json_success(array(
                    'action' => 'bookmarked',
                    'message' => 'Post saved!'
                ));
            } else {
                wp_send_json_error('Failed to save post');
            }
        }
    }

    /**
     * Track post share
     */
    public function track_post_share() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        $post_id = intval($_POST['post_id']);
        $platform = sanitize_text_field($_POST['platform']);

        global $wpdb;

        $posts_table = $wpdb->prefix . 'myavana_community_posts';

        // Increment shares count
        $wpdb->query($wpdb->prepare(
            "UPDATE $posts_table SET shares_count = shares_count + 1 WHERE id = %d",
            $post_id
        ));

        wp_send_json_success(array('message' => 'Share tracked'));
    }

    /**
     * Get user profile data
     */
    public function get_user_profile() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);

        if (!$user) {
            wp_send_json_error('User not found');
            return;
        }

        global $wpdb;

        // Get user stats
        $stats = $this->get_user_social_stats($user_id);

        // Get recent posts
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $recent_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content, image_url, likes_count, comments_count, created_at, is_pinned
             FROM $posts_table
             WHERE user_id = %d AND privacy_level = 'public'
             ORDER BY is_pinned DESC, created_at DESC
             LIMIT 9",
            $user_id
        ));

        // Check if current user is following this user
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        $is_following = false;
        if ($this->user_id > 0) {
            $is_following = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $followers_table WHERE follower_id = %d AND following_id = %d",
                $this->user_id, $user_id
            ));
        }

        // Get hair journey stats from the active journey system
        $posts_table_wp = $wpdb->posts;
        $postmeta_table_wp = $wpdb->postmeta;

        $total_entries = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID)
             FROM {$posts_table_wp}
             WHERE post_type = 'hair_journey_entry'
               AND post_status = 'publish'
               AND post_author = %d",
            $user_id
        ));

        $journey_start_raw = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(post_date)
             FROM {$posts_table_wp}
             WHERE post_type = 'hair_journey_entry'
               AND post_status = 'publish'
               AND post_author = %d",
            $user_id
        ));

        $entries_last_30_days = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID)
             FROM {$posts_table_wp}
             WHERE post_type = 'hair_journey_entry'
               AND post_status = 'publish'
               AND post_author = %d
               AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));

        $avg_health_rating_raw = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(CAST(pm.meta_value AS DECIMAL(10,2)))
             FROM {$posts_table_wp} p
             INNER JOIN {$postmeta_table_wp} pm
                     ON pm.post_id = p.ID
                    AND pm.meta_key = 'health_rating'
             WHERE p.post_type = 'hair_journey_entry'
               AND p.post_status = 'publish'
               AND p.post_author = %d
               AND pm.meta_value REGEXP '^[0-9]+(\\\\.[0-9]+)?$'",
            $user_id
        ));

        $structured_goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
        if (!is_array($structured_goals)) {
            $structured_goals = [];
        }

        $current_routine = get_user_meta($user_id, 'current_routine', true);
        if (!is_array($current_routine)) {
            $current_routine = [];
        }

        $journey_stats = array(
            'total_entries' => $total_entries,
            'journey_start' => $journey_start_raw ? mysql2date('F Y', $journey_start_raw) : 'Recently',
            'entries_last_30_days' => $entries_last_30_days,
            'avg_health_rating' => $avg_health_rating_raw !== null ? round((float) $avg_health_rating_raw, 1) : null,
            'goals_count' => count($structured_goals),
            'routine_steps_count' => count($current_routine),
        );

        // Journey preview for profile offcanvas tab
        $recent_entry_ids = get_posts([
            'post_type' => 'hair_journey_entry',
            'author' => $user_id,
            'post_status' => 'publish',
            'posts_per_page' => 4,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        $entry_preview = [];
        foreach ($recent_entry_ids as $entry_id) {
            $entry_preview[] = [
                'id' => (int) $entry_id,
                'title' => get_the_title($entry_id),
                'date' => get_the_date('M j, Y', $entry_id),
                'health_rating' => get_post_meta($entry_id, 'health_rating', true),
                'mood' => get_post_meta($entry_id, 'mood_demeanor', true),
            ];
        }

        $goals_preview = [];
        foreach (array_slice($structured_goals, 0, 3) as $goal) {
            $goals_preview[] = [
                'title' => sanitize_text_field($goal['title'] ?? 'Hair Goal'),
                'progress' => isset($goal['progress']) ? (int) $goal['progress'] : 0,
                'target_date' => sanitize_text_field($goal['end_date'] ?? ($goal['target_date'] ?? '')),
            ];
        }

        $routine_preview = [];
        foreach (array_slice($current_routine, 0, 4) as $step) {
            if (is_array($step)) {
                $routine_preview[] = [
                    'name' => sanitize_text_field($step['name'] ?? 'Routine Step'),
                    'frequency' => sanitize_text_field($step['frequency'] ?? 'daily'),
                ];
            } else {
                $routine_preview[] = [
                    'name' => sanitize_text_field((string) $step),
                    'frequency' => 'daily',
                ];
            }
        }

        $profile = array(
            'user_id' => $user_id,
            'display_name' => $user->display_name,
            'avatar' => get_avatar_url($user_id, 120),
            'bio' => get_user_meta($user_id, 'description', true),
            'stats' => $stats,
            'recent_posts' => $recent_posts,
            'is_following' => $is_following,
            'hair_journey_stats' => $journey_stats,
            'hair_journey_preview' => [
                'entries' => $entry_preview,
                'goals' => $goals_preview,
                'routine' => $routine_preview,
            ],
        );

        wp_send_json_success($profile);
    }

    /**
     * Get trending posts
     */
    public function get_trending_posts() {
        $this->sync_user_id();
        if (!wp_verify_nonce($_POST['nonce'], 'myavana_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $users_table = $wpdb->users;
        
        $trending_posts = $wpdb->get_results("
            SELECT p.*, u.display_name, u.user_email,
                   (p.likes_count + p.comments_count + p.shares_count) as engagement_score
            FROM $posts_table p
            LEFT JOIN $users_table u ON p.user_id = u.ID
            WHERE p.privacy_level = 'public' 
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY engagement_score DESC, p.created_at DESC
            LIMIT 10
        ");
        
        // Enhance posts with additional data
        foreach ($trending_posts as &$post) {
            $post->user_avatar = get_avatar_url($post->user_id);
            $post->is_liked = $this->is_post_liked($post->id, $this->user_id);
            $post->formatted_date = human_time_diff(strtotime($post->created_at)) . ' ago';
        }
        
        wp_send_json_success($trending_posts);
    }

    /**
     * Discovery payload for community explore panel.
     */
    public function get_discovery_data() {
        $this->sync_user_id();
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'myavana_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $current_user_id = get_current_user_id();
        $trending_hashtags = $this->get_trending_hashtags(14);
        $suggested_creators = $this->get_suggested_creators($current_user_id, 8);
        $active_challenges = $this->get_active_challenges();

        $challenge_items = [];
        if (!empty($active_challenges)) {
            foreach (array_slice($active_challenges, 0, 6) as $challenge) {
                $challenge_items[] = [
                    'id' => (int) $challenge->id,
                    'title' => sanitize_text_field($challenge->title),
                    'hashtag' => sanitize_text_field($challenge->hashtag ?? ''),
                    'participants_count' => (int) $challenge->participants_count,
                    'end_date' => sanitize_text_field($challenge->end_date),
                ];
            }
        }

        wp_send_json_success([
            'trending_hashtags' => $trending_hashtags,
            'suggested_creators' => $suggested_creators,
            'active_challenges' => $challenge_items,
        ]);
    }

    /**
     * Return top hashtags used recently.
     */
    private function get_trending_hashtags($limit = 12) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT hashtags, content
             FROM {$posts_table}
             WHERE privacy_level = 'public'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 45 DAY)
             ORDER BY created_at DESC
             LIMIT %d",
            max(20, $limit * 25)
        ));

        $counts = [];
        foreach ($rows as $row) {
            if (!empty($row->hashtags)) {
                $tags = explode(',', strtolower((string) $row->hashtags));
                foreach ($tags as $tag) {
                    $clean = preg_replace('/[^a-z0-9_]/', '', trim((string) $tag));
                    if (strlen($clean) > 1) {
                        $counts[$clean] = ($counts[$clean] ?? 0) + 1;
                    }
                }
            }

            if (!empty($row->content)) {
                preg_match_all('/#([a-z0-9_]+)/i', (string) $row->content, $matches);
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $tag) {
                        $clean = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $tag));
                        if (strlen($clean) > 1) {
                            $counts[$clean] = ($counts[$clean] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        if (empty($counts)) {
            return [];
        }

        arsort($counts);
        $items = [];
        foreach (array_slice($counts, 0, $limit, true) as $tag => $total) {
            $items[] = [
                'tag' => $tag,
                'count' => (int) $total,
            ];
        }

        return $items;
    }

    /**
     * Return suggested creators ranked by recent engagement.
     */
    private function get_suggested_creators($current_user_id = 0, $limit = 8) {
        global $wpdb;

        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $followers_table = $wpdb->prefix . 'myavana_user_followers';
        $users_table = $wpdb->users;

        $current_user_id = (int) $current_user_id;
        $limit = max(1, min(24, (int) $limit));
        $query_args = [];

        $sql = "SELECT
                    u.ID AS user_id,
                    u.display_name,
                    COUNT(cp.id) AS posts_count,
                    COALESCE(SUM(cp.likes_count + cp.comments_count + cp.shares_count), 0) AS engagement_score,
                    (
                        SELECT COUNT(*)
                        FROM {$followers_table} f2
                        WHERE f2.following_id = u.ID
                    ) AS followers_count
                FROM {$users_table} u
                INNER JOIN {$posts_table} cp
                        ON cp.user_id = u.ID
                       AND cp.privacy_level = 'public'
                       AND cp.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                WHERE 1=1";

        if ($current_user_id > 0) {
            $sql .= " AND u.ID <> %d
                      AND u.ID NOT IN (
                          SELECT following_id
                          FROM {$followers_table}
                          WHERE follower_id = %d
                      )";
            $query_args[] = $current_user_id;
            $query_args[] = $current_user_id;
        }

        $sql .= " GROUP BY u.ID
                  ORDER BY engagement_score DESC, followers_count DESC, posts_count DESC
                  LIMIT %d";
        $query_args[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$query_args));
        $items = [];

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $items[] = [
                    'user_id' => (int) $row->user_id,
                    'display_name' => sanitize_text_field($row->display_name ?: 'Community Member'),
                    'avatar' => get_avatar_url((int) $row->user_id, ['size' => 72]),
                    'posts_count' => (int) $row->posts_count,
                    'followers_count' => (int) $row->followers_count,
                    'engagement_score' => (int) $row->engagement_score,
                ];
            }
        }

        return $items;
    }
    
    /**
     * Get active community challenges
     */
    public function get_active_challenges() {
        global $wpdb;
        
        $challenges_table = $wpdb->prefix . 'myavana_community_challenges';
        
        $challenges = $wpdb->get_results("
            SELECT *
            FROM $challenges_table
            WHERE is_active = 1 
                AND end_date > NOW()
            ORDER BY start_date DESC
        ");
        
        return $challenges;
    }
    
    /**
     * Get user's social stats
     */
    public function get_user_social_stats($user_id = null) {
        if (!$user_id) {
            $user_id = $this->sync_user_id();
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
    private function is_post_liked($post_id, $user_id) {
        global $wpdb;

        $likes_table = $wpdb->prefix . 'myavana_post_likes';

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $likes_table WHERE post_id = %d AND user_id = %d",
            $post_id, $user_id
        ));
    }

    private function is_post_bookmarked($post_id, $user_id) {
        global $wpdb;

        $bookmarks_table = $wpdb->prefix . 'myavana_post_bookmarks';
        $collections_table = $wpdb->prefix . 'myavana_ci_bookmark_collections';
        $collection_items_table = $wpdb->prefix . 'myavana_ci_collection_items';

        $is_quick_saved = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $bookmarks_table WHERE post_id = %d AND user_id = %d",
            $post_id, $user_id
        ));

        if ($is_quick_saved) {
            return true;
        }

        // Also treat collection saves as bookmarked/saved
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT ci.id
             FROM $collection_items_table ci
             INNER JOIN $collections_table c ON c.id = ci.collection_id
             WHERE ci.post_id = %d
               AND c.user_id = %d
             LIMIT 1",
            $post_id,
            $user_id
        ));
    }
    
    private function get_recent_comments($post_id, $limit = 3) {
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
    private function get_post_reactions($post_id, $user_id) {
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
    
    private function handle_image_upload($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return array('success' => false, 'error' => $uploaded_file['error']);
        }
        
        return array(
            'success' => true, 
            'url' => $uploaded_file['url'],
            'path' => $uploaded_file['file']
        );
    }

    private function handle_video_upload($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $max_size_bytes = 40 * 1024 * 1024; // 40MB
        $file_size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($file_size > $max_size_bytes) {
            return ['success' => false, 'error' => 'Video file is too large. Maximum size is 40MB.'];
        }

        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'mp4' => 'video/mp4',
                'm4v' => 'video/mp4',
                'mov' => 'video/quicktime',
                'webm' => 'video/webm',
                'ogv' => 'video/ogg',
            ],
        ];

        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        if (isset($uploaded_file['error'])) {
            return ['success' => false, 'error' => $uploaded_file['error']];
        }

        return [
            'success' => true,
            'url' => $uploaded_file['url'],
            'path' => $uploaded_file['file'],
        ];
    }
}

// Initialize the social features system if not already created
if (!isset($GLOBALS['myavana_social_features_instance'])) {
    $GLOBALS['myavana_social_features_instance'] = new Myavana_Social_Features();
}
}
