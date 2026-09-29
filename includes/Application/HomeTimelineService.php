<?php
/**
 * The Home page's 3D journey timeline: every moment of her journey as a
 * media card, oldest to newest. Visitors see recent public Community
 * moments instead.
 *
 * Images are served at a web size (the "large" variant when WordPress
 * has one) so the timeline never pulls full-resolution phone photos.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Core\MemberName;
use Myavana\Next\Domain\Journal\JournalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class HomeTimelineService {
    private const MAX_ITEMS = 60;
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    private const TYPE_LABELS = [
        'wash_day' => 'Wash day',
        'length_check' => 'Length check',
        'milestone' => 'Milestone',
        'setback' => 'Setback',
        'quick_checkin' => 'Check-in',
        'standard' => 'Entry',
    ];

    public static function init(): void {
        add_action('save_post_hair_journey_entry', [__CLASS__, 'flush'], 10, 2);
        add_action('before_delete_post', static function ($postId) {
            $post = get_post($postId);
            if ($post && $post->post_type === 'hair_journey_entry') {
                self::flush($postId, $post);
            }
        });
    }

    public static function flush($postId, $post = null): void {
        $post = $post ?: get_post($postId);
        if ($post) {
            delete_transient('myavana_home_tl_' . (int) $post->post_author);
        }
    }

    /** @return array{mode:string,items:array,startedAt:?string} */
    public function forUser(int $userId): array {
        $cached = get_transient('myavana_home_tl_' . $userId);
        if (is_array($cached)) {
            return $cached;
        }

        $entries = (new JournalRepository())->getEntries($userId, ['perPage' => 200])['items'] ?? [];
        usort($entries, static fn($a, $b) => strtotime((string) $a['date']) <=> strtotime((string) $b['date']));
        $first = $entries[0]['date'] ?? null;

        $items = [];
        foreach ($entries as $e) {
            $video = !empty($e['videos'][0]['url']) ? $e['videos'][0] : null;
            $photo = !empty($e['photos'][0]) ? (string) $e['photos'][0] : (string) ($e['featuredImage'] ?? '');
            if ($video && $photo === ($video['poster'] ?? '')) {
                $photo = '';
            }
            $image = $photo !== '' ? $photo : (string) ($video['poster'] ?? '');
            $items[] = [
                'id' => (int) $e['id'],
                'date' => $this->iso((string) $e['date']),
                'title' => (string) ($e['title'] ?? ''),
                'kind' => self::TYPE_LABELS[$e['entryType'] ?? ''] ?? 'Entry',
                'type' => (string) ($e['entryType'] ?? ''),
                'text' => wp_trim_words((string) ($e['notes'] ?? ''), 22, '…'),
                'image' => $image !== '' ? $this->webSize($image) : '',
                'video' => $video ? (string) $video['url'] : '',
                'duration' => $video ? (int) ($video['duration'] ?? 0) : 0,
                'count' => count((array) ($e['photos'] ?? [])) + count((array) ($e['videos'] ?? [])),
                'length' => $e['hairLength'] ?? null,
            ];
        }
        // Keep the most recent moments when there are many.
        if (count($items) > self::MAX_ITEMS) {
            $items = array_slice($items, -self::MAX_ITEMS);
        }

        $result = [
            'mode' => 'member',
            'items' => $items,
            'startedAt' => $first ? $this->iso((string) $first) : null,
        ];
        set_transient('myavana_home_tl_' . $userId, $result, self::CACHE_TTL);
        return $result;
    }

    /** Recent public Community moments with media, for visitors. */
    public function forVisitors(): array {
        $cached = get_transient('myavana_home_tl_public');
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_community_posts';
        $items = [];
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $rows = $wpdb->get_results(
                "SELECT id, user_id, title, content, image_url, video_url, created_at FROM {$table}
                 WHERE privacy_level = 'public' AND ((image_url IS NOT NULL AND image_url <> '') OR (video_url IS NOT NULL AND video_url <> ''))
                 ORDER BY created_at DESC LIMIT 24",
                ARRAY_A
            );
            foreach (array_reverse((array) $rows) as $r) {
                $name = MemberName::forUser((int) $r['user_id'], false);
                $items[] = [
                    'id' => (int) $r['id'],
                    'date' => $this->iso((string) $r['created_at']),
                    'title' => wp_strip_all_tags((string) $r['title']),
                    'kind' => $name !== '' ? $name : 'Community',
                    'type' => 'community',
                    'text' => wp_trim_words(wp_strip_all_tags((string) $r['content']), 18, '…'),
                    'image' => !empty($r['image_url']) ? $this->webSize((string) $r['image_url']) : '',
                    'video' => (string) ($r['video_url'] ?? ''),
                    'duration' => 0,
                    'count' => 1,
                    'length' => null,
                ];
            }
        }
        $result = ['mode' => 'community', 'items' => $items, 'startedAt' => null];
        set_transient('myavana_home_tl_public', $result, self::CACHE_TTL);
        return $result;
    }

    private function iso(string $mysql): string {
        $ts = strtotime($mysql);
        return $ts ? wp_date('c', $ts - (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)) : '';
    }

    /** The 1024px "large" variant of an uploaded image, when one exists. */
    private function webSize(string $url): string {
        $id = attachment_url_to_postid($url);
        if ($id) {
            $src = wp_get_attachment_image_src($id, 'large');
            if (!empty($src[0])) {
                return (string) $src[0];
            }
        }
        return $url;
    }
}
