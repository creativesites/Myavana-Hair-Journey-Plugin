<?php
/**
 * Community integration helpers, ported from the original Hair Journey
 * plugin (includes/community-integration.php). SocialFeatures and
 * CommunitySharingHandlers call these statically; without the original
 * plugin active the class was missing, so creating a post, liking,
 * commenting and sharing an entry all ended in a fatal error.
 *
 * Only the methods this plugin calls are kept.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Myavana_Community_Integration')) {
    class Myavana_Community_Integration {

        private static $tableExists = [];

        private static function tableExists(string $suffix): bool {
            global $wpdb;
            if (!isset(self::$tableExists[$suffix])) {
                $table = $wpdb->prefix . $suffix;
                self::$tableExists[$suffix] = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            }
            return self::$tableExists[$suffix];
        }

        public static function is_entry_shareable($entry_id) {
            global $wpdb;
            $entry = get_post($entry_id);
            if (!$entry || $entry->post_type !== 'hair_journey_entry') {
                return false;
            }
            $already_shared = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}myavana_shared_entries WHERE entry_id = %d",
                $entry_id
            ));
            return !$already_shared;
        }

        public static function share_entry($entry_id, $privacy = 'public') {
            global $wpdb;
            $user_id = get_current_user_id();
            $entry = get_post($entry_id);

            if (!$entry || $entry->post_type !== 'hair_journey_entry') {
                return new WP_Error('not_found', __('Entry not found.', 'myavana-hair-journey-next'));
            }
            if ((int) $entry->post_author !== (int) $user_id) {
                return new WP_Error('unauthorized', __('You can only share your own entries.', 'myavana-hair-journey-next'));
            }

            $image_url = '';
            $thumbnail_id = get_post_thumbnail_id($entry_id);
            if ($thumbnail_id) {
                $image_url = (string) wp_get_attachment_url($thumbnail_id);
            }
            if ($image_url === '') {
                // Current app entries store photo URLs; older ones a gallery of attachment IDs.
                $photos = get_post_meta($entry_id, 'entry_photos', true);
                if (is_array($photos) && !empty($photos[0])) {
                    $image_url = (string) $photos[0];
                } else {
                    $gallery = get_post_meta($entry_id, '_entry_gallery', true);
                    if (is_array($gallery) && !empty($gallery[0])) {
                        $image_url = (string) wp_get_attachment_url($gallery[0]);
                    }
                }
            }

            // The story alone: ratings and moods are private journal data.
            $content = wp_specialchars_decode((string) $entry->post_content, ENT_QUOTES);

            $inserted = $wpdb->insert(
                $wpdb->prefix . 'myavana_community_posts',
                [
                    'user_id' => $user_id,
                    'title' => sanitize_text_field($entry->post_title),
                    'content' => sanitize_textarea_field($content),
                    'image_url' => esc_url_raw($image_url),
                    'post_type' => 'progress',
                    'privacy_level' => in_array($privacy, ['public', 'followers', 'private'], true) ? $privacy : 'public',
                    'source_entry_id' => $entry_id,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
            );
            if (!$inserted) {
                return new WP_Error('db_error', __('Could not create the community post.', 'myavana-hair-journey-next'));
            }
            $new_post_id = (int) $wpdb->insert_id;

            $wpdb->insert(
                $wpdb->prefix . 'myavana_shared_entries',
                [
                    'entry_id' => $entry_id,
                    'community_post_id' => $new_post_id,
                    'user_id' => $user_id,
                    'shared_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%d', '%s']
            );

            self::award_community_points($user_id, 'share_entry');
            return $new_post_id;
        }

        public static function extract_hashtags($content) {
            if (empty($content)) {
                return '';
            }
            preg_match_all('/#(\w+)/', (string) $content, $matches);
            return empty($matches[0]) ? '' : implode(',', array_unique($matches[0]));
        }

        public static function award_community_points($user_id, $action) {
            $point_values = [
                'share_entry' => 15,
                'first_like' => 5,
                'first_comment' => 10,
                'complete_challenge' => 100,
                'help_someone' => 3,
                'weekly_top_contributor' => 200,
                'create_post' => 10,
            ];
            $points = $point_values[$action] ?? 0;

            if ($points > 0 && function_exists('myavana_award_points')) {
                myavana_award_points(
                    $user_id,
                    $points,
                    'Community action: ' . $action,
                    'community_action',
                    0,
                    'community_action:' . $action . ':' . $user_id . ':' . gmdate('YmdHis')
                );
            }
            if ($points > 0) {
                self::check_badge_eligibility((int) $user_id);
            }
            return $points;
        }

        private static function check_badge_eligibility(int $user_id): void {
            global $wpdb;
            if (!self::tableExists('myavana_badges') || !self::tableExists('myavana_user_badges')) {
                return;
            }
            $posts_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}myavana_community_posts WHERE user_id = %d",
                $user_id
            ));
            if ($posts_count >= 1) {
                self::award_badge($user_id, 'community_starter');
            }
            if ($posts_count >= 50) {
                self::award_badge($user_id, 'community_champion');
            }
        }

        private static function award_badge(int $user_id, string $badge_key) {
            global $wpdb;
            $badge = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}myavana_badges WHERE badge_key = %s",
                $badge_key
            ));
            if (!$badge) {
                return false;
            }
            $has_badge = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}myavana_user_badges WHERE user_id = %d AND badge_id = %d",
                $user_id,
                $badge->id
            ));
            if ($has_badge) {
                return false;
            }
            $result = $wpdb->insert(
                $wpdb->prefix . 'myavana_user_badges',
                ['user_id' => $user_id, 'badge_id' => $badge->id, 'earned_at' => current_time('mysql'), 'notified' => 0],
                ['%d', '%d', '%s', '%d']
            );
            if ($result) {
                self::create_notification($user_id, 'badge_earned', [
                    'badge_name' => $badge->name,
                    'badge_description' => $badge->description,
                ]);
            }
            return $result;
        }

        public static function create_notification($user_id, $type, $data = []) {
            global $wpdb;
            if (!self::tableExists('myavana_notifications')) {
                return false;
            }
            $templates = [
                'new_like' => ['title' => 'New Like', 'message' => '{user} liked your post'],
                'new_comment' => ['title' => 'New Comment', 'message' => '{user} commented on your post'],
                'new_follower' => ['title' => 'New Follower', 'message' => '{user} started following you'],
                'badge_earned' => ['title' => 'Badge Earned!', 'message' => 'You earned the {badge_name} badge!'],
                'challenge_milestone' => ['title' => 'Challenge Progress', 'message' => 'You reached {milestone}% in {challenge_name}'],
                'new_post' => ['title' => 'New Post', 'message' => '{user} shared a new post'],
            ];
            if (!isset($templates[$type])) {
                return false;
            }
            $message = $templates[$type]['message'];
            foreach ($data as $key => $value) {
                if (is_scalar($value)) {
                    $message = str_replace('{' . $key . '}', (string) $value, $message);
                }
            }
            return $wpdb->insert(
                $wpdb->prefix . 'myavana_notifications',
                [
                    'user_id' => $user_id,
                    'type' => $type,
                    'title' => $templates[$type]['title'],
                    'message' => $message,
                    'action_url' => $data['action_url'] ?? '',
                    'related_user_id' => $data['related_user_id'] ?? null,
                    'related_post_id' => $data['related_post_id'] ?? null,
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
            );
        }
    }
}
