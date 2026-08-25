<?php
/**
 * Smart Entry Service - Coordinates two-speed entry flows (Quick vs Deep Wash-day)
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Rewards\RewardService;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Goals\GoalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SmartEntryService {
    private JournalRepository $journalRepo;
    private RewardService $rewardService;
    private RoutineRepository $routineRepo;
    private GoalRepository $goalRepo;

    public function __construct(
        ?JournalRepository $journalRepo = null,
        ?RewardService $rewardService = null,
        ?RoutineRepository $routineRepo = null,
        ?GoalRepository $goalRepo = null
    ) {
        $this->journalRepo = $journalRepo ?: new JournalRepository();
        $this->rewardService = $rewardService ?: new RewardService();
        $this->routineRepo = $routineRepo ?: new RoutineRepository();
        $this->goalRepo = $goalRepo ?: new GoalRepository();
    }

    /**
     * Submit Smart Entry (Quick check-in or Deep wash-day log)
     *
     * @param int $userId
     * @param array $entryData
     * @return array|\WP_Error
     */
    public function submitEntry(int $userId, array $entryData) {
        $entryType = sanitize_key($entryData['entryType'] ?? 'quick_checkin');

        // Create the journal post record
        $entry = $this->journalRepo->create($userId, $entryData);
        if (is_wp_error($entry)) {
            return $entry;
        }

        // Award gamification points based on speed/mode
        if ($entryType === 'wash_day') {
            $stats = $this->rewardService->awardWashDayLog($userId);
        } else {
            $stats = $this->rewardService->awardQuickCheckin($userId);
        }

        // Move the linked goal's progress, if this entry was tagged to one.
        $updatedGoal = null;
        $goalId = sanitize_text_field($entryData['goalId'] ?? '');
        if ($goalId !== '') {
            $updatedGoal = $this->goalRepo->applyEntryProgress($userId, $goalId, $entry->hairLength);
        }

        return [
            'entry' => $entry->toArray(),
            'stats' => $stats,
            'pointsAwarded' => ($entryType === 'wash_day' ? 25 : 10),
            'message' => ($entryType === 'wash_day' ? __('Wash day routine logged! +25 Points awarded.', 'myavana-hair-journey-next') : __('Quick check-in recorded! +10 Points awarded.', 'myavana-hair-journey-next')),
            'updatedGoal' => $updatedGoal,
        ];
    }
}
