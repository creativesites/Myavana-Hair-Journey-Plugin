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

        register_rest_route(self::NAMESPACE, '/community/upload', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'uploadMedia'],
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

    /**
     * Upload community post media (supports multipart and base64)
     */
    public function uploadMedia(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();
        $files = $request->get_file_params();

        // Check base64 input first
        if (empty($files['file']) && empty($files['image'])) {
            $params = $request->get_json_params() ?: $request->get_params();
            $base64 = $params['mediaData'] ?? ($params['data'] ?? ($params['image'] ?? ''));

            if (!empty($base64)) {
                $type = 'jpg';
                if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matched)) {
                    $base64 = substr($base64, strpos($base64, ',') + 1);
                    $type = strtolower($matched[1]);
                }
                if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    return $this->respondError(__('Invalid image type. Supported: JPG, PNG, WEBP.', 'myavana-hair-journey-next'), 'invalid_type', 400);
                }

                $data = base64_decode($base64);
                if ($data === false) {
                    return $this->respondError(__('Base64 decode failed.', 'myavana-hair-journey-next'), 'decode_failed', 400);
                }

                $filename = 'community_' . $userId . '_' . time() . '_' . wp_generate_password(6, false) . '.' . $type;
                $upload = wp_upload_bits($filename, null, $data);
                if (!empty($upload['error'])) {
                    return $this->respondError($upload['error'], 'upload_error', 500);
                }

                return $this->respondSuccess([
                    'url' => esc_url_raw($upload['url']),
                    'mediaType' => 'image',
                    'message' => __('Photo uploaded successfully.', 'myavana-hair-journey-next'),
                ], 201);
            }

            return $this->respondError(__('No image or photo file provided.', 'myavana-hair-journey-next'), 'missing_file', 400);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $files['file'] ?? $files['image'];
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            ],
        ];

        $upload = wp_handle_upload($file, $upload_overrides);
        if (isset($upload['error'])) {
            return $this->respondError($upload['error'], 'upload_error', 500);
        }

        return $this->respondSuccess([
            'url' => esc_url_raw($upload['url']),
            'mediaType' => 'image',
            'message' => __('Photo uploaded successfully.', 'myavana-hair-journey-next'),
        ], 201);
    }
}
