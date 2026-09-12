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
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/community/posts', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'createPost'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/community/posts/(?P<id>\d+)/like', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'toggleLike'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/community/posts/(?P<id>\d+)/comments', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getComments'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/community/posts/(?P<id>\d+)/comments', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addComment'],
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

    public function toggleLike(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $postId = (int) $request->get_param('id');
        if ($postId <= 0) {
            return $this->respondError('Invalid post ID', 'invalid_id', 400);
        }

        $repo = new CommunityRepository();
        $result = $repo->toggleLike($userId, $postId);
        return $this->respondSuccess($result);
    }

    public function getComments(\WP_REST_Request $request): \WP_REST_Response {
        $postId = (int) $request->get_param('id');
        if ($postId <= 0) {
            return $this->respondError('Invalid post ID', 'invalid_id', 400);
        }

        $repo = new CommunityRepository();
        $comments = $repo->getComments($postId);
        return $this->respondSuccess($comments);
    }

    public function addComment(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $postId = (int) $request->get_param('id');
        $data = $request->get_json_params() ?: $request->get_params();
        $content = (string) ($data['content'] ?? '');

        if ($postId <= 0) {
            return $this->respondError('Invalid post ID', 'invalid_id', 400);
        }

        $repo = new CommunityRepository();
        $result = $repo->addComment($userId, $postId, $content);
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
