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
        $result = $service->requestPasswordReset((string) ($data['email'] ?? ''));

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
    }

    public function resetPassword(\WP_REST_Request $request): \WP_REST_Response {
        $data = $request->get_json_params() ?: $request->get_params();
        $service = new AuthService();
        $result = $service->resetPassword($data);

        if (is_wp_error($result)) {
            return $this->fromWpError($result);
        }

        return $this->respondSuccess($result);
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
