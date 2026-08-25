<?php
/**
 * Assembles the FACTS for the "today_insight" task — computed from the
 * member's real data, never a raw dump of it. No passwords, tokens, emails,
 * or internal IDs; just the structured signals an insight can reason about.
 *
 * @package Myavana\Next\Domain\Intelligence\ContextBuilders
 */

namespace Myavana\Next\Domain\Intelligence\ContextBuilders;

use Myavana\Next\Domain\Profile\ProfileEntity;

if (!defined('ABSPATH')) {
    exit;
}

class TodayInsightContextBuilder {
    /**
     * @param ProfileEntity $profile
     * @param array $entries       Journal entries, newest first (as returned by JournalRepository::getEntries()).
     * @param array $checklist     RoutineService::getTodayChecklist() result.
     * @param array $goals         GoalRepository::getGoals() result.
     * @param int $streakDays      GamificationRepository::getStats()['currentStreak'].
     * @param int $dayCount        Days since the member's first entry.
     * @return array
     */
    public static function build(ProfileEntity $profile, array $entries, array $checklist, array $goals, int $streakDays, int $dayCount): array {
        $sevenDaysAgo = strtotime('-7 days');
        $entries7d = array_values(array_filter($entries, function ($e) use ($sevenDaysAgo) {
            $timestamp = strtotime($e['date'] ?? '');
            return $timestamp !== false && $timestamp >= $sevenDaysAgo;
        }));

        $moodCounts = [];
        $moistureValues = [];
        $washDays = 0;
        foreach ($entries7d as $entry) {
            $mood = $entry['mood'] ?? '';
            if ($mood !== '') {
                $moodCounts[$mood] = ($moodCounts[$mood] ?? 0) + 1;
            }
            if (!empty($entry['moistureLevel'])) {
                $moistureValues[] = (int) $entry['moistureLevel'];
            }
            if (($entry['entryType'] ?? '') === 'wash_day') {
                $washDays++;
            }
        }

        $topGoal = $goals[0] ?? null;

        return [
            'hair_type' => $profile->hairType ?: 'unspecified',
            'porosity' => $profile->porosity ?: 'unspecified',
            'top_concerns' => array_slice($profile->concerns, 0, 3),
            'top_goal' => $topGoal['title'] ?? null,
            'top_goal_progress_percent' => $topGoal ? (int) ($topGoal['progress'] ?? 0) : null,
            'journal_entries_last_7_days' => count($entries7d),
            'mood_counts_last_7_days' => $moodCounts,
            'avg_moisture_level_last_7_days' => !empty($moistureValues) ? round(array_sum($moistureValues) / count($moistureValues), 1) : null,
            'wash_days_last_7_days' => $washDays,
            'todays_routine_completion_percent' => $checklist['completionPercent'] ?? 0,
            'current_streak_days' => $streakDays,
            'day_count_on_journey' => $dayCount,
        ];
    }
}
