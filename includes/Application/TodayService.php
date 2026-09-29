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
use Myavana\Next\Domain\Intelligence\ContextBuilders\TodayInsightContextBuilder;
use Myavana\Next\Domain\Rewards\GamificationRepository;

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
        $recentEntries = array_slice($entries, 0, 3);

        $hasRoutineSteps = !empty($checklist['items']) && count($checklist['items']) > 0;
        $firstEntry = !empty($entries) ? end($entries) : null;
        $dayCount = $firstEntry ? max(1, (int) round((time() - strtotime($firstEntry['date'])) / DAY_IN_SECONDS) + 1) : 1;
        $goals = $this->goalRepo->getGoals($userId);
        $streakDays = (int) ((new GamificationRepository())->getStats($userId)['currentStreak'] ?? 0);

        // The richer AI insight is requested independently by the client. The
        // Today hub itself must always be fast and reliable, so it returns the
        // fact-based fallback rather than calling a removed legacy AI method.
        $insightContext = TodayInsightContextBuilder::build(
            $profile,
            $entries,
            $checklist,
            $goals,
            $streakDays,
            $dayCount
        );
        $rhythm = (new HabitService())->weeklyRhythm($entries);
        if (is_array($insightContext)) {
            $insightContext['weeks_in_a_row'] = $rhythm['weeksInARow'];
        }

        return [
            'greeting' => $this->getGreeting($this->preferredName($userId, $profile->displayName)),
            'greetingSubtext' => __('Let\'s take care of your hair today.', 'myavana-hair-journey-next'),
            'dayCount' => $dayCount,
            'user' => $profile->toArray(),
            'hasRoutineSteps' => $hasRoutineSteps,
            'checklist' => $checklist,
            'latestEntry' => $latestEntry,
            'recentEntries' => $recentEntries,
            'insight' => $this->insightEngine->generateFallbackInsight($insightContext),
            'routineProducts' => array_slice($this->routineService->getProductCabinet($userId), 0, 3),
            'week' => $this->buildWeekStrip($entries),
            'goals' => array_slice($this->distinctActiveGoals($goals), 0, 3),
            'upcomingGoals' => $this->buildUpcomingGoals($goals),
            'memory' => $this->findMemory($userId, $entries),
            'focus' => $focus = $this->getGoalFocus($userId),
            'focusSource' => $focus ? 'signup' : 'goals',
            'focusGoals' => $focus ? [] : $this->activeGoalTitles($goals),
            'rhythm' => $rhythm,
            'weeklyPrompt' => WeeklyPromptService::current(),
            'recapOffer' => (new RecapService())->offerForToday($userId),
            'firstWeek' => (new HabitService())->firstWeek($userId, $entries, $goals),
        ];
    }

    /**
     * First name when she has one; otherwise the display name, unless that is
     * just her login handle (many accounts have display_name = user_login).
     */
    private function preferredName(int $userId, string $displayName): string {
        return \Myavana\Next\Core\MemberName::forUser($userId, false);
    }

    /**
     * Distinct titles of her active goals (at most two), for the hero line
     * when she hasn't picked focus goals at signup.
     *
     * @return string[]
     */
    private function activeGoalTitles(array $goals): array {
        $titles = [];
        foreach ($goals as $goal) {
            $title = trim((string) ($goal['title'] ?? ($goal['goal_title'] ?? '')));
            if ($title === '' || ($goal['status'] ?? 'active') === 'completed' || (int) ($goal['progress'] ?? 0) >= 100) {
                continue;
            }
            $titles[strtolower($title)] = $title;
        }
        return array_slice(array_values($titles), 0, 2);
    }

    /**
     * The hair goals the member chose in the welcome pop-up, as display
     * labels, so Today can speak to what she's actually working on.
     *
     * @param int $userId
     * @return string[]
     */
    private function getGoalFocus(int $userId): array {
        $labels = [
            'growth' => __('hair growth', 'myavana-hair-journey-next'),
            'moisture' => __('more moisture', 'myavana-hair-journey-next'),
            'strength' => __('stronger hair', 'myavana-hair-journey-next'),
            'damage' => __('repairing damage', 'myavana-hair-journey-next'),
            'definition' => __('better definition', 'myavana-hair-journey-next'),
            'frizz' => __('less frizz', 'myavana-hair-journey-next'),
            'shine' => __('more shine', 'myavana-hair-journey-next'),
            'volume' => __('more volume', 'myavana-hair-journey-next'),
        ];
        $keys = array_filter(array_map('trim', explode(',', (string) get_user_meta($userId, 'myavana_hair_goals', true))));
        $focus = [];
        foreach ($keys as $key) {
            if (isset($labels[$key])) {
                $focus[] = $labels[$key];
            }
        }
        return array_slice($focus, 0, 3);
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
    /**
     * Active goals, one per title: members who double-submitted the goal form
     * have several identical goals, and repeating them reads as a glitch.
     */
    private function distinctActiveGoals(array $goals): array {
        $seen = [];
        $out = [];
        foreach ($goals as $g) {
            $title = trim((string) ($g['title'] ?? $g['goal_title'] ?? ''));
            $key = strtolower($title);
            if ($title === '' || isset($seen[$key]) || ($g['status'] ?? 'active') === 'completed') {
                continue;
            }
            $seen[$key] = true;
            $g['title'] = $title;
            $out[] = $g;
        }
        return $out;
    }

    /** Goals whose target date is still ahead, soonest first. */
    private function buildUpcomingGoals(array $goals): array {
        $today = strtotime(current_time('Y-m-d'));
        $goals = array_filter($this->distinctActiveGoals($goals), function ($g) use ($today) {
            $target = !empty($g['target_date']) ? strtotime((string) $g['target_date']) : false;
            return $target !== false && $target >= $today;
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
