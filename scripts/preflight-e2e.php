<?php
/**
 * E2E Preflight — run BEFORE anyone conducts an end-to-end test against a
 * newly deployed chat service.
 *
 * This exists because of a specific, verified failure mode: when the chat
 * service cannot reach WordPress, `wordPressHairJourneyProvider` does not
 * fail. It catches the error, returns null, and every method then substitutes
 * fabricated data — including invented journal entries. Its write paths return
 * `completed: true` / `saved: true` on writes that never arrived, so Mya
 * reports "✓ Synced with Hair Journey" having synced nothing.
 *
 * A test run against that state does not fail visibly. It FALSELY PASSES.
 *
 * So this script does not ask "is the service up". It asks "is the service
 * telling the truth", by checking for the known fabrication signatures.
 *
 * Usage:
 *   php scripts/preflight-e2e.php [--chat-base=https://...] [--user=login_or_id]
 */

require_once dirname(__DIR__, 4) . '/wp-load.php';

$opts = getopt('', ['chat-base::', 'user::']);
$chatBase = rtrim($opts['chat-base'] ?? (defined('MYAVANA_CHAT_API_BASE')
    ? MYAVANA_CHAT_API_BASE
    : get_option('myavana_next_chat_api_base', 'http://localhost:8080')), '/');
$userRef = $opts['user'] ?? 'mya-e2e-test';

$pass = 0; $fail = 0; $warn = 0;
function ok($label, $cond, $detail = '') {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  \033[32mPASS\033[0m  {$label}\n"; }
    else { $fail++; echo "  \033[31mFAIL\033[0m  {$label}" . ($detail ? "\n         → {$detail}" : '') . "\n"; }
}
function warn($label, $detail = '') {
    global $warn; $warn++;
    echo "  \033[33mWARN\033[0m  {$label}" . ($detail ? "\n         → {$detail}" : '') . "\n";
}

echo "\n  MYAVANA E2E Preflight\n";
echo "  chat base: {$chatBase}\n";
echo str_repeat('─', 62) . "\n\n";

// ── 1. Configuration ────────────────────────────────────────────────────────
echo "1. Configuration\n";

ok('Chat API base is not localhost',
    !preg_match('#//(localhost|127\.0\.0\.1)#', $chatBase),
    "Still pointing at {$chatBase} — the plugin will not reach a deployed service.");

ok('MYAVANA_SERVICE_KEY is defined',
    defined('MYAVANA_SERVICE_KEY') && MYAVANA_SERVICE_KEY !== '',
    'Undefined — the hardcoded fallback is authenticating service calls. See thread 004.');

// Compared by hash, never by literal. Writing the known-bad key into a file
// in a public repository would create exactly the exposure this check exists
// to prevent — it is currently in no commit in either repo, and it should stay
// that way.
const KNOWN_BAD_KEY_SHA256 = 'e62c37d4ae1c53e778229b506252fb862857f5199b7ad834879c0f2fbc344777';
if (defined('MYAVANA_SERVICE_KEY')
    && hash('sha256', MYAVANA_SERVICE_KEY) === KNOWN_BAD_KEY_SHA256) {
    ok('Service key is not the known-exposed default', false,
        'The constant is set to the hardcoded fallback value. Generate a fresh one.');
}

ok('MYAVANA_CHAT_SECRET is defined',
    defined('MYAVANA_CHAT_SECRET') && MYAVANA_CHAT_SECRET !== '',
    'Undefined — chat tokens are signed with wp_salt(\'auth\'). Works, but give it its own constant.');

if (strpos(get_site_url(), 'https://') !== 0) {
    warn('Site is not served over HTTPS',
        'A widget on http:// calling an https:// Cloud Run service is fine, but the reverse is blocked as mixed content.');
}

// ── 2. Test subject ─────────────────────────────────────────────────────────
echo "\n2. Test subject\n";

$user = is_numeric($userRef) ? get_userdata((int) $userRef)
    : (get_user_by('login', $userRef) ?: get_user_by('email', $userRef));

if (!$user) {
    ok("Test user '{$userRef}' exists", false, 'Run: php scripts/seed-e2e-fixture.php');
    echo "\n  Cannot continue without a test subject.\n\n";
    exit(1);
}
ok("Test user '{$user->user_login}' exists (ID {$user->ID})", true);

$profileRepo = new \Myavana\Next\Domain\Profile\ProfileRepository();
$profile = $profileRepo->getByUserId($user->ID)->toArray();
$goals = (new \Myavana\Next\Domain\Goals\GoalRepository())->getGoals($user->ID);
$entries = (new \Myavana\Next\Domain\Journal\JournalRepository())->getEntries($user->ID, ['perPage' => 50])['items'] ?? [];
$routines = (new \Myavana\Next\Domain\Routine\RoutineRepository())->getRoutines($user->ID);

ok('Subject has a hair type on record', !empty($profile['hairType']),
    'Nothing to compare against — Mya could say anything and we could not tell.');
ok('Subject has at least one goal', count($goals) > 0);
ok('Subject has journal entries', count($entries) > 0);
ok('Subject has a routine with steps', count($routines) > 0);

echo "\n   Ground truth for this subject — Mya must match these:\n";
echo "     hairType : " . ($profile['hairType'] ?: '(unset)') . "\n";
echo "     porosity : " . ($profile['porosity'] ?: '(unset)') . "\n";
echo "     density  : " . ($profile['density'] ?: '(unset)') . "\n";
echo "     length   : " . ($profile['length'] ?: '(unset)') . "\n";
echo "     goals    : " . count($goals) . " (" . implode('; ', array_map(fn($g) => $g['title'] ?? '?', array_slice($goals, 0, 2))) . ")\n";
echo "     entries  : " . count($entries) . "\n";

// ── 3. Fabrication canary ───────────────────────────────────────────────────
// These are the literal values wordPressHairJourneyProvider substitutes when
// it cannot reach WordPress. If the subject's REAL data happens to match one,
// we lose the ability to distinguish truth from fallback for that field.
echo "\n3. Fabrication canary\n";

$fabricated = [
    'hairType' => 'Type 4 (Coily)',
    'porosity' => 'Normal Porosity',
    'elasticity' => 'Medium',
];
$collisions = [];
foreach ($fabricated as $field => $fake) {
    if (!empty($profile[$field]) && strcasecmp(trim($profile[$field]), $fake) === 0) {
        $collisions[] = "{$field} = '{$fake}'";
    }
}
ok('Subject data does not collide with the fallback fixtures',
    empty($collisions),
    'Collides on: ' . implode(', ', $collisions) . ". Change the subject's data — otherwise a "
    . "fabricated answer is indistinguishable from a correct one for that field.");

echo "\n   Canary strings — if ANY appear in Mya's answers, WordPress is unreachable\n";
echo "   and she is inventing. Treat as an immediate no-go:\n";
echo "     \"Type 4 (Coily)\"  \"Normal Porosity\"  \"L.O.C. Method weekly\"\n";
echo "     \"Retain 4 inches by December 2026\"  \"Morning Hydration Mist\"\n";
echo "     \"Sunday Wash Day & Heat Steam\"  \"Deep Condition with Heat (20 min)\"\n";

// ── 4. Reachability ─────────────────────────────────────────────────────────
echo "\n4. Reachability\n";

$health = wp_remote_get($chatBase . '/health', ['timeout' => 8]);
if (is_wp_error($health)) {
    ok('Chat service reachable from WordPress', false, $health->get_error_message());
} else {
    ok('Chat service reachable from WordPress',
        wp_remote_retrieve_response_code($health) < 500,
        'HTTP ' . wp_remote_retrieve_response_code($health));
}

warn('Cloud Run → WordPress reachability CANNOT be checked from here',
    "This script runs on the WordPress host, so it proves nothing about whether the chat service "
    . "can reach back. That direction is the actual blocker: if WORDPRESS_API_BASE is a .local "
    . "hostname it resolves to 127.0.0.1 and Cloud Run will hit itself. Verify from the service side "
    . "with:  curl -H 'X-Myavana-Service-Key: <key>' -H 'X-On-Behalf-Of: {$user->ID}' "
    . "<public-wp-url>/wp-json/myavana/v1/profile");

// ── Summary ─────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 62) . "\n";
echo "  {$pass} passed, {$fail} failed, {$warn} warnings\n\n";

if ($fail > 0) {
    echo "  \033[31mNOT CLEAR FOR E2E TEST.\033[0m Resolve the failures above first.\n\n";
    exit(1);
}
echo "  \033[32mPreflight clear.\033[0m Still confirm the Cloud Run → WordPress direction\n";
echo "  by hand (see warning) before letting anyone conduct the test.\n\n";
