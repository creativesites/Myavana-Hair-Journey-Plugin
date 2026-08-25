<?php
/**
 * Goal Service - Progress & milestone calculations
 *
 * @package Myavana\Next\Domain\Goals
 */

namespace Myavana\Next\Domain\Goals;

if (!defined('ABSPATH')) {
    exit;
}

class GoalService {
    private GoalRepository $repository;

    public function __construct(?GoalRepository $repository = null) {
        $this->repository = $repository ?: new GoalRepository();
    }

    /**
     * Get goals overview with active and completed breakdown
     *
     * @param int $userId
     * @return array
     */
    public function getOverview(int $userId): array {
        $allGoals = $this->repository->getGoals($userId);

        $active = [];
        $completed = [];

        foreach ($allGoals as $goal) {
            $progress = (int) ($goal['progress'] ?? 0);
            $status = $goal['status'] ?? 'active';

            if ($progress >= 100 || $status === 'completed') {
                $goal['status'] = 'completed';
                $completed[] = $goal;
            } else {
                $active[] = $goal;
            }
        }

        return [
            'active' => $active,
            'completed' => $completed,
            'totalActive' => count($active),
            'totalCompleted' => count($completed),
        ];
    }
}
