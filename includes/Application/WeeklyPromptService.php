<?php
/**
 * Weekly Prompt — the MYAVANA team's weekly Community question
 * ("Wash-day Wednesday: show us your finish"). A team member ticks
 * "Make this the weekly prompt" when posting; members then see it above
 * the feed and on Today for a week, with an "Answer with an entry" button.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

if (!defined('ABSPATH')) {
    exit;
}

class WeeklyPromptService {
    public const OPTION = 'myavana_next_weekly_prompt';
    private const LIFETIME = 8 * DAY_IN_SECONDS;

    /** Team = can edit others' posts (editors and administrators). */
    public static function canSet(?int $userId = null): bool {
        return $userId ? user_can($userId, 'edit_others_posts') : current_user_can('edit_others_posts');
    }

    public static function set(int $postId): void {
        update_option(self::OPTION, ['post_id' => $postId, 'set_at' => time()], false);
    }

    /**
     * The live prompt, or null when none was set in the last week.
     *
     * @return array{id:int,title:string,content:string,image:string,author:string,date:string}|null
     */
    public static function current(): ?array {
        $saved = get_option(self::OPTION, []);
        $postId = (int) ($saved['post_id'] ?? 0);
        if (!$postId || (time() - (int) ($saved['set_at'] ?? 0)) > self::LIFETIME) {
            return null;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, title, content, image_url, created_at, privacy_level
             FROM {$wpdb->prefix}myavana_community_posts WHERE id = %d",
            $postId
        ));
        if (!$row || ($row->privacy_level ?? 'public') !== 'public') {
            return null;
        }
        $clean = static fn($v) => trim(wp_strip_all_tags(wp_specialchars_decode((string) $v, ENT_QUOTES)));
        return [
            'id' => (int) $row->id,
            'title' => $clean($row->title),
            'content' => $clean($row->content),
            'image' => (string) $row->image_url,
            'author' => \Myavana\Next\Core\MemberName::displayFor((int) $row->user_id),
            'date' => (string) $row->created_at,
        ];
    }
}
