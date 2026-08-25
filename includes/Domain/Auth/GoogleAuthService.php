<?php
/**
 * Google Sign-In Service - verifies Google ID tokens and finds/creates
 * the corresponding WordPress user.
 *
 * Ported from the legacy plugin's Myavana_Auth_System::handle_google_auth.
 * The Google Client ID is intentionally its own option for this plugin
 * (myavana_next_google_client_id) rather than reading the legacy plugin's
 * option, so this plugin has no runtime dependency on the old one.
 *
 * @package Myavana\Next\Domain\Auth
 */

namespace Myavana\Next\Domain\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class GoogleAuthService {
    private const CLIENT_ID_OPTION = 'myavana_next_google_client_id';
    private const ENABLED_OPTION = 'myavana_next_google_auth_enabled';

    public function isEnabled(): bool {
        return (bool) get_option(self::ENABLED_OPTION, true) && $this->getClientId() !== '';
    }

    public function getClientId(): string {
        return (string) get_option(self::CLIENT_ID_OPTION, '');
    }

    /**
     * Verify a Google ID token credential and sign the matching user in
     * (creating the account first if this is their first Google sign-in).
     *
     * @param string $credential The Google ID token from Google Identity Services.
     * @return array|\WP_Error ['userId', 'message']
     */
    public function authenticate(string $credential) {
        if (!$this->isEnabled()) {
            return new \WP_Error('google_disabled', __('Google sign-in is not configured yet.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        if (empty($credential)) {
            return new \WP_Error('missing_credential', __('Missing Google credential.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        $googleUser = $this->verifyIdToken($credential);
        if (is_wp_error($googleUser)) {
            return $googleUser;
        }

        $user = $this->findOrCreateUser($googleUser);
        if (is_wp_error($user)) {
            return $user;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        $displayName = $user->display_name ?: $user->user_login;

        return [
            'userId' => $user->ID,
            'message' => sprintf(__('Welcome, %s!', 'myavana-hair-journey-next'), $displayName),
        ];
    }

    /**
     * @return array|\WP_Error ['sub','email','email_verified','name','given_name','picture']
     */
    private function verifyIdToken(string $credential) {
        $response = wp_remote_get(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential),
            ['timeout' => 15]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('google_verify_failed', __('Unable to verify your Google account right now. Please try again.', 'myavana-hair-journey-next'), ['status' => 502]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || !is_array($body)) {
            return new \WP_Error('google_invalid_response', __('Google sign-in could not be validated.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        $audience = (string) ($body['aud'] ?? '');
        if ($audience !== $this->getClientId()) {
            return new \WP_Error('google_invalid_audience', __('This Google sign-in token is not for this site.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        $email = sanitize_email($body['email'] ?? '');
        $emailVerifiedRaw = $body['email_verified'] ?? false;
        $emailVerified = in_array($emailVerifiedRaw, [true, 'true', '1', 1], true);

        if (!$email || !$emailVerified) {
            return new \WP_Error('google_email_unverified', __('Your Google account email must be verified before signing in.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        $sub = sanitize_text_field($body['sub'] ?? '');
        if (!$sub) {
            return new \WP_Error('google_missing_subject', __('Google did not return a valid account identifier.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => sanitize_text_field($body['name'] ?? ''),
            'given_name' => sanitize_text_field($body['given_name'] ?? ''),
            'picture' => esc_url_raw($body['picture'] ?? ''),
        ];
    }

    /**
     * @param array $googleUser
     * @return \WP_User|\WP_Error
     */
    private function findOrCreateUser(array $googleUser) {
        $existingBySub = $this->getUserByGoogleSub($googleUser['sub']);
        if ($existingBySub) {
            $this->syncGoogleUserMeta($existingBySub->ID, $googleUser);
            return $existingBySub;
        }

        $existingByEmail = get_user_by('email', $googleUser['email']);
        if ($existingByEmail) {
            $this->syncGoogleUserMeta($existingByEmail->ID, $googleUser);
            return $existingByEmail;
        }

        $username = $this->generateUniqueUsername($googleUser['email']);
        $password = wp_generate_password(24, true, true);
        $userId = wp_create_user($username, $password, $googleUser['email']);

        if (is_wp_error($userId)) {
            return new \WP_Error('create_failed', __('There was an issue creating your account. Please try again.', 'myavana-hair-journey-next'), ['status' => 500]);
        }

        $displayName = $googleUser['name'] ?: ($googleUser['given_name'] ?: $username);
        wp_update_user([
            'ID' => $userId,
            'display_name' => $displayName,
            'first_name' => $googleUser['given_name'] ?: $displayName,
        ]);

        update_user_meta($userId, 'myavana_signup_date', current_time('mysql'));
        update_user_meta($userId, 'myavana_onboarding_status', 'pending');
        update_user_meta($userId, 'myavana_registration_source', 'google');

        $this->syncGoogleUserMeta($userId, $googleUser);

        return get_user_by('id', $userId);
    }

    private function syncGoogleUserMeta(int $userId, array $googleUser): void {
        update_user_meta($userId, 'myavana_google_sub', $googleUser['sub']);
        update_user_meta($userId, 'myavana_google_picture', $googleUser['picture']);
        update_user_meta($userId, 'myavana_auth_provider', 'google');
        update_user_meta($userId, 'myavana_last_google_login', current_time('mysql'));
        // Google already verifies email ownership before issuing a token with
        // email_verified=true, so a successful Google sign-in is proof enough.
        update_user_meta($userId, 'myavana_email_verified', 'yes');
    }

    private function getUserByGoogleSub(string $sub): ?\WP_User {
        $users = get_users([
            'meta_key' => 'myavana_google_sub',
            'meta_value' => $sub,
            'number' => 1,
        ]);
        return $users[0] ?? null;
    }

    private function generateUniqueUsername(string $email): string {
        $base = sanitize_user(current(explode('@', $email)), true);
        $username = $base;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        return $username;
    }
}
