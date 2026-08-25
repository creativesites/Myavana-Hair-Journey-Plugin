<?php
/**
 * Routine REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Routine\RoutineRepository;
use Myavana\Next\Domain\Routine\RoutineService;
use Myavana\Next\Domain\Rewards\RewardService;

if (!defined('ABSPATH')) {
    exit;
}

class RoutineRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/routine', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getRoutines'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveRoutines'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/routine/toggle-step', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggleStepCompletion'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/routine/cabinet', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getCabinet'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function getRoutines(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $repo = new RoutineRepository();
        $service = new RoutineService($repo);

        return $this->respondSuccess([
            'routines' => $repo->getRoutines($userId),
            'checklist' => $service->getTodayChecklist($userId),
            'cabinet' => $service->getProductCabinet($userId),
        ]);
    }

    public function saveRoutines(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $repo = new RoutineRepository();
        $data = $request->get_json_params() ?: $request->get_params();
        $routines = $data['routines'] ?? null;

        if (is_array($routines)) {
            $repo->saveRoutines($userId, $routines);
        } elseif (!empty($data['title'])) {
            $repo->addRoutine($userId, $data);
        } else {
            return $this->respondError(__('Please add a routine name.', 'myavana-hair-journey-next'), 'invalid_format', 400);
        }

        $service = new RoutineService($repo);
        return $this->respondSuccess([
            'routines' => $repo->getRoutines($userId),
            'checklist' => $service->getTodayChecklist($userId),
            'message' => __('Routine saved successfully.', 'myavana-hair-journey-next'),
        ]);
    }

    public function toggleStepCompletion(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $stepId = sanitize_text_field($request->get_param('stepId') ?? '');
        $date = sanitize_text_field($request->get_param('date') ?? current_time('Y-m-d'));

        if (empty($stepId)) {
            return $this->respondError(__('Step ID required.', 'myavana-hair-journey-next'), 'missing_id', 400);
        }

        $repo = new RoutineRepository();
        $completions = $repo->toggleCompletion($userId, $stepId, $date);

        // Award small points if completed
        $rewardService = new RewardService();
        $stats = $rewardService->awardRoutineStep($userId);

        $service = new RoutineService($repo);
        $checklist = $service->getTodayChecklist($userId, $date);

        return $this->respondSuccess([
            'stepId' => $stepId,
            'completions' => $completions,
            'checklist' => $checklist,
            'stats' => $stats,
        ]);
    }

    public function getCabinet(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $service = new RoutineService();
        return $this->respondSuccess($service->getProductCabinet($userId));
    }
}
