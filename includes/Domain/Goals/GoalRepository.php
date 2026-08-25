<?php
/**
 * Goal Repository - CRUD for hair goals (Real user data only)
 *
 * @package Myavana\Next\Domain\Goals
 */

namespace Myavana\Next\Domain\Goals;

if (!defined('ABSPATH')) {
    exit;
}

class GoalRepository {
    /**
     * Get all structured goals for a user
     *
     * @param int $userId
     * @return array
     */
    public function getGoals(int $userId): array {
        $goals = get_user_meta($userId, 'myavana_hair_goals_structured', true);

        if (!is_array($goals)) {
            return [];
        }

        return array_values($goals);
    }

    /**
     * Save goals array for user
     *
     * @param int $userId
     * @param array $goals
     * @return bool
     */
    public function saveGoals(int $userId, array $goals): bool {
        global $wpdb;
        $saved = update_user_meta($userId, 'myavana_hair_goals_structured', array_values($goals));

        // Also sync to wp_myavana_profiles table if available
        $table = $wpdb->prefix . 'myavana_profiles';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->update(
                $table,
                ['hair_goals' => wp_json_encode(array_values($goals))],
                ['user_id' => $userId]
            );
        }

        return (bool) $saved;
    }

    /**
     * Add a new goal
     *
     * @param int $userId
     * @param array $goalData
     * @return array Updated goals
     */
    public function addGoal(int $userId, array $goalData): array {
        $goals = $this->getGoals($userId);
        $newGoal = [
            'id' => 'goal_' . wp_generate_password(8, false),
            'title' => sanitize_text_field($goalData['title'] ?? __('My Hair Goal', 'myavana-hair-journey-next')),
            'category' => sanitize_text_field($goalData['category'] ?? 'Length Retention'),
            'description' => sanitize_textarea_field($goalData['description'] ?? ''),
            'target_date' => sanitize_text_field($goalData['target_date'] ?? date('Y-m-d', strtotime('+3 months'))),
            'status' => 'active',
            'progress' => max(0, min(100, (int) ($goalData['progress'] ?? 0))),
            'created_at' => current_time('mysql'),
            // Optional — only set for length/growth goals. A goal without
            // target_length falls back to a flat per-entry nudge instead of
            // measured progress (see applyEntryProgress()).
            'target_length' => isset($goalData['target_length']) && $goalData['target_length'] !== ''
                ? (float) $goalData['target_length'] : null,
            'start_length' => isset($goalData['start_length']) && $goalData['start_length'] !== ''
                ? (float) $goalData['start_length'] : null,
        ];

        $goals[] = $newGoal;
        $this->saveGoals($userId, $goals);
        return $goals;
    }

    /**
     * Update an existing goal
     *
     * @param int $userId
     * @param string $goalId
     * @param array $updates
     * @return array Updated goals
     */
    public function updateGoal(int $userId, string $goalId, array $updates): array {
        $goals = $this->getGoals($userId);
        foreach ($goals as &$goal) {
            if (($goal['id'] ?? '') === $goalId || ($goal['goal_key'] ?? '') === $goalId) {
                if (isset($updates['title'])) $goal['title'] = sanitize_text_field($updates['title']);
                if (isset($updates['category'])) $goal['category'] = sanitize_text_field($updates['category']);
                if (isset($updates['description'])) $goal['description'] = sanitize_textarea_field($updates['description']);
                if (isset($updates['target_date'])) $goal['target_date'] = sanitize_text_field($updates['target_date']);
                if (isset($updates['status'])) $goal['status'] = sanitize_key($updates['status']);
                if (isset($updates['progress'])) $goal['progress'] = max(0, min(100, (int) $updates['progress']));
                if (array_key_exists('target_length', $updates)) {
                    $goal['target_length'] = $updates['target_length'] !== '' && $updates['target_length'] !== null
                        ? (float) $updates['target_length'] : null;
                }
                if (array_key_exists('start_length', $updates)) {
                    $goal['start_length'] = $updates['start_length'] !== '' && $updates['start_length'] !== null
                        ? (float) $updates['start_length'] : null;
                }
                break;
            }
        }

        $this->saveGoals($userId, $goals);
        return $goals;
    }

    /**
     * Look up a single goal by id.
     *
     * @param int $userId
     * @param string $goalId
     * @return array|null
     */
    public function getById(int $userId, string $goalId): ?array {
        foreach ($this->getGoals($userId) as $goal) {
            if (($goal['id'] ?? '') === $goalId || ($goal['goal_key'] ?? '') === $goalId) {
                return $goal;
            }
        }
        return null;
    }

    /**
     * Move a goal's progress after a journal entry links to it.
     *
     * If the goal has target_length and start_length set, progress is
     * measured directly from the entry's hair length: how far the member
     * has moved from start_length toward target_length. Every other case
     * (no measurable target, or an entry with no length reading) instead
     * nudges progress by a flat +10, capped at 100 — so linking an entry to
     * a goal always does something, even without a numeric target.
     *
     * @param int $userId
     * @param string $goalId
     * @param float|null $hairLength The linked entry's hair_length, if any.
     * @return array|null The updated goal, or null if goalId didn't match anything.
     */
    public function applyEntryProgress(int $userId, string $goalId, ?float $hairLength): ?array {
        $goal = $this->getById($userId, $goalId);
        if (!$goal) {
            return null;
        }

        $targetLength = $goal['target_length'] ?? null;
        $startLength = $goal['start_length'] ?? null;

        if ($hairLength !== null && $targetLength !== null && $startLength !== null && $targetLength !== $startLength) {
            $progress = ($hairLength - $startLength) / ($targetLength - $startLength) * 100;
        } else {
            $progress = (int) ($goal['progress'] ?? 0) + 10;
        }

        $this->updateGoal($userId, $goalId, ['progress' => (int) round($progress)]);

        return $this->getById($userId, $goalId);
    }

    /**
     * Delete goal
     *
     * @param int $userId
     * @param string $goalId
     * @return array
     */
    public function deleteGoal(int $userId, string $goalId): array {
        $goals = $this->getGoals($userId);
        $goals = array_values(array_filter($goals, fn($g) => ($g['id'] ?? '') !== $goalId && ($g['goal_key'] ?? '') !== $goalId));
        $this->saveGoals($userId, $goals);
        return $goals;
    }
}
