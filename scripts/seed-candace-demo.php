<?php
/**
 * Seed Candace's Account (candyvharris / ID 5) with mature, realistic Hair Journey data
 * for tonight's executive demonstration.
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';

use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Rewards\GamificationRepository;

$user = get_user_by('login', 'candyvharris') ?: get_user_by('email', 'candace@myavana.com');
if (!$user) {
    echo "User candyvharris not found. Creating user..." . PHP_EOL;
    $userId = wp_create_user('candyvharris', 'CandaceDemo2026!', 'candace@myavana.com');
    $user = get_userdata($userId);
}

$userId = $user->ID;
echo "Seeding data for User {$userId} ({$user->user_login})..." . PHP_EOL;

// 1. Profile / HairID
$profileRepo = new ProfileRepository();
$profileRepo->save($userId, [
    'displayName' => 'Candace Mitchell Harris',
    'hairType' => '4A',
    'porosity' => 'Medium',
    'density' => 'Medium',
    'length' => '10.5',
    'concerns' => ['Moisture Retention', 'Length Retention', 'Scalp Care'],
    'hairJourneyStage' => 'Thriving & Growing',
    'hairHealthRating' => 8,
    'bio' => 'Founder & CEO of MYAVANA. Pioneering the science of personalized hair care.',
    'location' => 'Atlanta, GA',
    'profileVisibility' => 'public',
    'measurementUnit' => 'in',
]);
update_user_meta($userId, 'myavana_onboarding_completed', '1');
update_user_meta($userId, 'myavana_email_verified', 'yes');

// 2. Goals
$goalRepo = new GoalRepository();
$goals = [
    [
        'id' => 'goal_candace_length_1',
        'title' => 'Retain 4 Inches of Healthy Length',
        'category' => 'Length Retention',
        'description' => 'Targeting 14.5 inches by maintaining consistent moisture and protective night styling.',
        'target_date' => '2026-12-15',
        'status' => 'active',
        'progress' => 68,
        'start_length' => 10.5,
        'target_length' => 14.5,
        'created_at' => date('Y-m-d H:i:s', strtotime('-90 days')),
    ],
    [
        'id' => 'goal_candace_moisture_2',
        'title' => 'Deep Hydration & Moisture Balance',
        'category' => 'Moisture & Hydration',
        'description' => 'Consistently execute Sunday wash-day steaming and mid-week moisture resets.',
        'target_date' => '2026-10-30',
        'status' => 'active',
        'progress' => 80,
        'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
    ],
    [
        'id' => 'goal_candace_scalp_3',
        'title' => 'Scalp Circulation & Health',
        'category' => 'Scalp Care',
        'description' => 'Nightly rosemary scalp stimulation massage to encourage follicular strength.',
        'target_date' => '2026-11-20',
        'status' => 'active',
        'progress' => 45,
        'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
    ],
];
$goalRepo->saveGoals($userId, $goals);

// 3. Routines
$routineRepo = new RoutineRepository();
$routines = [
    [
        'id' => 'routine_candace_wash_day',
        'title' => 'Wash Day Hydration Protocol',
        'category' => 'Wash Day',
        'frequency' => 'Weekly',
        'description' => 'Weekly deep cleansing and thermal hydration infusion.',
        'products' => ['MYAVANA Clarifying Shampoo', 'MYAVANA Hydrating Mask', 'MYAVANA Botanical Leave-In', 'Jojoba & Baobab Oil Blend'],
        'steps' => [
            ['id' => 'step_wash_1', 'name' => 'Clarifying Cleanse with Warm Water', 'duration' => '8 min', 'products' => 'MYAVANA Clarifying Shampoo'],
            ['id' => 'step_wash_2', 'name' => 'Deep Conditioning Treatment under Steam Cap', 'duration' => '20 min', 'products' => 'MYAVANA Hydrating Mask'],
            ['id' => 'step_wash_3', 'name' => 'Leave-In Conditioner Detangle', 'duration' => '5 min', 'products' => 'MYAVANA Botanical Leave-In'],
            ['id' => 'step_wash_4', 'name' => 'L.O.C. Moisture Seal with Jojoba Oil', 'duration' => '5 min', 'products' => 'Jojoba & Baobab Oil Blend'],
        ],
    ],
    [
        'id' => 'routine_candace_daily_refresh',
        'title' => 'Daily Morning Moisture Refresh',
        'category' => 'Daily Refresh',
        'frequency' => 'Daily',
        'description' => 'Quick wake-up hydration mist and scalp stimulation.',
        'products' => ['Rosewater & Aloe Mist', 'Light Nourishing Milk'],
        'steps' => [
            ['id' => 'step_daily_1', 'name' => 'Hydrating Mist & Scalp Massage', 'duration' => '3 min', 'products' => 'Rosewater & Aloe Mist'],
            ['id' => 'step_daily_2', 'name' => 'Light Ends Nourishment', 'duration' => '2 min', 'products' => 'Light Nourishing Milk'],
        ],
    ],
];
$routineRepo->saveRoutines($userId, $routines);

// 4. Journal Entries (for Timeline, Growth Sparkline, and Heatmap)
$journalRepo = new JournalRepository();

// Clear existing entries for clean demo state
$existing = get_posts([
    'post_type' => JournalRepository::POST_TYPE,
    'author' => $userId,
    'posts_per_page' => -1,
    'fields' => 'ids',
]);
foreach ($existing as $pId) {
    wp_delete_post($pId, true);
}

$entries = [
    [
        'title' => 'Initial Spring Baseline Length Check',
        'notes' => 'Starting our dedicated length retention cycle! Scalp feels revitalized after clarifying detox.',
        'date' => date('Y-m-d H:i:s', strtotime('-75 days')),
        'entryType' => 'length_check',
        'mood' => 'happy',
        'hairLength' => 9.2,
        'hairLengthPoint' => 'crown',
        'moistureLevel' => 4,
        'scalpState' => 'Normal',
        'goalId' => 'goal_candace_length_1',
    ],
    [
        'title' => 'Steam Mask Hydration Session',
        'notes' => 'Deep conditioned with thermal cap for 25 minutes. Curls bounced right back with maximum elasticity.',
        'date' => date('Y-m-d H:i:s', strtotime('-55 days')),
        'entryType' => 'wash_day',
        'mood' => 'happy',
        'moistureLevel' => 5,
        'scalpState' => 'Hydrated',
        'goalId' => 'goal_candace_moisture_2',
    ],
    [
        'title' => 'Mid-Summer Growth Milestone',
        'notes' => 'Measured crown and back strands. Crown reached 9.8 inches! Breakage during detangling has dropped by half.',
        'date' => date('Y-m-d H:i:s', strtotime('-35 days')),
        'entryType' => 'length_check',
        'mood' => 'happy',
        'hairLength' => 9.8,
        'hairLengthPoint' => 'crown',
        'moistureLevel' => 4,
        'scalpState' => 'Normal',
        'goalId' => 'goal_candace_length_1',
    ],
    [
        'title' => 'Scalp Stimulation & Botanical Infusion',
        'notes' => 'Applied rosemary and peppermint botanical blend with warm scalp massage tool.',
        'date' => date('Y-m-d H:i:s', strtotime('-18 days')),
        'entryType' => 'quick_checkin',
        'mood' => 'happy',
        'moistureLevel' => 4,
        'scalpState' => 'Tingling & Fresh',
        'goalId' => 'goal_candace_scalp_3',
    ],
    [
        'title' => 'Pre-Demo Length Check',
        'notes' => 'Length check confirmed: 10.5 inches! Exactly on track for our December retention milestone.',
        'date' => date('Y-m-d H:i:s', strtotime('-4 days')),
        'entryType' => 'length_check',
        'mood' => 'happy',
        'hairLength' => 10.5,
        'hairLengthPoint' => 'crown',
        'moistureLevel' => 5,
        'scalpState' => 'Healthy',
        'goalId' => 'goal_candace_length_1',
    ],
    [
        'title' => 'Wash Day & Steam Treatment',
        'notes' => 'Full wash day completed today. Hair feels incredibly soft and hydrated from roots to ends.',
        'date' => date('Y-m-d H:i:s', strtotime('-1 days')),
        'entryType' => 'wash_day',
        'mood' => 'happy',
        'moistureLevel' => 5,
        'scalpState' => 'Clean & Hydrated',
        'goalId' => 'goal_candace_moisture_2',
    ],
];

foreach ($entries as $eData) {
    $journalRepo->create($userId, $eData);
}

// 5. Gamification / Rewards
$gamificationRepo = new GamificationRepository();
$gamificationRepo->recordCheckin($userId, 50, 'bonus');

echo "✅ Candace's demo account (ID {$userId}) seeded successfully with HairID, 3 goals, 2 routines, and 6 journal entries!" . PHP_EOL;
