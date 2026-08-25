<?php
/**
 * Rule-based fallback for the "today_insight" task — what Today shows when
 * no AI provider is configured, the call fails, times out, or returns a
 * malformed response. Every value it cites comes straight from the same
 * TodayInsightContextBuilder facts the AI path uses; nothing here is
 * fabricated (an earlier version of this class hardcoded numbers like a
 * "30-day streak" and "8/10 health rating" that were never computed from
 * real data — Today must never show a human-sounding claim it can't back).
 *
 * @package Myavana\Next\Domain\AI
 */

namespace Myavana\Next\Domain\AI;

if (!defined('ABSPATH')) {
    exit;
}

class InsightEngine {
    /**
     * @param array $context TodayInsightContextBuilder::build() output.
     * @return array {title, summary, recommendation, confidence, supporting_signals}
     */
    public function generateFallbackInsight(array $context): array {
        $moisture = $context['avg_moisture_level_last_7_days'] ?? null;
        $concerns = $context['top_concerns'] ?? [];
        $entries7d = (int) ($context['journal_entries_last_7_days'] ?? 0);
        $washDays = (int) ($context['wash_days_last_7_days'] ?? 0);
        $completion = (int) ($context['todays_routine_completion_percent'] ?? 0);
        $streak = (int) ($context['current_streak_days'] ?? 0);
        $dayCount = (int) ($context['day_count_on_journey'] ?? 1);

        if ($entries7d === 0) {
            return [
                'title' => __('Pick back up today', 'myavana-hair-journey-next'),
                'summary' => __("You haven't logged an entry in the last 7 days — even a quick check-in keeps your routine accurate.", 'myavana-hair-journey-next'),
                'recommendation' => __('Log a quick check-in to note how your hair feels right now.', 'myavana-hair-journey-next'),
                'confidence' => 'medium',
                'supporting_signals' => [__('0 journal entries in the last 7 days', 'myavana-hair-journey-next')],
            ];
        }

        if ($moisture !== null && $moisture <= 4) {
            return [
                'title' => __('Moisture needs attention', 'myavana-hair-journey-next'),
                'summary' => sprintf(__('Your recent entries show an average moisture level of %s out of 10 over the last 7 days.', 'myavana-hair-journey-next'), $moisture),
                'recommendation' => __('Add a hydrating mist or leave-in between wash days this week.', 'myavana-hair-journey-next'),
                'confidence' => 'medium',
                'supporting_signals' => [
                    sprintf(__('Average moisture level: %s/10 over the last 7 days', 'myavana-hair-journey-next'), $moisture),
                    sprintf(_n('%d journal entry logged in the last 7 days', '%d journal entries logged in the last 7 days', $entries7d, 'myavana-hair-journey-next'), $entries7d),
                ],
            ];
        }

        if (in_array('Dryness & Moisture', $concerns, true) && $washDays > 0) {
            return [
                'title' => __('Dryness is a stated concern', 'myavana-hair-journey-next'),
                'summary' => sprintf(__("Dryness & Moisture is one of your top concerns, and you've logged %d wash day(s) in the last 7 days.", 'myavana-hair-journey-next'), $washDays),
                'recommendation' => __('Try sealing with an oil right after your next wash to lock in moisture.', 'myavana-hair-journey-next'),
                'confidence' => 'low',
                'supporting_signals' => [
                    __('Dryness & Moisture listed as a top concern', 'myavana-hair-journey-next'),
                    sprintf(__('%d wash day(s) logged in the last 7 days', 'myavana-hair-journey-next'), $washDays),
                ],
            ];
        }

        if ($completion < 50 && $streak > 0) {
            return [
                'title' => __('Routine is slipping a little', 'myavana-hair-journey-next'),
                'summary' => sprintf(__("Today's routine is %d%% complete, even with a %d-day streak going.", 'myavana-hair-journey-next'), $completion, $streak),
                'recommendation' => __("Finish today's remaining steps to keep your streak intact.", 'myavana-hair-journey-next'),
                'confidence' => 'medium',
                'supporting_signals' => [
                    sprintf(__("Today's routine completion: %d%%", 'myavana-hair-journey-next'), $completion),
                    sprintf(__('%d-day current streak', 'myavana-hair-journey-next'), $streak),
                ],
            ];
        }

        return [
            'title' => __('Consistency is paying off', 'myavana-hair-journey-next'),
            'summary' => sprintf(__('Day %d of your journey, with a %d-day current streak.', 'myavana-hair-journey-next'), $dayCount, $streak),
            'recommendation' => __('Keep logging entries — the more your journey has, the more specific your guidance gets.', 'myavana-hair-journey-next'),
            'confidence' => 'low',
            'supporting_signals' => [
                sprintf(__('%d-day current streak', 'myavana-hair-journey-next'), $streak),
                sprintf(__('Day %d on your hair journey', 'myavana-hair-journey-next'), $dayCount),
            ],
        ];
    }
}
