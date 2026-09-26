<?php
/**
 * Auth REST Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;
use Myavana\Next\Core\Permissions;
use Myavana\Next\Domain\Auth\AuthService;
use Myavana\Next\Domain\Auth\GoogleAuthService;

if (!defined('ABSPATH')) {
    exit;
}

class AuthRoutes extends RestController {
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'register'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/google', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'google'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/resend-verification', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resendVerification'],
            'permission_callback' => [Permissions::class, 'restUserCheck'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/forgot-password', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'forgotPassword'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/reset-password', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resetPassword'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/chat-token', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getChatToken'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/sync-mobile-user', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'syncMobileUser'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function register(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $service = new AuthService();
        $result = $service->register($data);

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result, 201);
    }

    public function login(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $service = new AuthService();
        $result = $service->login($data);

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    public function google(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $credential = (string) ($data['credential'] ?? '');

        $service = new GoogleAuthService();
        $result = $service->authenticate($credential);

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    public function resendVerification(\WP_REST_Request $request): \WP_REST_Response {
        $service = new AuthService();
        $result = $service->resendVerification($this->getUserId());

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    public function forgotPassword(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $service = new AuthService();
        $result = $service->requestPasswordReset((string) ($data['login'] ?? ''));

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    public function resetPassword(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $service = new AuthService();
        $result = $service->resetPassword(
            (string) ($data['login'] ?? ''),
            (string) ($data['key'] ?? ''),
            (string) ($data['password'] ?? '')
        );

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    /**
     * Mint a short-lived token for Mya chat sessions (authenticated or guest)
     */
    public function getChatToken(\WP_REST_Request $request): \WP_REST_Response {
        $userId = $this->getUserId();

        if ($userId > 0) {
            $user = get_userdata($userId);
            if (!$user) {
                return $this->respondError(__('User not found.', 'myavana-hair-journey-next'), 'not_found', 404);
            }

            $payload = [
                'userId' => $userId,
                'email' => $user->user_email,
                'name' => $user->display_name,
                'exp' => time() + 300,
            ];
        } else {
            // Guest token for unauthenticated visitors
            $guestId = 'guest_' . wp_generate_uuid4();
            $payload = [
                'userId' => $guestId,
                'email' => '',
                'name' => 'Guest',
                'isGuest' => true,
                'exp' => time() + 300,
            ];
        }

        $secret = defined('MYAVANA_CHAT_SECRET') ? MYAVANA_CHAT_SECRET : wp_salt('auth');
        $token = base64_encode(json_encode($payload)) . '.' . hash_hmac('sha256', json_encode($payload), $secret);

        $response = [
            'token' => $token,
            'expiresIn' => 300,
        ];

        if ($userId > 0) {
            $user = get_userdata($userId);
            $response['userId'] = $userId;
            $response['userName'] = $user->display_name;
            $response['firstName'] = $user->first_name ?: $user->display_name;
        } else {
            $response['userId'] = $payload['userId'];
            $response['userName'] = 'Guest';
            $response['firstName'] = 'Guest';
        }

        return $this->respondSuccess($response);
    }

    /**
     * Silently synchronize mobile app user with WordPress site user
     */
    public function syncMobileUser(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $email = sanitize_email($data['email'] ?? '');
        $name  = sanitize_text_field($data['name'] ?? '');
        $hairType = sanitize_text_field($data['hairType'] ?? '');

        if (empty($email)) {
            return $this->respondError(__('Email is required for synchronization.', 'myavana-hair-journey-next'), 'missing_email', 400);
        }

        $user = get_user_by('email', $email);
        $userId = 0;

        if (!$user) {
            $username = sanitize_user(explode('@', $email)[0]);
            if (username_exists($username)) {
                $username .= '_' . wp_rand(100, 999);
            }
            $randomPassword = wp_generate_password(24, true, true);
            $userId = wp_create_user($username, $randomPassword, $email);

            if (is_wp_error($userId)) {
                return $this->fromWpError($userId);
            }

            if (!empty($name)) {
                wp_update_user([
                    'ID' => $userId,
                    'display_name' => $name,
                    'first_name' => $name,
                ]);
            }
            $user = get_userdata($userId);
        } else {
            $userId = (int) $user->ID;
            if (!empty($name) && empty($user->display_name)) {
                wp_update_user([
                    'ID' => $userId,
                    'display_name' => $name,
                ]);
            }
        }

        if (!empty($hairType)) {
            update_user_meta($userId, 'myavana_hair_type', $hairType);
        }

        // Issue 30-day mobile auth token
        $payload = [
            'userId' => $userId,
            'email' => $email,
            'name' => $user->display_name ?: $name ?: $user->user_login,
            'exp' => time() + (86400 * 30),
        ];

        $secret = defined('MYAVANA_CHAT_SECRET') ? MYAVANA_CHAT_SECRET : wp_salt('auth');
        $token = base64_encode(json_encode($payload)) . '.' . hash_hmac('sha256', json_encode($payload), $secret);

        return $this->respondSuccess([
            'userId' => $userId,
            'email' => $email,
            'name' => $user->display_name ?: $name ?: $user->user_login,
            'avatar' => get_avatar_url($userId, ['size' => 96]),
            'token' => $token,
            'hairType' => (string) get_user_meta($userId, 'myavana_hair_type', true) ?: $hairType,
            'synced' => true,
        ]);
    }

    /**
     * Convert a WP_Error (with a 'status' entry in its error data) into the
     * standard error response shape, carrying through any extra data fields
     * (field, showSignin, showForgot, attemptsRemaining, ...) the frontend
     * needs for form-level feedback.
     */
    private function fromWpError(\WP_Error $error): \WP_REST_Response {
        $data = $error->get_error_data() ?: [];
        $status = (int) ($data['status'] ?? 400);
        unset($data['status']);

        $response = [
            'success' => false,
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ];

        return new \WP_REST_Response(array_merge($response, $data), $status);
    }
}
