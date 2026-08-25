<?php
/**
 * App Initialization Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Application\TodayService;
use Myavana\Next\Application\JourneyService;
use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Rewards\GamificationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class InitRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/app/init', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getInitData'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getInitData(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $isLoggedIn = Permissions::isAuthenticated();

        if (!$isLoggedIn || $userId <= 0) {
            return $this->respondSuccess([
                'isLoggedIn' => false,
                'user' => null,
                'discovery' => [
                    'heroTitle' => __('Understand Your Hair Better', 'myavana-hair-journey-next'),
                    'heroSubtitle' => __('Get a scientific, confidence-aware analysis and personalized daily regimen in under 90 seconds.', 'myavana-hair-journey-next'),
                    'features' => [
                        ['title' => __('Two-Speed Check-ins', 'myavana-hair-journey-next'), 'desc' => __('15-second daily touchups or deep wash-day logs.')],
                        ['title' => __('Visual Proof of Progress', 'myavana-hair-journey-next'), 'desc' => __('Interactive timeline, calendar heatmap, and photo slider.')],
                        ['title' => __('Transparent AI Hair Science', 'myavana-hair-journey-next'), 'desc' => __('Evidence-based recommendations that adapt to your curl texture.')],
                    ],
                ],
            ]);
        }

        $todayService = new TodayService();
        $profileRepo = new ProfileRepository();
        $gamificationRepo = new GamificationRepository();

        $todayData = $todayService->getTodayHubData($userId);
        $profile = $profileRepo->getByUserId($userId);
        $stats = $gamificationRepo->getStats($userId);

        return $this->respondSuccess([
            'isLoggedIn' => true,
            'user' => $profile->toArray(),
            'today' => $todayData,
            'stats' => $stats,
        ]);
    }
}
