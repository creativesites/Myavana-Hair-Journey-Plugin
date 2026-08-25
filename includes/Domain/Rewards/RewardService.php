<?php
/**
 * Reward Service - Business logic for awarding points and streak progression
 *
 * @package Myavana\Next\Domain\Rewards
 */

namespace Myavana\Next\Domain\Rewards;

if (!defined('ABSPATH')) {
    exit;
}

class RewardService {
    private GamificationRepository $repository;

    public function __construct(?GamificationRepository $repository = null) {
        $this->repository = $repository ?: new GamificationRepository();
    }

    /**
     * Award points for quick check-in
     *
     * @param int $userId
     * @return array
     */
    public function awardQuickCheckin(int $userId): array {
        return $this->repository->recordCheckin($userId, 10, 'quick_checkin');
    }

    /**
     * Award points for completing full wash-day log
     *
     * @param int $userId
     * @return array
     */
    public function awardWashDayLog(int $userId): array {
        return $this->repository->recordCheckin($userId, 25, 'wash_day');
    }

    /**
     * Award points for routine step completion
     *
     * @param int $userId
     * @return array
     */
    public function awardRoutineStep(int $userId): array {
        return $this->repository->recordCheckin($userId, 5, 'routine_step');
    }

    /**
     * Get reward hub overview
     *
     * @param int $userId
     * @return array
     */
    public function getOverview(int $userId): array {
        return [
            'stats' => $this->repository->getStats($userId),
            'badges' => $this->repository->getBadges($userId),
        ];
    }
}
