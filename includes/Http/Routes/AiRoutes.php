<?php
/**
 * AI Concierge REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Profile\ProfileRepository;
use Myavana\Next\Domain\Journal\JournalRepository;
use Myavana\Next\Domain\Routine\RoutineService;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Rewards\GamificationRepository;
use Myavana\Next\Domain\AI\AiProxyService;
use Myavana\Next\Domain\AI\InsightEngine;
use Myavana\Next\Domain\Intelligence\IntelligenceOrchestrator;
use Myavana\Next\Domain\Intelligence\ContextBuilders\TodayInsightContextBuilder;
use Myavana\Next\Domain\Intelligence\Tasks\TodayInsightTask;

if (!defined('ABSPATH')) {
    exit;
}

class AiRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/ai/consult', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'consultAi'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/ai/insight', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getDailyInsight'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function consultAi(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $message = sanitize_textarea_field($request->get_param('message') ?? '');

        if (empty(trim($message))) {
            return $this->respondError(__('Message cannot be empty.', 'myavana-hair-journey-next'), 'empty_message', 400);
        }

        $profileRepo = new ProfileRepository();
        $journalRepo = new JournalRepository();

        $profile = $profileRepo->getByUserId($userId);
        $recentLogs = $journalRepo->getEntries($userId, ['perPage' => 5])['items'];

        $aiService = new AiProxyService();
        $result = $aiService->consult($userId, $message, $profile, $recentLogs);

        return $this->respondSuccess($result);
    }

    public function getDailyInsight(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $profile = (new ProfileRepository())->getByUserId($userId);
        $entries = (new JournalRepository())->getEntries($userId, ['perPage' => 60])['items'];
        $checklist = (new RoutineService())->getTodayChecklist($userId);
        $goals = (new GoalRepository())->getGoals($userId);
        $streakDays = (int) ((new GamificationRepository())->getStats($userId)['currentStreak'] ?? 0);
        $firstEntry = !empty($entries) ? end($entries) : null;
        $dayCount = $firstEntry ? max(1, (int) round((time() - strtotime($firstEntry['date'])) / DAY_IN_SECONDS) + 1) : 1;

        $context = TodayInsightContextBuilder::build($profile, $entries, $checklist, $goals, $streakDays, $dayCount);
        $insight = (new IntelligenceOrchestrator())->generate(TodayInsightTask::NAME, $userId, $context, TodayInsightTask::schema());

        if (empty($insight)) {
            $insight = (new InsightEngine())->generateFallbackInsight($context);
        }

        return $this->respondSuccess($insight);
    }
}
