<?php
/**
 * Today Service - Contextual aggregator for the Today Habit Hub
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Routine\RoutineService;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\AI\InsightEngine;

if (!defined('ABSPATH')) {
    exit;
}

class TodayService {
    private ProfileRepository $profileRepo;
    private JournalRepository $journalRepo;
    private RoutineService $routineService;
    private GoalRepository $goalRepo;
    private InsightEngine $insightEngine;

    public function __construct(
        ?ProfileRepository $profileRepo = null,
        ?JournalRepository $journalRepo = null,
        ?RoutineService $routineService = null,
        ?GoalRepository $goalRepo = null,
        ?InsightEngine $insightEngine = null
    ) {
        $this->profileRepo = $profileRepo ?: new ProfileRepository();
        $this->journalRepo = $journalRepo ?: new JournalRepository();
        $this->routineService = $routineService ?: new RoutineService();
        $this->goalRepo = $goalRepo ?: new GoalRepository();
        $this->insightEngine = $insightEngine ?: new InsightEngine();
    }

    /**
     * Get aggregated Today Hub state with low cognitive load
     *
     * @param int $userId
     * @return array
     */
    public function getTodayHubData(int $userId): array {
        $profile = $this->profileRepo->getByUserId($userId);
        $checklist = $this->routineService->getTodayChecklist($userId);
        $entriesResult = $this->journalRepo->getEntries($userId, ['perPage' => 60]);
        $entries = $entriesResult['items'];
        $latestEntry = $entries[0] ?? null;

        $hasRoutineSteps = !empty($checklist['items']) && count($checklist['items']) > 0;
        $firstEntry = !empty($entries) ? end($entries) : null;
        $dayCount = $firstEntry ? max(1, (int) round((time() - strtotime($firstEntry['date'])) / DAY_IN_SECONDS) + 1) : 1;

        return [
            'greeting' => $this->getGreeting($profile->displayName),
            'greetingSubtext' => __('Let\'s take care of your hair today.', 'myavana-hair-journey-next'),
            'dayCount' => $dayCount,
            'user' => $profile->toArray(),
            'hasRoutineSteps' => $hasRoutineSteps,
            'checklist' => $checklist,
            'latestEntry' => $latestEntry,
            'insight' => $this->insightEngine->generateDailyInsight($profile, array_slice($entries, 0, 5)),
            'routineProducts' => array_slice($this->routineService->getProductCabinet($userId), 0, 3),
            'week' => $this->buildWeekStrip($entries),
            'goals' => array_slice($this->goalRepo->getGoals($userId), 0, 3),
            'upcomingGoals' => $this->buildUpcomingGoals($userId),
            'memory' => $this->findMemory($userId, $entries),
        ];
    }

    /**
     * Friendly, personalized time-aware greeting
     *
     * @param string $name
     * @return string
     */
    private function getGreeting(string $name): string {
        $hour = (int) current_time('G');
        $timeOfDay = 'Good morning';
        if ($hour >= 12 && $hour < 17) {
            $timeOfDay = 'Good afternoon';
        } elseif ($hour >= 17) {
            $timeOfDay = 'Good evening';
        }

        $trimmed = trim($name);
        if (!empty($trimmed)) {
            $firstName = explode(' ', $trimmed)[0];
            return sprintf('%s, %s', $timeOfDay, esc_html($firstName));
        }

        return __('Welcome back', 'myavana-hair-journey-next');
    }

    /**
     * Last 7 days, marking which days had at least one journal entry.
     *
     * @param array $entries
     * @return array [{label, date, hasEntry, isToday}]
     */
    private function buildWeekStrip(array $entries): array {
        $activeDates = [];
        foreach ($entries as $e) {
            $activeDates[substr($e['date'], 0, 10)] = true;
        }

        $today = current_time('Y-m-d');
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $timestamp = strtotime("-{$i} days", strtotime($today));
            $dateStr = date('Y-m-d', $timestamp);
            $days[] = [
                'label' => date_i18n('D', $timestamp)[0],
                'date' => $dateStr,
                'hasEntry' => isset($activeDates[$dateStr]),
                'isToday' => $dateStr === $today,
            ];
        }

        return $days;
    }

    /**
     * Active goals with the nearest target dates, so "what's coming up" is
     * grounded in real goal data instead of a fabricated reminders feed.
     *
     * @param int $userId
     * @return array
     */
    private function buildUpcomingGoals(int $userId): array {
        $goals = array_filter($this->goalRepo->getGoals($userId), function ($g) {
            return !empty($g['target_date']) && ($g['status'] ?? 'active') !== 'completed';
        });

        usort($goals, fn($a, $b) => strtotime($a['target_date']) <=> strtotime($b['target_date']));

        return array_slice(array_values($goals), 0, 3);
    }

    /**
     * Find the journal entry closest to exactly one year ago, if any exists
     * within a two-week window either side.
     *
     * @param int $userId
     * @param array $recentEntries Already-fetched entries (avoids a second query when they cover it).
     * @return array|null
     */
    private function findMemory(int $userId, array $recentEntries): ?array {
        $targetTime = strtotime('-1 year');
        $windowSeconds = 14 * DAY_IN_SECONDS;

        $candidates = $recentEntries;
        $needsWiderQuery = empty($candidates) || strtotime(end($candidates)['date']) > ($targetTime + $windowSeconds);
        if ($needsWiderQuery) {
            // getEntries only supports page/perPage, not a date-range filter,
            // so pull a larger window instead of paginating deep — good
            // enough for finding a "one year ago" moment without a
            // dedicated date-range query.
            $wide = $this->journalRepo->getEntries($userId, ['perPage' => 200]);
            $candidates = $wide['items'];
        }

        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($candidates as $entry) {
            $diff = abs(strtotime($entry['date']) - $targetTime);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $entry;
            }
        }

        if (!$best || $bestDiff > $windowSeconds) {
            return null;
        }

        return $best;
    }
}
