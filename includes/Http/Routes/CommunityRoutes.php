<?php
/**
 * Community REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Community\CommunityRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CommunityRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/community/feed', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getFeed'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/community/posts', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'createPost'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/community/twins', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getHairTwins'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);
    }

    public function getFeed(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $params = $request->get_params();
        $repo = new CommunityRepository();
        $data = $repo->getFeed($userId, $params);

        return $this->respondSuccess($data);
    }

    public function createPost(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $data = $request->get_json_params() ?: $request->get_params();

        $repo = new CommunityRepository();
        $result = $repo->createPost($userId, $data);

        if (is_wp_error($result)) {
            return $this->respondError($result->get_error_message(), $result->get_error_code(), 400);
        }

        return $this->respondSuccess($result, 201);
    }

    public function getHairTwins(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $repo = new CommunityRepository();
        return $this->respondSuccess($repo->getHairTwins($userId));
    }
}
