<?php
/**
 * Profile & Privacy REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Rewards\GamificationRepository;
use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Goals\GoalService;

if (!defined('ABSPATH')) {
    exit;
}

class ProfileRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/profile', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getProfile'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveProfile'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/profile/avatar', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'uploadAvatar'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/profile/export', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'exportUserData'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/profile/onboarding', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'completeOnboarding'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function getProfile(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $userId = $this->getUserId();
        $profileRepo = new ProfileRepository();
        $gamificationRepo = new GamificationRepository();
        $journalRepo = new JournalRepository();
        $routineRepo = new RoutineRepository();
        $goalRepo = new GoalRepository();

        $profileData = $profileRepo->getByUserId($userId)->toArray();
        $stats = $gamificationRepo->getStats($userId);
        $badges = $gamificationRepo->getBadges($userId);

        // Fetch User Journey Entries
        $entriesResult = $journalRepo->getEntries($userId, ['perPage' => 50]);
        $entries = $entriesResult['items'] ?? [];
        $totalEntries = $entriesResult['total'] ?? count($entries);

        // Fetch Goals & Routines
        $goals = $goalRepo->getGoals($userId);
        $routines = $routineRepo->getRoutines($userId);

        // Fetch Community Stats & Posts
        $communityPosts = [];
        $communityStats = [
            'totalPosts' => 0,
            'totalLikes' => 0,
            'totalComments' => 0,
            'followersCount' => 0,
            'followingCount' => 0,
        ];

        // Check wp_posts for user community posts
        $userPosts = get_posts([
            'post_type' => ['myavana_post', 'myavana_community_post'],
            'author' => $userId,
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ]);

        if (!empty($userPosts)) {
            $communityStats['totalPosts'] = count($userPosts);
            foreach ($userPosts as $p) {
                $thumbId = get_post_thumbnail_id($p->ID);
                $thumbUrl = $thumbId ? wp_get_attachment_image_url($thumbId, 'large') : '';
                $likesCount = (int) get_post_meta($p->ID, 'likes_count', true);
                $commentsCount = (int) get_comments_number($p->ID);
                $communityStats['totalLikes'] += $likesCount;
                $communityStats['totalComments'] += $commentsCount;

                $communityPosts[] = [
                    'id' => $p->ID,
                    'title' => $p->post_title,
                    'content' => wp_strip_all_tags($p->post_content),
                    'date' => get_the_date('M j, Y', $p->ID),
                    'imageUrl' => $thumbUrl,
                    'likesCount' => $likesCount,
                    'commentsCount' => $commentsCount,
                ];
            }
        }

        // Followers / Following
        $followers = get_user_meta($userId, 'myavana_followers', true);
        $following = get_user_meta($userId, 'myavana_following', true);
        $communityStats['followersCount'] = is_array($followers) ? count($followers) : (int) get_user_meta($userId, 'myavana_followers_count', true);
        $communityStats['followingCount'] = is_array($following) ? count($following) : (int) get_user_meta($userId, 'myavana_following_count', true);

        // Compute Analytics Aggregates
        $healthRatings = [];
        $moodCounts = [];
        $monthlyCounts = [];

        foreach ($entries as $entry) {
            $rating = (int) ($entry['healthRating'] ?? 0);
            if ($rating > 0) {
                $healthRatings[] = $rating;
            }
            $mood = !empty($entry['mood']) ? $entry['mood'] : 'Balanced';
            $moodCounts[$mood] = ($moodCounts[$mood] ?? 0) + 1;

            $dateStr = $entry['date'] ?? '';
            if ($dateStr) {
                $month = date('M', strtotime($dateStr));
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        $avgHealth = !empty($healthRatings) ? round(array_sum($healthRatings) / count($healthRatings), 1) : 0;
        arsort($moodCounts);
        $topMood = !empty($moodCounts) ? array_key_first($moodCounts) : 'Hydrated';

        $analytics = [
            'totalEntries' => $totalEntries,
            'currentStreak' => $stats['currentStreak'] ?? 0,
            'avgHealthScore' => $avgHealth,
            'topMood' => $topMood,
            'monthlyCounts' => $monthlyCounts,
            'progressScore' => min(100, (int) (($stats['currentStreak'] * 5) + ($totalEntries * 4) + ($avgHealth * 4))),
        ];

        // Day count on the journey, mirroring Today/Timeline's calculation.
        $firstEntry = !empty($entries) ? end($entries) : null;
        $dayCount = $firstEntry ? max(1, (int) round((time() - strtotime($firstEntry['date'])) / DAY_IN_SECONDS) + 1) : 1;

        // Real length-growth numbers from logged length-check readings.
        $lengthHistory = $journalRepo->getLengthHistory($userId);
        $currentLength = !empty($lengthHistory) ? end($lengthHistory)['length'] : null;
        $lengthGain = count($lengthHistory) >= 2 ? round($currentLength - $lengthHistory[0]['length'], 1) : null;

        // Milestone-tagged entries with a photo, for the Milestones grid.
        $milestones = array_values(array_filter($entries, function ($e) {
            return in_array($e['entryType'] ?? '', ['milestone', 'length_check'], true) && !empty($e['featuredImage']);
        }));
        $milestones = array_slice($milestones, 0, 6);

        $goalsOverview = (new GoalService($goalRepo))->getOverview($userId);
        $currentUser = wp_get_current_user();

        return $this->respondSuccess([
            'profile' => $profileData,
            'stats' => $stats,
            'badges' => $badges,
            'entries' => $entries,
            'totalEntries' => $totalEntries,
            'goals' => $goals,
            'goalsOverview' => $goalsOverview,
            'routines' => $routines,
            'community' => [
                'stats' => $communityStats,
                'posts' => $communityPosts,
            ],
            'analytics' => $analytics,
            'dayCount' => $dayCount,
            'joinDate' => $currentUser->user_registered ?? '',
            'currentLength' => $currentLength,
            'lengthGain' => $lengthGain,
            'healthScore' => ($profileData['hairHealthRating'] ?? 0) * 10,
            'milestones' => $milestones,
            'hairIdNote' => $this->buildHairIdNote($profileData),
        ]);
    }

    /**
     * A short, templated note about what the member's own stored hair
     * traits typically mean — grounded in their real profile fields, not a
     * fabricated AI capability.
     *
     * @param array $profile
     * @return string
     */
    private function buildHairIdNote(array $profile): string {
        $porosity = $profile['porosity'] ?? '';
        $type = $profile['hairType'] ?? '';

        if (empty($porosity) && empty($type)) {
            return __('Fill in your hair type and porosity to see what your HairID means for your routine.', 'myavana-hair-journey-next');
        }

        $porosityNotes = [
            'Low' => __('tends to resist moisture and benefits from heat during deep conditioning', 'myavana-hair-journey-next'),
            'Medium' => __('holds moisture fairly well and adapts to most routines', 'myavana-hair-journey-next'),
            'High' => __('absorbs moisture quickly but can lose it just as fast, so sealing matters', 'myavana-hair-journey-next'),
        ];
        $porosityText = $porosityNotes[$porosity] ?? __('has its own moisture rhythm worth tracking over time', 'myavana-hair-journey-next');
        $typeText = !empty($type) ? sprintf(__('Type %s hair', 'myavana-hair-journey-next'), $type) : __('Your hair', 'myavana-hair-journey-next');

        return sprintf('%s %s.', $typeText, $porosityText);
    }

    public function saveProfile(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $data = $request->get_json_params() ?: $request->get_params();

        $profileRepo = new ProfileRepository();
        $updated = $profileRepo->save($userId, $data);

        return $this->respondSuccess([
            'profile' => $updated->toArray(),
            'message' => __('Profile updated successfully.', 'myavana-hair-journey-next'),
        ]);
    }

    /**
     * Record that the post-signup onboarding wizard was finished or
     * explicitly skipped, so it never shows again for this user. Writes
     * myavana_onboarding_completed — the same meta key the legacy
     * luxury-home template already reads for its own "first entry" banner
     * — so a Next-plugin signup is no longer invisible to that logic, and
     * myavana_onboarding_status for parity with what AuthService/
     * GoogleAuthService recorded at signup time.
     */
    public function completeOnboarding(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $data = $request->get_json_params() ?: $request->get_params();
        $status = ($data['status'] ?? '') === 'skipped' ? 'skipped' : 'completed';

        update_user_meta($userId, 'myavana_onboarding_completed', $status);
        update_user_meta($userId, 'myavana_onboarding_status', $status);

        return $this->respondSuccess(['status' => $status]);
    }

    public function uploadAvatar(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $files = $request->get_file_params();

        if (empty($files['avatar'])) {
            // Check base64 in json params
            $params = $request->get_json_params() ?: $request->get_params();
            $base64 = $params['avatarData'] ?? '';
            if (!empty($base64)) {
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
                    $base64 = substr($base64, strpos($base64, ',') + 1);
                    $type = strtolower($type[1]);
                    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                        return $this->respondError(__('Invalid image type', 'myavana-hair-journey-next'), 400);
                    }
                    $data = base64_decode($base64);
                    if ($data === false) {
                        return $this->respondError(__('Base64 decode failed', 'myavana-hair-journey-next'), 400);
                    }
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $type;
                    $upload = wp_upload_bits($filename, null, $data);
                    if (!empty($upload['error'])) {
                        return $this->respondError($upload['error'], 500);
                    }
                    update_user_meta($userId, 'myavana_custom_avatar_url', esc_url_raw($upload['url']));
                    return $this->respondSuccess([
                        'avatarUrl' => $upload['url'],
                        'message' => __('Avatar updated successfully.', 'myavana-hair-journey-next'),
                    ]);
                }
            }
            return $this->respondError(__('No image uploaded.', 'myavana-hair-journey-next'), 400);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $files['avatar'];
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return $this->respondError($upload['error'], 500);
        }

        $avatarUrl = esc_url_raw($upload['url']);
        update_user_meta($userId, 'myavana_custom_avatar_url', $avatarUrl);

        return $this->respondSuccess([
            'avatarUrl' => $avatarUrl,
            'message' => __('Avatar updated successfully.', 'myavana-hair-journey-next'),
        ]);
    }

    public function exportUserData(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $profileRepo = new ProfileRepository();
        $journalRepo = new JournalRepository();
        $routineRepo = new RoutineRepository();
        $goalRepo = new GoalRepository();
        $gamificationRepo = new GamificationRepository();

        $export = [
            'exportedAt' => current_time('mysql'),
            'profile' => $profileRepo->getByUserId($userId)->toArray(),
            'entries' => $journalRepo->getEntries($userId, ['perPage' => 200])['items'],
            'routines' => $routineRepo->getRoutines($userId),
            'goals' => $goalRepo->getGoals($userId),
            'stats' => $gamificationRepo->getStats($userId),
        ];

        return $this->respondSuccess($export);
    }
}
