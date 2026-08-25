<?php
/**
 * Auth Service - registration, login, password policy, rate limiting,
 * and email verification.
 *
 * Ported from the legacy plugin's Myavana_Auth_System with the same
 * hardening rules (password complexity, generic login errors, rate
 * limiting, email verification), reshaped into a framework-thin service
 * that returns data or WP_Error instead of writing JSON responses itself.
 *
 * @package Myavana\Next\Domain\Auth
 */

namespace Myavana\Next\Domain\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class AuthService {
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 15 * MINUTE_IN_SECONDS;
    private const MAX_RESET_REQUESTS = 3;
    private const RESET_REQUEST_WINDOW = 15 * MINUTE_IN_SECONDS;
    private const RESET_TOKEN_TTL = HOUR_IN_SECONDS;

    /**
     * The site's own transactional emails (verification, password reset)
     * must always claim to be from support@myavana.com, regardless of
     * whatever address the WP Mail SMTP plugin (or any other mail plugin)
     * happens to be configured with at the time — that dashboard setting
     * has drifted between a personal testing inbox and this address before,
     * which silently changed who these emails appeared to come from.
     */
    private const MAIL_FROM_ADDRESS = 'support@myavana.com';

    /**
     * Register a new user with email + password.
     *
     * @param array $data ['name' => string, 'email' => string, 'password' => string, 'terms' => bool]
     * @return array|\WP_Error ['userId', 'message', 'emailVerified']
     */
    public function register(array $data) {
        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $terms = !empty($data['terms']);

        if (empty($name) || strlen($name) < 2) {
            return new \WP_Error('invalid_name', __('Please enter your full name (at least 2 characters).', 'myavana-hair-journey-next'), ['status' => 400, 'field' => 'name']);
        }

        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'myavana-hair-journey-next'), ['status' => 400, 'field' => 'email']);
        }

        $passwordError = $this->validatePasswordStrength($password);
        if ($passwordError) {
            return new \WP_Error('weak_password', $passwordError, ['status' => 400, 'field' => 'password']);
        }

        if (!$terms) {
            return new \WP_Error('terms_required', __('Please accept the Terms of Service and Privacy Policy to continue.', 'myavana-hair-journey-next'), ['status' => 400, 'field' => 'terms']);
        }

        if (email_exists($email)) {
            return new \WP_Error('email_exists', __('This email is already registered. Please sign in instead.', 'myavana-hair-journey-next'), ['status' => 409, 'field' => 'email', 'showSignin' => true]);
        }

        $username = $this->generateUniqueUsername($email);
        $userId = wp_create_user($username, $password, $email);

        if (is_wp_error($userId)) {
            return new \WP_Error('create_failed', __('There was an issue creating your account. Please try again.', 'myavana-hair-journey-next'), ['status' => 500]);
        }

        wp_update_user(['ID' => $userId, 'display_name' => $name, 'first_name' => $name]);
        update_user_meta($userId, 'myavana_signup_date', current_time('mysql'));
        update_user_meta($userId, 'myavana_email_verified', 'no');
        update_user_meta($userId, 'myavana_onboarding_status', 'pending');

        $this->sendVerificationEmail($userId);

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);

        $firstName = explode(' ', $name)[0];

        return [
            'userId' => $userId,
            'message' => sprintf(
                /* translators: 1: first name, 2: email address */
                __("Welcome to MYAVANA, %1\$s! We've sent a verification link to %2\$s — confirm it when you get a chance.", 'myavana-hair-journey-next'),
                $firstName,
                $email
            ),
            'emailVerified' => false,
        ];
    }

    /**
     * Authenticate a user by login (email or username) + password.
     *
     * @param array $data ['login' => string, 'password' => string, 'remember' => bool]
     * @return array|\WP_Error ['userId', 'message']
     */
    public function login(array $data) {
        $login = sanitize_text_field($data['login'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $remember = !empty($data['remember']);

        if (empty($login) || empty($password)) {
            return new \WP_Error('missing_credentials', __('Please enter your email/username and password.', 'myavana-hair-journey-next'), ['status' => 400]);
        }

        $rateLimitError = $this->checkLoginRateLimit($login);
        if ($rateLimitError) {
            return $rateLimitError;
        }

        // Deliberately do not check whether the account exists before calling
        // wp_authenticate(), and give every failure mode the same generic
        // message. Telling an attacker "no account found" vs "wrong
        // password" lets them enumerate registered emails; one message
        // doesn't.
        //
        // Youzify hooks wp_login_failed and hard-redirects to its own login
        // page on any failed wp_authenticate() call anywhere on the site
        // (it only guards against admin-ajax, not REST requests). Suppress
        // wp_login_failed for the scope of this call so a wrong password
        // returns our JSON response instead of hijacking this REST request
        // with a 302.
        remove_all_actions('wp_login_failed');
        $user = wp_authenticate($login, $password);

        if (is_wp_error($user)) {
            $this->recordFailedLoginAttempt($login);
            $remaining = $this->getRemainingAttempts($login);
            $suffix = $remaining > 0 ? sprintf(' (%d attempts remaining)', $remaining) : '';

            return new \WP_Error(
                'invalid_credentials',
                __('The email/username or password you entered is incorrect.', 'myavana-hair-journey-next') . $suffix,
                ['status' => 401, 'field' => 'password', 'attemptsRemaining' => $remaining]
            );
        }

        $this->clearFailedLoginAttempts($login);

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);

        $displayName = $user->display_name ?: $user->user_login;

        return [
            'userId' => $user->ID,
            'message' => sprintf(__('Welcome back, %s!', 'myavana-hair-journey-next'), $displayName),
        ];
    }

    /**
     * Mirrors the requirements shown live in the signup form's client-side
     * strength meter, so the server never accepts a password the UI
     * displayed as failing every requirement.
     */
    public function validatePasswordStrength(string $password): ?string {
        if (strlen($password) < 8) {
            return __('Password must be at least 8 characters long.', 'myavana-hair-journey-next');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return __('Password must include at least one uppercase letter.', 'myavana-hair-journey-next');
        }
        if (!preg_match('/[a-z]/', $password)) {
            return __('Password must include at least one lowercase letter.', 'myavana-hair-journey-next');
        }
        if (!preg_match('/[0-9]/', $password)) {
            return __('Password must include at least one number.', 'myavana-hair-journey-next');
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return __('Password must include at least one special character (!@#$%^&*).', 'myavana-hair-journey-next');
        }
        return null;
    }

    private function checkLoginRateLimit(string $login): ?\WP_Error {
        $ip = $this->getClientIp();
        $ipKey = 'myavana_next_login_attempts_ip_' . md5($ip);
        $userKey = 'myavana_next_login_attempts_user_' . md5(strtolower($login));

        $userAttempts = (int) get_transient($userKey);
        if ($userAttempts >= self::MAX_LOGIN_ATTEMPTS) {
            return new \WP_Error(
                'rate_limited',
                __('Too many failed attempts. Please try again in a few minutes or reset your password.', 'myavana-hair-journey-next'),
                ['status' => 429, 'showForgot' => true]
            );
        }

        $ipAttempts = (int) get_transient($ipKey);
        if ($ipAttempts >= (self::MAX_LOGIN_ATTEMPTS * 2)) {
            return new \WP_Error(
                'rate_limited',
                __('Too many failed attempts from this location. Please try again shortly.', 'myavana-hair-journey-next'),
                ['status' => 429]
            );
        }

        return null;
    }

    private function recordFailedLoginAttempt(string $login): void {
        $ip = $this->getClientIp();
        $ipKey = 'myavana_next_login_attempts_ip_' . md5($ip);
        $userKey = 'myavana_next_login_attempts_user_' . md5(strtolower($login));

        $ipAttempts = (int) get_transient($ipKey);
        set_transient($ipKey, $ipAttempts + 1, self::ATTEMPT_WINDOW);

        $userAttempts = (int) get_transient($userKey);
        set_transient($userKey, $userAttempts + 1, self::ATTEMPT_WINDOW);
    }

    private function clearFailedLoginAttempts(string $login): void {
        $userKey = 'myavana_next_login_attempts_user_' . md5(strtolower($login));
        delete_transient($userKey);
    }

    private function getRemainingAttempts(string $login): int {
        $userKey = 'myavana_next_login_attempts_user_' . md5(strtolower($login));
        $attempts = (int) get_transient($userKey);
        return max(0, self::MAX_LOGIN_ATTEMPTS - $attempts);
    }

    private function getClientIp(): string {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
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

    // =========================
    // EMAIL VERIFICATION
    // =========================

    public function sendVerificationEmail(int $userId): bool {
        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        $token = wp_generate_password(32, false);
        update_user_meta($userId, 'myavana_email_verification_token', $token);
        update_user_meta($userId, 'myavana_email_verification_sent', current_time('mysql'));

        $verifyUrl = add_query_arg([
            'myavana_next_verify_email' => '1',
            'uid' => $userId,
            'token' => $token,
        ], home_url('/'));

        $siteName = get_bloginfo('name');
        $subject = __('Confirm your MYAVANA email address', 'myavana-hair-journey-next');
        $message = $this->getVerificationEmailTemplate($user, $verifyUrl, $siteName);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $siteName . ' <' . self::MAIL_FROM_ADDRESS . '>',
        ];

        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    public function verifyEmailToken(int $userId, string $token): bool {
        $stored = $userId ? get_user_meta($userId, 'myavana_email_verification_token', true) : '';
        if (!$userId || !$token || !$stored || !hash_equals($stored, $token)) {
            return false;
        }

        update_user_meta($userId, 'myavana_email_verified', 'yes');
        delete_user_meta($userId, 'myavana_email_verification_token');
        return true;
    }

    /**
     * @return array|\WP_Error
     */
    public function resendVerification(int $userId) {
        if (get_user_meta($userId, 'myavana_email_verified', true) === 'yes') {
            return ['message' => __('Your email is already verified.', 'myavana-hair-journey-next')];
        }

        $sent = $this->sendVerificationEmail($userId);
        if (!$sent) {
            return new \WP_Error('send_failed', __('Unable to send verification email right now. Please try again shortly.', 'myavana-hair-journey-next'), ['status' => 500]);
        }

        return ['message' => __('Verification email sent! Please check your inbox.', 'myavana-hair-journey-next')];
    }

    // =========================
    // PASSWORD RESET
    // =========================

    /**
     * Start a password reset for the given email. Always returns a
     * success-shaped message regardless of whether the email is registered,
     * so this endpoint can't be used to enumerate accounts.
     *
     * @return array|\WP_Error
     */
    public function requestPasswordReset(string $email) {
        $email = sanitize_email($email);
        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'myavana-hair-journey-next'), ['status' => 400, 'field' => 'email']);
        }

        $rateLimitError = $this->checkResetRateLimit($email);
        if ($rateLimitError) {
            return $rateLimitError;
        }
        $this->recordResetRequest($email);

        $genericResult = ['message' => __("If an account exists for that email, we've sent a link to reset the password.", 'myavana-hair-journey-next')];

        $user = get_user_by('email', $email);
        if (!$user) {
            return $genericResult;
        }

        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'myavana_password_reset_token', $token);
        update_user_meta($user->ID, 'myavana_password_reset_sent', time());

        $resetUrl = add_query_arg([
            'myavana_next_reset_password' => '1',
            'uid' => $user->ID,
            'token' => $token,
        ], home_url('/'));

        $this->sendPasswordResetEmail($user, $resetUrl);

        return $genericResult;
    }

    /**
     * Consume a password reset token and set a new password.
     *
     * @param array $data ['userId' => int, 'token' => string, 'password' => string]
     * @return array|\WP_Error ['userId', 'message']
     */
    public function resetPassword(array $data) {
        $userId = absint($data['userId'] ?? 0);
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $invalidLinkError = new \WP_Error(
            'invalid_reset_link',
            __('This password reset link is invalid or has expired. Please request a new one.', 'myavana-hair-journey-next'),
            ['status' => 400]
        );

        if (!$userId || !$token) {
            return $invalidLinkError;
        }

        $stored = get_user_meta($userId, 'myavana_password_reset_token', true);
        $sentAt = (int) get_user_meta($userId, 'myavana_password_reset_sent', true);

        if (!$stored || !$sentAt || !hash_equals($stored, $token) || (time() - $sentAt) > self::RESET_TOKEN_TTL) {
            return $invalidLinkError;
        }

        $passwordError = $this->validatePasswordStrength($password);
        if ($passwordError) {
            return new \WP_Error('weak_password', $passwordError, ['status' => 400, 'field' => 'password']);
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return $invalidLinkError;
        }

        wp_set_password($password, $userId);
        delete_user_meta($userId, 'myavana_password_reset_token');
        delete_user_meta($userId, 'myavana_password_reset_sent');

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);

        return [
            'userId' => $userId,
            'message' => __('Your password has been reset. Welcome back!', 'myavana-hair-journey-next'),
        ];
    }

    private function checkResetRateLimit(string $email): ?\WP_Error {
        $key = 'myavana_next_reset_attempts_' . md5(strtolower($email));
        $attempts = (int) get_transient($key);
        if ($attempts >= self::MAX_RESET_REQUESTS) {
            return new \WP_Error(
                'rate_limited',
                __('Too many reset requests. Please check your inbox or try again shortly.', 'myavana-hair-journey-next'),
                ['status' => 429]
            );
        }
        return null;
    }

    private function recordResetRequest(string $email): void {
        $key = 'myavana_next_reset_attempts_' . md5(strtolower($email));
        $attempts = (int) get_transient($key);
        set_transient($key, $attempts + 1, self::RESET_REQUEST_WINDOW);
    }

    private function sendPasswordResetEmail(\WP_User $user, string $resetUrl): bool {
        $siteName = get_bloginfo('name');
        $subject = __('Reset your MYAVANA password', 'myavana-hair-journey-next');
        $message = $this->getPasswordResetEmailTemplate($user, $resetUrl, $siteName);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $siteName . ' <' . self::MAIL_FROM_ADDRESS . '>',
        ];

        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    private function getPasswordResetEmailTemplate(\WP_User $user, string $resetUrl, string $siteName): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html__('Reset your password', 'myavana-hair-journey-next'); ?> - <?php echo esc_html($siteName); ?></title>
            <style>
                body { font-family: 'Archivo', Arial, sans-serif; background: #f5f5f7; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #e7a690 0%, #fce5d7 100%); padding: 40px 20px; text-align: center; color: white; }
                .logo { font-size: 28px; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; }
                .content { padding: 40px 30px; }
                .button { display: inline-block; background: #e7a690; color: white !important; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; text-transform: uppercase; margin: 20px 0; }
                .footer { background: #f5f5f7; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">MYAVANA</div>
                    <p style="margin: 0; font-size: 18px;"><?php echo esc_html__('Reset your password', 'myavana-hair-journey-next'); ?></p>
                </div>
                <div class="content">
                    <p><?php echo esc_html(sprintf(__('Hi %s,', 'myavana-hair-journey-next'), $user->display_name ?: $user->user_login)); ?></p>
                    <p><?php echo esc_html__('We received a request to reset the password for your MYAVANA account. Click below to choose a new one.', 'myavana-hair-journey-next'); ?></p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($resetUrl); ?>" class="button"><?php echo esc_html__('Reset My Password', 'myavana-hair-journey-next'); ?></a>
                    </p>
                    <p><small><?php echo esc_html__("This link expires in 1 hour. If you didn't request a password reset, you can safely ignore this email — your password won't change.", 'myavana-hair-journey-next'); ?></small></p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html__('This email was sent from MYAVANA Hair Journey', 'myavana-hair-journey-next'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }

    private function getVerificationEmailTemplate(\WP_User $user, string $verifyUrl, string $siteName): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html__('Confirm your email', 'myavana-hair-journey-next'); ?> - <?php echo esc_html($siteName); ?></title>
            <style>
                body { font-family: 'Archivo', Arial, sans-serif; background: #f5f5f7; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #e7a690 0%, #fce5d7 100%); padding: 40px 20px; text-align: center; color: white; }
                .logo { font-size: 28px; font-weight: 900; text-transform: uppercase; margin-bottom: 10px; }
                .content { padding: 40px 30px; }
                .button { display: inline-block; background: #e7a690; color: white !important; padding: 16px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; text-transform: uppercase; margin: 20px 0; }
                .footer { background: #f5f5f7; padding: 20px; text-align: center; color: #666; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">MYAVANA</div>
                    <p style="margin: 0; font-size: 18px;"><?php echo esc_html__('Confirm your email address', 'myavana-hair-journey-next'); ?></p>
                </div>
                <div class="content">
                    <p><?php echo esc_html(sprintf(__('Hi %s,', 'myavana-hair-journey-next'), $user->display_name ?: $user->user_login)); ?></p>
                    <p><?php echo esc_html__('Welcome to MYAVANA! Please confirm this is your email address so we can keep your hair journey and progress safe.', 'myavana-hair-journey-next'); ?></p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($verifyUrl); ?>" class="button"><?php echo esc_html__('Confirm My Email', 'myavana-hair-journey-next'); ?></a>
                    </p>
                    <p><small><?php echo esc_html__("This link doesn't expire, but if you didn't create a MYAVANA account, you can safely ignore this email.", 'myavana-hair-journey-next'); ?></small></p>
                </div>
                <div class="footer">
                    <p><?php echo esc_html__('This email was sent from MYAVANA Hair Journey', 'myavana-hair-journey-next'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }
}
