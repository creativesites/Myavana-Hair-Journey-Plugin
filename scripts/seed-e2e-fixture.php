<?php
/**
 * Seed the E2E test fixture member.
 *
 * Deliberately NOT Candace. `seed-candace-demo.php` writes synthetic data into
 * `candyvharris` — the real account of MYAVANA's founder — which is fine for an
 * executive demo but wrong for an automated test subject: once synthetic and
 * real data share an account, nobody downstream can tell which is which. That
 * ambiguity is the whole failure mode we removed from this codebase.
 *
 * This account is unmistakable on sight. The display name says synthetic, the
 * bio says synthetic, and the email is on a reserved invalid domain.
 *
 * The hair traits are chosen to DIFFER from the chat service's fabricated
 * fallbacks, so a fabricated answer cannot be mistaken for a correct one:
 *
 *   fallback says : Type 4 (Coily) / Normal Porosity / L.O.C. Method weekly
 *   fixture is    : 3C            / Low Porosity    / Sunday Reset Wash Day
 *
 * Usage:  php scripts/seed-e2e-fixture.php [--reset]
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';

use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Journal\JournalRepository;

const FIXTURE_LOGIN = 'mya-e2e-test';
const FIXTURE_EMAIL = 'e2e-fixture@invalid.test';   // reserved TLD, cannot receive mail

$opts = getopt('', ['reset']);

$user = get_user_by('login', FIXTURE_LOGIN);
if (!$user) {
    $userId = wp_create_user(FIXTURE_LOGIN, wp_generate_password(24), FIXTURE_EMAIL);
    if (is_wp_error($userId)) {
        fwrite(STDERR, "Could not create fixture user: " . $userId->get_error_message() . PHP_EOL);
        exit(1);
    }
    $user = get_userdata($userId);
    echo "Created fixture user {$userId} (" . FIXTURE_LOGIN . ")" . PHP_EOL;
} else {
    echo "Fixture user {$user->ID} (" . FIXTURE_LOGIN . ") already exists" . PHP_EOL;
}
$userId = $user->ID;

wp_update_user([
    'ID' => $userId,
    'display_name' => 'E2E Test Member (synthetic)',
    'first_name' => 'E2E',
    'last_name' => 'Fixture',
    'role' => 'subscriber',
]);
update_user_meta($userId, 'myavana_is_test_fixture', '1');

if (!empty($opts['reset'])) {
    foreach (get_posts([
        'post_type' => JournalRepository::POST_TYPE, 'author' => $userId,
        'posts_per_page' => -1, 'post_status' => 'any',
    ]) as $p) {
        wp_delete_post($p->ID, true);
    }
    echo "Reset: cleared existing fixture journal entries" . PHP_EOL;
}

// ── Profile: deliberately distinct from the fabricated fallback ─────────────
(new ProfileRepository())->save($userId, [
    'displayName' => 'E2E Test Member (synthetic)',
    'hairType' => '3C',
    'porosity' => 'Low',
    'density' => 'High',
    'length' => '8.25',
    'concerns' => ['Product Buildup', 'Shrinkage'],
    'hairJourneyStage' => 'Rebuilding After Heat Damage',
    'hairHealthRating' => 6,
    'bio' => 'SYNTHETIC TEST FIXTURE — not a real member. Used for end-to-end verification.',
    'location' => 'Test Environment',
    'profileVisibility' => 'private',
    'measurementUnit' => 'in',
]);
update_user_meta($userId, 'myavana_onboarding_completed', '1');
update_user_meta($userId, 'myavana_email_verified', 'yes');

// ── One goal, with numbers precise enough to catch an invented answer ───────
(new GoalRepository())->saveGoals($userId, [[
    'id' => 'goal_e2e_length',
    'title' => 'Reach 11.5 inches without heat',
    'category' => 'Length Retention',
    'description' => 'Distinctive target so a fabricated goal is obvious on sight.',
    'target_date' => '2027-03-31',
    'status' => 'active',
    'progress' => 37,
    'start_length' => 8.25,
    'target_length' => 11.5,
    'created_at' => date('Y-m-d H:i:s', strtotime('-45 days')),
]]);

// ── Routine with named steps the fallback does not contain ─────────────────
(new RoutineRepository())->saveRoutines($userId, [[
    'id' => 'routine_e2e_sunday',
    'title' => 'Sunday Reset Wash Day',
    'category' => 'Wash Day',
    'frequency' => 'Weekly',
    'description' => 'Fixture routine for end-to-end verification.',
    'steps' => [
        ['id' => 'e2e_step_chelate', 'name' => 'Chelating rinse', 'duration' => '4 min', 'products' => 'Clarifying rinse'],
        ['id' => 'e2e_step_condition', 'name' => 'Slip detangle under running water', 'duration' => '12 min', 'products' => 'Slip conditioner'],
        ['id' => 'e2e_step_gel', 'name' => 'Definition gel on soaking hair', 'duration' => '6 min', 'products' => 'Flaxseed gel'],
    ],
    'products' => ['Clarifying rinse', 'Slip conditioner', 'Flaxseed gel'],
]]);

// ── Journal entries with unmistakable titles ───────────────────────────────
$journalRepo = new JournalRepository();
$seedEntries = [
    ['title' => 'Fixture entry — chelating reset',   'daysAgo' => 21, 'type' => 'wash_day',     'moisture' => 3, 'mood' => 'Weighed down', 'len' => null],
    ['title' => 'Fixture entry — length check 8.25', 'daysAgo' => 10, 'type' => 'length_check', 'moisture' => 4, 'mood' => 'Encouraged',   'len' => 8.25],
    ['title' => 'Fixture entry — gel cast success',  'daysAgo' => 3,  'type' => 'wash_day',     'moisture' => 5, 'mood' => 'Defined',      'len' => null],
];

$existing = wp_list_pluck($journalRepo->getEntries($userId, ['perPage' => 50])['items'] ?? [], 'title');
foreach ($seedEntries as $e) {
    if (in_array($e['title'], $existing, true)) {
        echo "  skip (exists): {$e['title']}" . PHP_EOL;
        continue;
    }
    $journalRepo->create($userId, [
        'title' => $e['title'],
        'notes' => 'Synthetic fixture entry created by seed-e2e-fixture.php for end-to-end verification.',
        'date' => date('Y-m-d H:i:s', strtotime("-{$e['daysAgo']} days")),
        'entryType' => $e['type'],
        'mood' => $e['mood'],
        'moistureLevel' => $e['moisture'],
        'scalpState' => 'Clean',
        'hairLength' => $e['len'],
        'hairLengthPoint' => $e['len'] ? 'crown' : '',
        'productsUsed' => ['Flaxseed gel'],
        'visibility' => 'private',
    ]);
    echo "  created: {$e['title']}" . PHP_EOL;
}

echo PHP_EOL . "Fixture ready. Ground truth for the E2E test:" . PHP_EOL;
echo "  login     : " . FIXTURE_LOGIN . " (ID {$userId})" . PHP_EOL;
echo "  hairType  : 3C          (fallback would say 'Type 4 (Coily)')" . PHP_EOL;
echo "  porosity  : Low         (fallback would say 'Normal Porosity')" . PHP_EOL;
echo "  length    : 8.25 in" . PHP_EOL;
echo "  goal      : Reach 11.5 inches without heat — 37%" . PHP_EOL;
echo "  routine   : Sunday Reset Wash Day (3 steps)" . PHP_EOL;
echo PHP_EOL . "Next: php scripts/preflight-e2e.php --user=" . FIXTURE_LOGIN . PHP_EOL;
