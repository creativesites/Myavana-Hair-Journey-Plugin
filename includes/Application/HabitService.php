<?php
/**
 * Habit Service — the engagement plan's first phase, computed from a
 * member's real entries rather than stored counters:
 *
 *  - weekly rhythm: weeks in a row with at least one entry (hair care is
 *    weekly, so a daily streak mostly shows people zeros);
 *  - first-week path: three small steps for new members;
 *  - moments: quiet timeline markers for things that really happened.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

if (!defined('ABSPATH')) {
    exit;
}

class HabitService {
    public const FIRST_WEEK_DISMISSED_META = 'myavana_first_week_dismissed';

    /**
     * @param array $entries Entry arrays (any order) with a 'date'.
     * @return array{weeksInARow:int, loggedThisWeek:bool}
     */
    public function weeklyRhythm(array $entries): array {
        $weeks = [];
        foreach ($entries as $entry) {
            $ts = strtotime((string) ($entry['date'] ?? ''));
            if ($ts) {
                $weeks[$this->weekKey($ts)] = true;
            }
        }

        $now = strtotime(current_time('Y-m-d'));
        $thisWeek = $this->weekKey($now);
        $logged = isset($weeks[$thisWeek]);

        // A rhythm isn't broken until a whole week passes without an entry.
        $cursor = $logged ? $now : $now - WEEK_IN_SECONDS;
        $count = 0;
        while (isset($weeks[$this->weekKey($cursor)])) {
            $count++;
            $cursor -= WEEK_IN_SECONDS;
        }

        return ['weeksInARow' => $count, 'loggedThisWeek' => $logged];
    }

    /**
     * Three small steps for a new member. Null once done, dismissed, or for
     * members who are clearly past their first weeks.
     *
     * @param int   $userId
     * @param array $entries Entry arrays.
     * @param array $goals   Structured goals.
     * @return array|null
     */
    public function firstWeek(int $userId, array $entries, array $goals): ?array {
        if (get_user_meta($userId, self::FIRST_WEEK_DISMISSED_META, true)) {
            return null;
        }

        $user = get_userdata($userId);
        $registered = $user ? strtotime($user->user_registered) : 0;
        $isNew = $registered && (time() - $registered) <= 45 * DAY_IN_SECONDS;
        if (!$isNew && count($entries) >= 3) {
            return null;
        }

        $hasPhoto = false;
        $weeks = [];
        $firstTs = null;
        foreach ($entries as $entry) {
            if (!empty($entry['photos']) || !empty($entry['featuredImage'])) {
                $hasPhoto = true;
            }
            $ts = strtotime((string) ($entry['date'] ?? ''));
            if ($ts) {
                $weeks[$this->weekKey($ts)] = true;
                $firstTs = $firstTs === null ? $ts : min($firstTs, $ts);
            }
        }

        $hasGoal = false;
        foreach ($goals as $goal) {
            if (is_array($goal) && trim((string) ($goal['title'] ?? $goal['goal_title'] ?? '')) !== ''
                && ($goal['status'] ?? 'active') !== 'completed') {
                $hasGoal = true;
                break;
            }
        }

        $again = count($weeks) >= 2;
        $againFrom = '';
        if ($firstTs !== null && !$again) {
            // Completes with an entry in a later week than the one(s) logged.
            // If this week has no entry yet, any entry now counts.
            $today = strtotime(current_time('Y-m-d'));
            if (isset($weeks[$this->weekKey($today)])) {
                $againFrom = date_i18n('M j', strtotime('monday next week', $today));
            }
        }
        $againHint = __('Come back next week and add one more. That is how your story starts to show.', 'myavana-hair-journey-next');
        if ($againFrom !== '') {
            $againHint = sprintf(
                /* translators: %s: a date, e.g. "Oct 5" */
                __('Add another from %s to see your first change over time.', 'myavana-hair-journey-next'),
                $againFrom
            );
        }

        $steps = [
            [
                'key' => 'photo',
                'label' => __('Add your first photo', 'myavana-hair-journey-next'),
                'hint' => __('A clear photo today becomes your "before".', 'myavana-hair-journey-next'),
                'done' => $hasPhoto,
                'action' => 'entry',
            ],
            [
                'key' => 'goal',
                'label' => __('Name one goal', 'myavana-hair-journey-next'),
                'hint' => __('Length, moisture, less breakage: whatever matters most to you.', 'myavana-hair-journey-next'),
                'done' => $hasGoal,
                'action' => 'goal',
            ],
            [
                'key' => 'again',
                'label' => __('Log again next week', 'myavana-hair-journey-next'),
                'hint' => $againHint,
                'done' => $again,
                'action' => 'entry',
                'availableFrom' => $againFrom,
            ],
        ];

        $done = count(array_filter($steps, static fn($s) => $s['done']));
        if ($done === count($steps)) {
            return null;
        }

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }

    /**
     * Moments worth marking on the timeline, keyed by entry id.
     *
     * @param array $entries Entry arrays (any order).
     * @param array $goals   Structured goals.
     * @return array{byEntry: array<int, array>, list: array}
     */
    public function moments(array $entries, array $goals): array {
        usort($entries, static function ($a, $b) {
            return strtotime((string) $a['date']) <=> strtotime((string) $b['date']);
        });

        $byEntry = [];
        $list = [];
        $add = function (array $entry, string $key, string $label) use (&$byEntry, &$list) {
            $id = (int) ($entry['id'] ?? 0);
            if (!$id) {
                return;
            }
            $moment = ['key' => $key, 'label' => $label, 'entryId' => $id, 'date' => substr((string) $entry['date'], 0, 10)];
            $byEntry[$id][] = $moment;
            $list[] = $moment;
        };

        if (empty($entries)) {
            return ['byEntry' => [], 'list' => []];
        }

        $first = $entries[0];
        $firstTs = strtotime((string) $first['date']);
        $add($first, 'first_entry', __('Where it started', 'myavana-hair-journey-next'));

        $seenPhoto = false;
        $seenLength = false;
        $seenMonth = false;
        $seenYear = false;
        foreach ($entries as $entry) {
            $hasPhoto = !empty($entry['photos']) || !empty($entry['featuredImage']);
            if (!$seenPhoto && $hasPhoto) {
                $seenPhoto = true;
                $add($entry, 'first_photo', __('First photo', 'myavana-hair-journey-next'));
            }
            if (!$seenLength && ($entry['entryType'] ?? '') === 'length_check') {
                $seenLength = true;
                $add($entry, 'first_length', __('First length check', 'myavana-hair-journey-next'));
            }
            $ts = strtotime((string) $entry['date']);
            // Anniversaries mark an entry near the date, never one months later.
            $age = $ts - $firstTs;
            if (!$seenMonth && $age >= 30 * DAY_IN_SECONDS && $age < 60 * DAY_IN_SECONDS) {
                $seenMonth = true;
                $add($entry, 'one_month', __('One month in', 'myavana-hair-journey-next'));
            }
            if (!$seenYear && $age >= 365 * DAY_IN_SECONDS && $age < 430 * DAY_IN_SECONDS) {
                $seenYear = true;
                $add($entry, 'one_year', __('One year of your journey', 'myavana-hair-journey-next'));
            }
        }

        // A reached goal is marked on the entry closest after it was completed.
        foreach ($goals as $goal) {
            if (!is_array($goal) || ($goal['status'] ?? '') !== 'completed') {
                continue;
            }
            $doneTs = strtotime((string) ($goal['completed_at'] ?? $goal['updated_at'] ?? ''));
            if (!$doneTs) {
                continue;
            }
            foreach ($entries as $entry) {
                if (strtotime((string) $entry['date']) >= $doneTs - DAY_IN_SECONDS) {
                    $title = trim((string) ($goal['title'] ?? $goal['goal_title'] ?? ''));
                    $add($entry, 'goal_reached', $title !== ''
                        ? sprintf(__('Goal reached: %s', 'myavana-hair-journey-next'), $title)
                        : __('Goal reached', 'myavana-hair-journey-next'));
                    break;
                }
            }
        }

        return ['byEntry' => $byEntry, 'list' => $list];
    }

    private function weekKey(int $ts): string {
        return date('o-W', $ts);
    }
}
