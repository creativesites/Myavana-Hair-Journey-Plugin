<?php
/**
 * Profile Entity Model
 *
 * @package Myavana\Next\Domain\Profile
 */

namespace Myavana\Next\Domain\Profile;

if (!defined('ABSPATH')) {
    exit;
}

class ProfileEntity {
    public int $userId = 0;
    public string $username = '';
    public string $displayName = '';
    public string $userEmail = '';
    public string $avatarUrl = '';
    public string $bio = '';
    public string $location = '';
    public string $website = '';

    // Hair Characteristics
    public string $hairType = '';
    public string $porosity = '';
    public string $density = '';
    public string $length = '';
    public string $hairJourneyStage = '';
    public int $hairHealthRating = 0;
    public string $lifeJourneyStage = '';
    public array $concerns = [];
    public array $goals = [];

    // Privacy & Settings
    public string $profileVisibility = 'public';
    public bool $showActivityStatus = true;
    public bool $emailNotifications = true;
    public bool $communityNotifications = true;
    public string $measurementUnit = 'in'; // 'in' | 'cm'

    // Profile Completion
    public int $completionPercentage = 0;
    public array $missingFields = [];

    /**
     * Convert to array for REST API responses
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'userId' => $this->userId,
            'username' => $this->username,
            'displayName' => $this->displayName,
            'userEmail' => $this->userEmail,
            'avatarUrl' => $this->avatarUrl,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'hairType' => $this->hairType,
            'porosity' => $this->porosity,
            'density' => $this->density,
            'length' => $this->length,
            'hairJourneyStage' => $this->hairJourneyStage,
            'hairHealthRating' => $this->hairHealthRating,
            'lifeJourneyStage' => $this->lifeJourneyStage,
            'concerns' => $this->concerns,
            'goals' => $this->goals,
            'profileVisibility' => $this->profileVisibility,
            'showActivityStatus' => $this->showActivityStatus,
            'emailNotifications' => $this->emailNotifications,
            'communityNotifications' => $this->communityNotifications,
            'measurementUnit' => $this->measurementUnit,
            'completionPercentage' => $this->completionPercentage,
            'missingFields' => $this->missingFields,
        ];
    }
}
