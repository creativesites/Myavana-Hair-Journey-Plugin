<?php
/**
 * Weekly Note — one short Sunday email to recently active members:
 * their latest photo, their weekly rhythm, and one "Log this week" button.
 *
 * Sends through wp_mail (the site's current mail setup). Off until the team
 * enables it in Settings → MYAVANA Next, after checking a test email.
 *
 * Safeguards: only members with an entry in the last 30 days and "Email care
 * reminders" on; at most one note per member per week; batches of 40 per
 * run; a signed one-click unsubscribe link in every email.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Core\MemberName;
use Myavana\Next\Domain\Journal\JournalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class WeeklyNoteService {
    public const HOOK = 'myavana_weekly_note';
    public const ENABLED_OPTION = 'myavana_next_weekly_note_enabled';
    public const SENT_META = 'myavana_weekly_note_sent_week';
    private const BATCH = 40;

    public static function init(): void {
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_action(self::HOOK . '_batch', [__CLASS__, 'run']);
        add_action('admin_post_myavana_weekly_note_test', [__CLASS__, 'handleTestSend']);
        add_action('init', [__CLASS__, 'schedule']);
        add_action('template_redirect', [__CLASS__, 'handleUnsubscribe']);
    }

    /** Sundays at 09:00 site time. */
    public static function schedule(): void {
        if (wp_next_scheduled(self::HOOK)) {
            return;
        }
        $tz = wp_timezone();
        $next = new \DateTimeImmutable('next sunday 09:00', $tz);
        wp_schedule_event($next->getTimestamp(), 'weekly', self::HOOK);
    }

    public static function isEnabled(): bool {
        return (bool) get_option(self::ENABLED_OPTION, false);
    }

    /** Send this week's notes (a batch; reschedules itself if more remain). */
    public static function run(): void {
        if (!self::isEnabled()) {
            return;
        }
        $week = wp_date('o-W');
        $recipients = self::recipients($week, self::BATCH + 1);
        $more = count($recipients) > self::BATCH;
        foreach (array_slice($recipients, 0, self::BATCH) as $userId) {
            // Mark first, so a mail failure can't cause a resend loop.
            update_user_meta($userId, self::SENT_META, $week);
            self::send($userId);
        }
        if ($more && !wp_next_scheduled(self::HOOK . '_batch')) {
            wp_schedule_single_event(time() + 5 * MINUTE_IN_SECONDS, self::HOOK . '_batch');
        }
    }

    /**
     * Member IDs due a note this week.
     *
     * @return int[]
     */
    public static function recipients(string $week, int $limit): array {
        global $wpdb;
        $since = wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.post_author FROM {$wpdb->posts} p
             WHERE p.post_type = 'hair_journey_entry' AND p.post_status = 'publish' AND p.post_date >= %s",
            $since
        ));
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (get_user_meta($id, 'myavana_email_notifications', true) === '0') {
                continue;
            }
            if (get_user_meta($id, self::SENT_META, true) === $week) {
                continue;
            }
            $out[] = $id;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public static function send(int $userId, ?string $to = null): bool {
        $user = get_userdata($userId);
        if (!$user || !is_email($user->user_email)) {
            return false;
        }
        [$subject, $html] = self::compose($userId);
        return wp_mail($to ?: $user->user_email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** @return array{0:string,1:string} Subject and HTML body. */
    public static function compose(int $userId): array {
        $name = MemberName::forUser($userId, false);
        $entries = (new JournalRepository())->getEntries($userId, ['perPage' => 60])['items'] ?? [];
        $rhythm = (new HabitService())->weeklyRhythm($entries);
        $weeks = (int) $rhythm['weeksInARow'];

        $photo = '';
        foreach ($entries as $entry) {
            $photo = $entry['featuredImage'] ?: ($entry['photos'][0] ?? '');
            if ($photo) {
                break;
            }
        }

        $subject = $name !== ''
            ? sprintf(__('%s, your hair this week', 'myavana-hair-journey-next'), $name)
            : __('Your hair this week', 'myavana-hair-journey-next');

        if ($rhythm['loggedThisWeek']) {
            $line = $weeks > 1
                ? sprintf(__('You have logged %d weeks in a row. That rhythm is what makes change visible.', 'myavana-hair-journey-next'), $weeks)
                : __('This week is logged. Add one next week and your story starts to show.', 'myavana-hair-journey-next');
        } else {
            $line = $weeks > 0
                ? sprintf(__('You have logged %d weeks in a row. One quick entry keeps it going.', 'myavana-hair-journey-next'), $weeks)
                : __('A quick photo and a line about how your hair feels is all this week needs.', 'myavana-hair-journey-next');
        }

        $logUrl = home_url('/#today');
        $unsubscribe = self::unsubscribeUrl($userId);
        $logo = MYAVANA_NEXT_URL . 'assets/images/myavana-primary-logo.png';

        ob_start();
        ?>
<!doctype html>
<html><body style="margin:0;padding:0;background:#fdf8f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf8f5;padding:32px 16px;font-family:Archivo,Helvetica,Arial,sans-serif;color:#222323;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #eadfd8;">
<tr><td style="padding:28px 28px 8px;text-align:center;"><img src="<?php echo esc_url($logo); ?>" alt="MYAVANA" width="120" style="display:inline-block;border:0;"></td></tr>
<?php if ($photo) : ?>
<tr><td style="padding:12px 28px 0;"><img src="<?php echo esc_url($photo); ?>" alt="" width="464" style="display:block;width:100%;max-width:464px;height:auto;max-height:360px;object-fit:cover;border-radius:18px;border:0;"></td></tr>
<?php endif; ?>
<tr><td style="padding:24px 28px 8px;">
<p style="margin:0 0 6px;font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#9b5a49;font-weight:700;"><?php esc_html_e('Your week', 'myavana-hair-journey-next'); ?></p>
<h1 style="margin:0 0 12px;font-size:22px;line-height:1.2;color:#222323;"><?php echo esc_html($name !== '' ? sprintf(__('Hi %s,', 'myavana-hair-journey-next'), $name) : __('Hi there,', 'myavana-hair-journey-next')); ?></h1>
<p style="margin:0;font-size:15px;line-height:1.6;color:#4d4747;"><?php echo esc_html($line); ?></p>
</td></tr>
<tr><td style="padding:20px 28px 28px;">
<a href="<?php echo esc_url($logUrl); ?>" style="display:inline-block;padding:14px 28px;border-radius:999px;background:#222323;color:#ffffff;text-decoration:none;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;"><?php esc_html_e('Log this week', 'myavana-hair-journey-next'); ?></a>
</td></tr>
</table>
<p style="max-width:520px;margin:16px auto 0;font-size:12px;line-height:1.5;color:#8a807d;text-align:center;">
<?php esc_html_e('You get this because care reminders are on in your MYAVANA profile.', 'myavana-hair-journey-next'); ?>&nbsp;<a href="<?php echo esc_url($unsubscribe); ?>" style="color:#9b5a49;"><?php esc_html_e('Stop these emails', 'myavana-hair-journey-next'); ?></a>
</p>
</td></tr>
</table>
</body></html>
        <?php
        return [$subject, (string) ob_get_clean()];
    }

    /** Settings → "Send me a test": the current admin's own note, to them. */
    public static function handleTestSend(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'myavana-hair-journey-next'), '', ['response' => 403]);
        }
        check_admin_referer('myavana_weekly_note_test');
        $sent = self::send(get_current_user_id());
        wp_safe_redirect(add_query_arg('myavana_weekly_test', $sent ? 'sent' : 'failed', admin_url('options-general.php?page=myavana-next-settings')));
        exit;
    }

    public static function unsubscribeUrl(int $userId): string {
        return add_query_arg([
            'myavana_unsubscribe' => $userId,
            'sig' => self::signature($userId),
        ], home_url('/'));
    }

    private static function signature(int $userId): string {
        return substr(hash_hmac('sha256', 'weekly-note|' . $userId, wp_salt('auth')), 0, 32);
    }

    /** One click from the email turns care reminders off, no login needed. */
    public static function handleUnsubscribe(): void {
        if (!isset($_GET['myavana_unsubscribe'], $_GET['sig'])) {
            return;
        }
        $userId = (int) $_GET['myavana_unsubscribe'];
        $sig = sanitize_text_field(wp_unslash($_GET['sig']));
        if ($userId > 0 && hash_equals(self::signature($userId), $sig)) {
            update_user_meta($userId, 'myavana_email_notifications', '0');
            wp_die(
                esc_html__('You will no longer get the weekly note. You can turn care reminders back on in your MYAVANA profile any time.', 'myavana-hair-journey-next'),
                esc_html__('Unsubscribed', 'myavana-hair-journey-next'),
                ['response' => 200, 'link_url' => home_url('/'), 'link_text' => __('Back to MYAVANA', 'myavana-hair-journey-next')]
            );
        }
    }
}
