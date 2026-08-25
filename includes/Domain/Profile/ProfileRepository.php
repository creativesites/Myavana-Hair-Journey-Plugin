<?php
/**
 * Profile Repository - Reads & writes real hair profile data
 *
 * @package Myavana\Next\Domain\Profile
 */

namespace Myavana\Next\Domain\Profile;

if (!defined('ABSPATH')) {
    exit;
}

class ProfileRepository {
    /**
     * Get profile for user
     *
     * @param int $userId
     * @return ProfileEntity
     */
    public function getByUserId(int $userId): ProfileEntity {
        global $wpdb;
        $entity = new ProfileEntity();
        $entity->userId = $userId;

        $user = get_userdata($userId);
        if ($user) {
            $entity->username = $user->user_login;
            $entity->displayName = $user->display_name ?: $user->user_login;
            $entity->userEmail = $user->user_email;
            $entity->website = $user->user_url ?: (string) get_user_meta($userId, 'myavana_website', true);
            $entity->bio = $user->description ?: (string) get_user_meta($userId, 'myavana_bio', true);
        }

        // Check custom avatar first, then default
        $customAvatar = get_user_meta($userId, 'myavana_custom_avatar_url', true);
        if (!empty($customAvatar)) {
            $entity->avatarUrl = (string) $customAvatar;
        } else {
            $entity->avatarUrl = get_avatar_url($userId, ['size' => 180]);
        }

        // Read Location
        $entity->location = (string) (get_user_meta($userId, 'myavana_location', true) ?: get_user_meta($userId, 'location', true) ?: '');

        // Try reading from custom table wp_myavana_profiles
        $table = $wpdb->prefix . 'myavana_profiles';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d LIMIT 1", $userId));
            if ($row) {
                $entity->hairType = (string) ($row->hair_type ?? '');
                $entity->hairJourneyStage = (string) ($row->hair_journey_stage ?? '');
                $entity->hairHealthRating = (int) ($row->hair_health_rating ?? 0);
                $entity->lifeJourneyStage = (string) ($row->life_journey_stage ?? '');
                if (!empty($row->location) && empty($entity->location)) {
                    $entity->location = (string) $row->location;
                }
                if (!empty($row->hair_goals)) {
                    $decoded = json_decode($row->hair_goals, true);
                    if (is_array($decoded)) {
                        $entity->goals = $decoded;
                    }
                }
            }
        }

        // Fallback / supplement with usermeta
        if (empty($entity->hairType)) {
            $entity->hairType = (string) (get_user_meta($userId, 'myavana_hair_type', true) ?: get_user_meta($userId, 'hair_type', true) ?: '');
        }

        if (empty($entity->porosity)) {
            $entity->porosity = (string) (get_user_meta($userId, 'hair_porosity', true) ?: get_user_meta($userId, 'myavana_hair_porosity', true) ?: '');
        }

        if (empty($entity->density)) {
            $entity->density = (string) (get_user_meta($userId, 'hair_density', true) ?: get_user_meta($userId, 'myavana_hair_density', true) ?: '');
        }

        if (empty($entity->length)) {
            $entity->length = (string) (get_user_meta($userId, 'myavana_hair_length', true) ?: get_user_meta($userId, 'hair_length', true) ?: '');
        }

        $concerns = get_user_meta($userId, 'myavana_hair_concerns', true);
        if (is_array($concerns)) {
            $entity->concerns = array_values(array_filter($concerns));
        } elseif (!empty($concerns)) {
            $entity->concerns = [(string) $concerns];
        }

        if (empty($entity->goals)) {
            $structuredGoals = get_user_meta($userId, 'myavana_hair_goals_structured', true);
            if (is_array($structuredGoals)) {
                $entity->goals = $structuredGoals;
            }
        }

        // Settings
        $vis = get_user_meta($userId, 'myavana_profile_visibility', true);
        $entity->profileVisibility = !empty($vis) ? (string) $vis : 'public';
        $entity->showActivityStatus = get_user_meta($userId, 'myavana_show_activity_status', true) !== '0';
        $entity->emailNotifications = get_user_meta($userId, 'myavana_email_notifications', true) !== '0';
        $entity->communityNotifications = get_user_meta($userId, 'myavana_community_notifications', true) !== '0';
        $unit = get_user_meta($userId, 'myavana_measurement_unit', true);
        $entity->measurementUnit = $unit === 'cm' ? 'cm' : 'in';

        // Calculate Completion Score
        $this->calculateCompletion($entity);

        return $entity;
    }

    /**
     * Save/Update profile entity for user
     *
     * @param int $userId
     * @param array $data
     * @return ProfileEntity
     */
    public function save(int $userId, array $data): ProfileEntity {
        global $wpdb;

        // Existing values, so a partial save (e.g. the Preferences form,
        // which only sends notification toggles) doesn't blank out fields
        // it never touched in the wp_myavana_profiles table below.
        $current = $this->getByUserId($userId);

        // Basic Info
        if (isset($data['displayName']) && !empty($data['displayName'])) {
            $displayName = sanitize_text_field($data['displayName']);
            wp_update_user([
                'ID' => $userId,
                'display_name' => $displayName,
            ]);
        }

        if (isset($data['bio'])) {
            $bio = sanitize_textarea_field($data['bio']);
            wp_update_user(['ID' => $userId, 'description' => $bio]);
            update_user_meta($userId, 'myavana_bio', $bio);
        }

        if (isset($data['location'])) {
            $location = sanitize_text_field($data['location']);
            update_user_meta($userId, 'myavana_location', $location);
            update_user_meta($userId, 'location', $location);
        }

        if (isset($data['website'])) {
            $website = esc_url_raw($data['website']);
            wp_update_user(['ID' => $userId, 'user_url' => $website]);
            update_user_meta($userId, 'myavana_website', $website);
        }

        if (isset($data['avatarUrl'])) {
            update_user_meta($userId, 'myavana_custom_avatar_url', esc_url_raw($data['avatarUrl']));
        }

        // Hair Characteristics. These are single-select dropdowns with a
        // "pick one" placeholder as their empty state — an empty submitted
        // value never represents an intentional choice (and can happen
        // simply because a legacy free-text value like "Normal" or
        // "Shoulder Length" doesn't match any option, leaving the select
        // blank), so treat blank the same as "field not sent" rather than
        // letting it silently overwrite real data.
        $hairType = !empty($data['hairType'] ?? '') ? sanitize_text_field($data['hairType']) : $current->hairType;
        $porosity = !empty($data['porosity'] ?? '') ? sanitize_text_field($data['porosity']) : $current->porosity;
        $density = !empty($data['density'] ?? '') ? sanitize_text_field($data['density']) : $current->density;
        $length = !empty($data['length'] ?? '') ? sanitize_text_field($data['length']) : $current->length;
        $stage = isset($data['hairJourneyStage']) ? sanitize_text_field($data['hairJourneyStage']) : $current->hairJourneyStage;
        $healthRating = isset($data['hairHealthRating']) ? max(0, min(10, (int) $data['hairHealthRating'])) : $current->hairHealthRating;
        $locationForTable = isset($data['location']) ? sanitize_text_field($data['location']) : $current->location;

        if (!empty($data['hairType'] ?? '')) {
            update_user_meta($userId, 'myavana_hair_type', $hairType);
            update_user_meta($userId, 'hair_type', $hairType);
        }
        if (!empty($data['porosity'] ?? '')) {
            update_user_meta($userId, 'hair_porosity', $porosity);
            update_user_meta($userId, 'myavana_hair_porosity', $porosity);
        }
        if (!empty($data['density'] ?? '')) {
            update_user_meta($userId, 'hair_density', $density);
            update_user_meta($userId, 'myavana_hair_density', $density);
        }
        if (!empty($data['length'] ?? '')) {
            update_user_meta($userId, 'myavana_hair_length', $length);
            update_user_meta($userId, 'hair_length', $length);
        }
        if (isset($data['concerns']) && is_array($data['concerns'])) {
            $concerns = array_map('sanitize_text_field', $data['concerns']);
            update_user_meta($userId, 'myavana_hair_concerns', $concerns);
        }
        if (isset($data['goals']) && is_array($data['goals'])) {
            $goals = array_map(function($g) {
                if (is_array($g)) {
                    return [
                        'goal' => sanitize_text_field($g['goal'] ?? ''),
                        'progress' => isset($g['progress']) ? max(0, min(100, (int)$g['progress'])) : 0,
                    ];
                }
                return ['goal' => sanitize_text_field((string)$g), 'progress' => 0];
            }, $data['goals']);
            update_user_meta($userId, 'myavana_hair_goals_structured', $goals);
        }

        // Privacy & Notification Settings
        if (isset($data['profileVisibility'])) {
            update_user_meta($userId, 'myavana_profile_visibility', sanitize_text_field($data['profileVisibility']));
        }
        if (isset($data['showActivityStatus'])) {
            update_user_meta($userId, 'myavana_show_activity_status', !empty($data['showActivityStatus']) ? '1' : '0');
        }
        if (isset($data['emailNotifications'])) {
            update_user_meta($userId, 'myavana_email_notifications', !empty($data['emailNotifications']) ? '1' : '0');
        }
        if (isset($data['communityNotifications'])) {
            update_user_meta($userId, 'myavana_community_notifications', !empty($data['communityNotifications']) ? '1' : '0');
        }
        if (isset($data['measurementUnit'])) {
            update_user_meta($userId, 'myavana_measurement_unit', $data['measurementUnit'] === 'cm' ? 'cm' : 'in');
        }

        // Update custom table wp_myavana_profiles if it exists
        $table = $wpdb->prefix . 'myavana_profiles';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $userId));
            $rowValues = [
                'user_id' => $userId,
                'hair_type' => $hairType,
                'hair_journey_stage' => $stage,
                'hair_health_rating' => $healthRating,
                'location' => $locationForTable,
            ];

            if ($existing) {
                $wpdb->update($table, $rowValues, ['user_id' => $userId]);
            } else {
                $wpdb->insert($table, $rowValues);
            }
        }

        return $this->getByUserId($userId);
    }

    /**
     * Calculate profile completion score & missing fields
     */
    private function calculateCompletion(ProfileEntity $entity): void {
        $checklist = [
            'hairType' => ['weight' => 20, 'label' => 'Hair Type', 'value' => $entity->hairType],
            'porosity' => ['weight' => 15, 'label' => 'Porosity', 'value' => $entity->porosity],
            'density' => ['weight' => 15, 'label' => 'Density', 'value' => $entity->density],
            'length' => ['weight' => 15, 'label' => 'Length', 'value' => $entity->length],
            'concerns' => ['weight' => 15, 'label' => 'Hair Concerns', 'value' => !empty($entity->concerns)],
            'bio' => ['weight' => 10, 'label' => 'Bio', 'value' => $entity->bio],
            'location' => ['weight' => 10, 'label' => 'Location', 'value' => $entity->location],
        ];

        $score = 0;
        $missing = [];

        foreach ($checklist as $key => $item) {
            if (!empty($item['value'])) {
                $score += $item['weight'];
            } else {
                $missing[] = $item['label'];
            }
        }

        $entity->completionPercentage = min(100, $score);
        $entity->missingFields = $missing;
    }
}
