<?php
/**
 * Goal REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Goals\GoalService;

if (!defined('ABSPATH')) {
    exit;
}

class GoalRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/goals', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'getGoals'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addGoal'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/goals/(?P<id>[\w\-]+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateGoal'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteGoal'],
                'permission_callback' => [Permissions::class, 'restUserCheck'],
            ],
        ]);
    }

    public function getGoals(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $service = new GoalService();
        return $this->respondSuccess($service->getOverview($userId));
    }

    public function addGoal(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $data = $request->get_json_params() ?: $request->get_params();

        $repo = new GoalRepository();
        $updatedGoals = $repo->addGoal($userId, $data);

        $service = new GoalService($repo);
        return $this->respondSuccess($service->getOverview($userId), 201);
    }

    public function updateGoal(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $goalId = sanitize_key($request['id']);
        $data = $request->get_json_params() ?: $request->get_params();

        $repo = new GoalRepository();
        $updatedGoals = $repo->updateGoal($userId, $goalId, $data);

        $service = new GoalService($repo);
        return $this->respondSuccess($service->getOverview($userId));
    }

    public function deleteGoal(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $goalId = sanitize_key($request['id']);

        $repo = new GoalRepository();
        $updatedGoals = $repo->deleteGoal($userId, $goalId);

        $service = new GoalService($repo);
        return $this->respondSuccess($service->getOverview($userId));
    }
}
