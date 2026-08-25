<?php
/**
 * Routine Repository - CRUD for hair routines (Real user data only)
 *
 * @package Myavana\Next\Domain\Routine
 */

namespace Myavana\Next\Domain\Routine;

if (!defined('ABSPATH')) {
    exit;
}

class RoutineRepository {
    /**
     * Get all routines for a user
     *
     * @param int $userId
     * @return array
     */
    public function getRoutines(int $userId): array {
        $routines = get_user_meta($userId, 'myavana_current_routine', true);

        if (!is_array($routines)) {
            return [];
        }

        return array_values(array_map(function ($routine, $index) {
            if (!is_array($routine)) {
                $routine = [];
            }

            $rawSteps = $routine['steps'] ?? ($routine['routine_steps'] ?? []);
            if (is_string($rawSteps)) {
                $rawSteps = preg_split('/\r\n|\r|\n|,/', $rawSteps);
            }
            $steps = [];
            foreach ((array) $rawSteps as $stepIndex => $step) {
                if (is_string($step)) {
                    $name = trim($step);
                    if ($name === '') {
                        continue;
                    }
                    $steps[] = ['id' => 'legacy_' . $index . '_' . $stepIndex, 'name' => $name, 'duration' => '', 'products' => ''];
                } elseif (is_array($step) && !empty($step['name'])) {
                    $steps[] = [
                        'id' => sanitize_key($step['id'] ?? ('legacy_' . $index . '_' . $stepIndex)),
                        'name' => sanitize_text_field($step['name']),
                        'duration' => sanitize_text_field($step['duration'] ?? ''),
                        'products' => sanitize_text_field($step['products'] ?? ''),
                    ];
                }
            }

            $routine['id'] = sanitize_key($routine['id'] ?? ('legacy_routine_' . $index));
            $routine['title'] = sanitize_text_field($routine['title'] ?? ($routine['routine_title'] ?? ($routine['name'] ?? __('My Hair Routine', 'myavana-hair-journey-next'))));
            $routine['category'] = sanitize_text_field($routine['category'] ?? ($routine['routine_type'] ?? 'Hair Care'));
            $routine['frequency'] = sanitize_text_field($routine['frequency'] ?? ($routine['routine_frequency'] ?? 'As needed'));
            $routine['description'] = sanitize_textarea_field($routine['description'] ?? ($routine['notes'] ?? ''));
            $routine['steps'] = $steps;
            $routine['products'] = $routine['products'] ?? [];
            return $routine;
        }, $routines, array_keys($routines)));
    }

    /**
     * Save all routines for user
     *
     * @param int $userId
     * @param array $routines
     * @return bool
     */
    public function saveRoutines(int $userId, array $routines): bool {
        return (bool) update_user_meta($userId, 'myavana_current_routine', array_values($routines));
    }

    /**
     * Add a single routine
     *
     * @param int $userId
     * @param array $routineData
     * @return array Updated list of routines
     */
    public function addRoutine(int $userId, array $routineData): array {
        $routines = $this->getRoutines($userId);
        $steps = [];

        if (!empty($routineData['steps']) && is_array($routineData['steps'])) {
            foreach ($routineData['steps'] as $idx => $step) {
                if (is_string($step) && !empty(trim($step))) {
                    $steps[] = [
                        'id' => 'step_' . ($idx + 1) . '_' . wp_generate_password(4, false),
                        'name' => sanitize_text_field($step),
                        'duration' => '5 mins',
                        'products' => '',
                    ];
                } elseif (is_array($step) && !empty($step['name'])) {
                    $steps[] = [
                        'id' => sanitize_key($step['id'] ?? ('step_' . ($idx + 1) . '_' . wp_generate_password(4, false))),
                        'name' => sanitize_text_field($step['name']),
                        'duration' => sanitize_text_field($step['duration'] ?? '5 mins'),
                        'products' => sanitize_text_field($step['products'] ?? ''),
                    ];
                }
            }
        }

        $newRoutine = [
            'id' => 'routine_' . wp_generate_password(8, false),
            'title' => sanitize_text_field($routineData['title'] ?? __('My Hair Routine', 'myavana-hair-journey-next')),
            'category' => sanitize_text_field($routineData['category'] ?? 'Wash Day'),
            'frequency' => sanitize_text_field($routineData['frequency'] ?? 'Weekly'),
            'description' => sanitize_textarea_field($routineData['description'] ?? ''),
            'products' => !empty($routineData['products']) ? (is_array($routineData['products']) ? array_map('sanitize_text_field', $routineData['products']) : array_filter(array_map('trim', explode(',', sanitize_text_field($routineData['products']))))) : [],
            'steps' => $steps,
            'created_at' => current_time('mysql'),
        ];

        $routines[] = $newRoutine;
        $this->saveRoutines($userId, $routines);
        return $routines;
    }

    /**
     * Delete a routine
     *
     * @param int $userId
     * @param string $routineId
     * @return array
     */
    public function deleteRoutine(int $userId, string $routineId): array {
        $routines = $this->getRoutines($userId);
        $routines = array_values(array_filter($routines, fn($r) => ($r['id'] ?? '') !== $routineId));
        $this->saveRoutines($userId, $routines);
        return $routines;
    }

    /**
     * Get completion log for a specific date (Y-m-d)
     *
     * @param int $userId
     * @param string $date Y-m-d format
     * @return array Array of completed step IDs
     */
    public function getDailyCompletions(int $userId, string $date): array {
        $key = 'myavana_routine_completions_' . $date;
        $completions = get_user_meta($userId, $key, true);
        if (is_array($completions)) {
            return $completions;
        }

        // Compatibility with the original plugin's date-keyed completion map.
        $legacyMap = get_user_meta($userId, 'myavana_routine_completions', true);
        return is_array($legacyMap) && is_array($legacyMap[$date] ?? null) ? $legacyMap[$date] : [];
    }

    /**
     * Toggle completion for a routine step on a given date
     *
     * @param int $userId
     * @param string $stepId
     * @param string $date
     * @return array Updated list of completions
     */
    public function toggleCompletion(int $userId, string $stepId, string $date): array {
        $key = 'myavana_routine_completions_' . $date;
        $completions = $this->getDailyCompletions($userId, $date);

        if (in_array($stepId, $completions, true)) {
            $completions = array_values(array_diff($completions, [$stepId]));
        } else {
            $completions[] = $stepId;
        }

        update_user_meta($userId, $key, $completions);
        $legacyMap = get_user_meta($userId, 'myavana_routine_completions', true);
        $legacyMap = is_array($legacyMap) ? $legacyMap : [];
        $legacyMap[$date] = $completions;
        update_user_meta($userId, 'myavana_routine_completions', $legacyMap);
        return $completions;
    }
}
