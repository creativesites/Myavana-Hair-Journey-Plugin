<?php
/**
 * Today Hub REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Application\TodayService;

if (!defined('ABSPATH')) {
    exit;
}

class TodayRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/today', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getTodayData'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function getTodayData(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $service = new TodayService();
        $data = $service->getTodayHubData($userId);

        return $this->respondSuccess($data);
    }
}
