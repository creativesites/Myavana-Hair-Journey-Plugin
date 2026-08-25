<?php
/**
 * Routine Service - High-level business logic for routines, checklist, and product cabinet
 *
 * @package Myavana\Next\Domain\Routine
 */

namespace Myavana\Next\Domain\Routine;

if (!defined('ABSPATH')) {
    exit;
}

class RoutineService {
    private RoutineRepository $repository;

    public function __construct(?RoutineRepository $repository = null) {
        $this->repository = $repository ?: new RoutineRepository();
    }

    /**
     * Get today's actionable routine checklist
     *
     * @param int $userId
     * @param string|null $date
     * @return array
     */
    public function getTodayChecklist(int $userId, ?string $date = null): array {
        $date = $date ?: current_time('Y-m-d');
        $routines = $this->repository->getRoutines($userId);
        $completedStepIds = $this->repository->getDailyCompletions($userId, $date);

        $checklist = [];
        foreach ($routines as $routine) {
            $steps = $routine['steps'] ?? [];
            foreach ($steps as $step) {
                $stepId = $step['id'] ?? sanitize_title($step['name'] ?? 'step');
                $isDone = in_array($stepId, $completedStepIds, true);
                $checklist[] = [
                    'id' => $stepId,
                    'routineTitle' => $routine['title'] ?? 'Routine',
                    'category' => $routine['category'] ?? 'General',
                    'name' => $step['name'] ?? '',
                    'duration' => $step['duration'] ?? '5 min',
                    'products' => $step['products'] ?? '',
                    'isCompleted' => $isDone,
                ];
            }
        }

        $total = count($checklist);
        $completedCount = count(array_filter($checklist, fn($item) => $item['isCompleted']));
        $percent = $total > 0 ? (int) round(($completedCount / $total) * 100) : 0;

        return [
            'date' => $date,
            'items' => $checklist,
            'completedCount' => $completedCount,
            'totalCount' => $total,
            'completionPercent' => $percent,
        ];
    }

    /**
     * Get product cabinet from all routines
     *
     * @param int $userId
     * @return array
     */
    public function getProductCabinet(int $userId): array {
        $routines = $this->repository->getRoutines($userId);
        $products = [];
        $seen = [];

        foreach ($routines as $routine) {
            $category = $routine['category'] ?? 'General Care';
            $prodList = $routine['products'] ?? [];
            if (is_string($prodList)) {
                $prodList = explode(',', $prodList);
            }

            foreach ($prodList as $p) {
                $p = trim($p);
                if (!empty($p) && !isset($seen[$p])) {
                    $seen[$p] = true;
                    $products[] = [
                        'name' => $p,
                        'category' => $category,
                        'routine' => $routine['title'] ?? '',
                        'status' => 'In Routine',
                    ];
                }
            }
        }

        return $products;
    }
}
