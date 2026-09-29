<?php
/**
 * Stories — a photo or short video that stays in Community for 24 hours.
 *
 * Two small tables: the stories themselves and who has seen each one, so
 * the rail can ring unseen stories and a member can see who watched hers.
 *
 * @package Myavana\Next\Domain\Community
 */

namespace Myavana\Next\Domain\Community;

use Myavana\Next\Core\MemberName;

if (!defined('ABSPATH')) {
    exit;
}

class StoryRepository {
    private const SCHEMA_VERSION = '1';
    private const SCHEMA_OPTION = 'myavana_next_stories_schema';
    public const LIFETIME = DAY_IN_SECONDS;
    private const MAX_PER_DAY = 20;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'myavana_stories';
    }

    public static function viewsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'myavana_story_views';
    }

    /** Create or upgrade the tables once per schema version. */
    public static function maybeInstall(): void {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta('CREATE TABLE ' . self::table() . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            media_type varchar(10) NOT NULL DEFAULT 'image',
            media_url text NOT NULL,
            poster_url text NULL,
            caption varchar(280) NOT NULL DEFAULT '',
            duration smallint(5) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$charset};");
        dbDelta('CREATE TABLE ' . self::viewsTable() . " (
            story_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            viewed_at datetime NOT NULL,
            PRIMARY KEY  (story_id,user_id),
            KEY user_id (user_id)
        ) {$charset};");
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION);
    }

    /**
     * Active stories grouped by member: the viewer's own first, then
     * members with something she hasn't seen, then the rest; newest first
     * within each band.
     */
    public function activeGrouped(int $viewerId): array {
        global $wpdb;
        $now = current_time('mysql', true);
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE expires_at > %s ORDER BY created_at ASC LIMIT 500',
            $now
        ), ARRAY_A);
        if (!$rows) {
            return [];
        }

        $seen = [];
        $viewCounts = [];
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $in = implode(',', $ids);
        if ($viewerId) {
            foreach ($wpdb->get_col($wpdb->prepare('SELECT story_id FROM ' . self::viewsTable() . " WHERE user_id = %d AND story_id IN ({$in})", $viewerId)) as $sid) {
                $seen[(int) $sid] = true;
            }
            foreach ($wpdb->get_results('SELECT story_id, COUNT(*) AS n FROM ' . self::viewsTable() . " WHERE story_id IN ({$in}) GROUP BY story_id", ARRAY_A) as $vc) {
                $viewCounts[(int) $vc['story_id']] = (int) $vc['n'];
            }
        }

        $groups = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            if (!isset($groups[$uid])) {
                $groups[$uid] = [
                    'user' => $this->member($uid),
                    'isMine' => $uid === $viewerId,
                    'items' => [],
                    'allSeen' => true,
                    'latest' => '',
                ];
            }
            $isSeen = isset($seen[(int) $r['id']]) || $uid === $viewerId;
            $groups[$uid]['items'][] = $this->shape($r, $isSeen, $uid === $viewerId ? ($viewCounts[(int) $r['id']] ?? 0) : null);
            $groups[$uid]['allSeen'] = $groups[$uid]['allSeen'] && $isSeen;
            $groups[$uid]['latest'] = $r['created_at'];
        }
        $groups = array_values(array_filter($groups, static fn($g) => $g['user'] !== null));

        usort($groups, static function ($a, $b) {
            if ($a['isMine'] !== $b['isMine']) {
                return $a['isMine'] ? -1 : 1;
            }
            if ($a['allSeen'] !== $b['allSeen']) {
                return $a['allSeen'] ? 1 : -1;
            }
            return strcmp($b['latest'], $a['latest']);
        });
        return $groups;
    }

    /** @return array|\WP_Error */
    public function create(int $userId, array $data) {
        global $wpdb;
        $base = wp_upload_dir()['baseurl'];
        $type = ($data['type'] ?? '') === 'video' ? 'video' : 'image';
        $url = esc_url_raw((string) ($data['url'] ?? ''));
        if ($url === '' || strpos($url, $base) !== 0) {
            return new \WP_Error('invalid_media', __('Add a photo or video for your story.', 'myavana-hair-journey-next'));
        }
        $poster = esc_url_raw((string) ($data['poster'] ?? ''));
        if ($poster !== '' && strpos($poster, $base) !== 0) {
            $poster = '';
        }

        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $today = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND created_at > %s', $userId, $since));
        if ($today >= self::MAX_PER_DAY) {
            return new \WP_Error('too_many', __('That is a lot of stories for one day. Try again tomorrow.', 'myavana-hair-journey-next'));
        }

        $now = time();
        $ok = $wpdb->insert(self::table(), [
            'user_id' => $userId,
            'media_type' => $type,
            'media_url' => $url,
            'poster_url' => $poster,
            'caption' => mb_substr(sanitize_text_field((string) ($data['caption'] ?? '')), 0, 280),
            'duration' => max(0, min(600, (int) round((float) ($data['duration'] ?? 0)))),
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'expires_at' => gmdate('Y-m-d H:i:s', $now + self::LIFETIME),
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
        if (!$ok) {
            return new \WP_Error('db_error', __('We could not share your story. Please try again.', 'myavana-hair-journey-next'));
        }
        return (int) $wpdb->insert_id;
    }

    public function markViewed(int $storyId, int $userId): void {
        global $wpdb;
        $owner = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . self::table() . ' WHERE id = %d', $storyId));
        if (!$owner || $owner === $userId) {
            return;
        }
        $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . self::viewsTable() . ' (story_id, user_id, viewed_at) VALUES (%d, %d, %s)',
            $storyId,
            $userId,
            gmdate('Y-m-d H:i:s')
        ));
    }

    /** Who watched one of her stories, newest first. */
    public function viewers(int $storyId, int $ownerId): array {
        global $wpdb;
        $owner = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . self::table() . ' WHERE id = %d', $storyId));
        if ($owner !== $ownerId) {
            return [];
        }
        $ids = $wpdb->get_col($wpdb->prepare('SELECT user_id FROM ' . self::viewsTable() . ' WHERE story_id = %d ORDER BY viewed_at DESC LIMIT 100', $storyId));
        return array_values(array_filter(array_map(fn($id) => $this->member((int) $id), $ids)));
    }

    public function delete(int $storyId, int $userId): bool {
        global $wpdb;
        $owner = (int) $wpdb->get_var($wpdb->prepare('SELECT user_id FROM ' . self::table() . ' WHERE id = %d', $storyId));
        if (!$owner || ($owner !== $userId && !current_user_can('moderate_comments'))) {
            return false;
        }
        $wpdb->delete(self::viewsTable(), ['story_id' => $storyId], ['%d']);
        return (bool) $wpdb->delete(self::table(), ['id' => $storyId], ['%d']);
    }

    /** Drop views of stories that expired a week ago or more. */
    public static function prune(): void {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS);
        $old = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE expires_at < %s LIMIT 500', $cutoff));
        if ($old) {
            $in = implode(',', array_map('intval', $old));
            $wpdb->query('DELETE FROM ' . self::viewsTable() . " WHERE story_id IN ({$in})");
            $wpdb->query('DELETE FROM ' . self::table() . " WHERE id IN ({$in})");
        }
    }

    private function shape(array $r, bool $seen, ?int $views): array {
        $created = strtotime($r['created_at'] . ' UTC');
        return [
            'id' => (int) $r['id'],
            'type' => $r['media_type'] === 'video' ? 'video' : 'image',
            'url' => (string) $r['media_url'],
            'poster' => (string) ($r['poster_url'] ?? ''),
            'caption' => (string) $r['caption'],
            'duration' => (int) $r['duration'],
            'createdAt' => gmdate('c', $created),
            'ago' => human_time_diff($created, time()),
            'seen' => $seen,
            'views' => $views,
        ];
    }

    private function member(int $userId): ?array {
        $user = get_userdata($userId);
        if (!$user) {
            return null;
        }
        $name = MemberName::displayFor($user);
        $custom = get_user_meta($userId, 'myavana_custom_avatar_url', true);
        return [
            'id' => $userId,
            'name' => $name,
            'firstName' => MemberName::forUser($user),
            'avatar' => is_string($custom) && $custom !== '' ? $custom : get_avatar_url($userId, ['size' => 128]),
            'fallback' => MemberName::initialAvatarUri($name),
        ];
    }
}
