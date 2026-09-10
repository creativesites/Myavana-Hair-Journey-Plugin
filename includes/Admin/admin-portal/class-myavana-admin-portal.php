<?php
if (!defined('ABSPATH')) {
    exit;
}

class Myavana_Admin_Portal
{
    const SETUP_VERSION = '1.0.0';
    const SETUP_OPTION = 'myavana_admin_portal_setup_version';
    const PAGE_SLUG = 'admin-portal';
    const PAGE_TITLE = 'Admin Portal';
    const REST_NAMESPACE = 'myavana-admin/v1';

    public function __construct()
    {
        add_action('init', [$this, 'register_shortcode']);
        add_action('init', [$this, 'maybe_run_setup'], 20);
        add_action('template_redirect', [$this, 'guard_portal_access'], 0);
        add_action('init', [$this, 'enforce_user_suspension']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'register_launch_link'], 90);
        add_filter('heartbeat_received', [$this, 'heartbeat_check_reports'], 10, 2);
        add_action('wp_footer', [$this, 'render_global_announcement']);
    }

    public static function activate()
    {
        Myavana_Admin_Portal_Permissions::activate();
        Myavana_Admin_Portal_Audit_Log::activate();
        self::ensure_portal_page();
        self::ensure_feature_flag_defaults();
        update_option(self::SETUP_OPTION, self::SETUP_VERSION, false);
    }

    public function maybe_run_setup()
    {
        if (get_option(self::SETUP_OPTION) === self::SETUP_VERSION) {
            self::ensure_feature_flag_defaults();
            return;
        }

        self::activate();
    }

    public function register_shortcode()
    {
        add_shortcode('myavana_admin_portal', [$this, 'render_shortcode']);
    }

    public function render_shortcode()
    {
        if (!Myavana_Admin_Portal_Permissions::current_user_can_access_portal()) {
            return '<div class="myavana-admin-portal-denied">You do not have permission to view this portal.</div>';
        }

        ob_start();
        $template = MYAVANA_DIR . 'templates/pages/admin-portal.php';
        if (file_exists($template)) {
            include $template;
        }
        return ob_get_clean();
    }

    public function enqueue_assets()
    {
        if (!$this->is_portal_request()) {
            return;
        }

        $sections = [];
        if (Myavana_Admin_Portal_Permissions::current_user_can_view_analytics()) {
            $sections[] = ['id' => 'overview', 'label' => 'Overview'];
            $sections[] = ['id' => 'ai', 'label' => 'AI'];
            $sections[] = ['id' => 'operations', 'label' => 'Operations'];
            $sections[] = ['id' => 'journey', 'label' => 'Journey'];
            $sections[] = ['id' => 'community', 'label' => 'Community'];
            $sections[] = ['id' => 'gamification', 'label' => 'Gamification'];
        }
        if (Myavana_Admin_Portal_Permissions::current_user_can_manage_users()) {
            $sections[] = ['id' => 'users', 'label' => 'Users'];
        }
        if (Myavana_Admin_Portal_Permissions::current_user_can_manage_settings()) {
            $sections[] = ['id' => 'settings', 'label' => 'Settings'];
            $sections[] = ['id' => 'control', 'label' => 'Control'];
        }
        if (Myavana_Admin_Portal_Permissions::current_user_can_view_audit_log()) {
            $sections[] = ['id' => 'audit', 'label' => 'Audit'];
        }

        $css_path = MYAVANA_DIR . 'assets/css/admin-portal.css';
        $js_path = MYAVANA_DIR . 'assets/js/admin-portal.js';
        $css_version = file_exists($css_path) ? (string) filemtime($css_path) : '1.0.0';
        $js_version = file_exists($js_path) ? (string) filemtime($js_path) : '1.0.0';

        wp_enqueue_style('myavana-admin-portal', MYAVANA_URL . 'assets/css/admin-portal.css', [], $css_version);
        wp_enqueue_script('myavana-admin-portal', MYAVANA_URL . 'assets/js/admin-portal.js', [], $js_version, true);

        wp_localize_script('myavana-admin-portal', 'myavanaAdminPortal', [
            'restRoot' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'portalUrl' => self::get_portal_url(),
            'homeUrl' => home_url('/'),
            'currentUser' => [
                'id' => get_current_user_id(),
                'name' => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
            ],
            'capabilities' => Myavana_Admin_Portal_Permissions::get_capability_matrix(),
            'sections' => $sections,
        ]);
    }

    public function guard_portal_access()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (!$this->is_portal_request()) {
            return;
        }

        if (!is_user_logged_in()) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $request_path = parse_url($request_uri, PHP_URL_PATH) ?: '/';
            $request_query = parse_url($request_uri, PHP_URL_QUERY);

            $requested_url = home_url($request_path);
            if (!empty($request_query)) {
                $requested_url .= '?' . $request_query;
            }

            $redirect_url = add_query_arg([
                'auth' => '1',
                'redirect_to' => rawurlencode($requested_url),
            ], home_url('/'));

            wp_safe_redirect($redirect_url);
            exit;
        }

        if (!Myavana_Admin_Portal_Permissions::current_user_can_access_portal()) {
            wp_safe_redirect(add_query_arg('portal_access', 'denied', home_url('/')));
            exit;
        }
    }

    public function enforce_user_suspension()
    {
        if (is_user_logged_in() && !is_admin() && !wp_doing_ajax()) {
            $user_id = get_current_user_id();
            if (get_user_meta($user_id, 'myavana_user_suspended', true)) {
                wp_logout();
                wp_die('Your account has been temporarily suspended. Please contact support for more details.', 'Account Suspended', ['response' => 403]);
            }
        }
    }

    public function heartbeat_check_reports($response, $data)
    {
        if (empty($data['myavana_check_reports'])) {
            return $response;
        }

        if (!$this->can_manage_support()) {
            return $response;
        }

        $last_check = absint($data['myavana_check_reports']);
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_ci_content_reports';
        
        $has_new = false;
        if ((bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE UNIX_TIMESTAMP(created_at) > %d AND status = 'pending'",
                $last_check
            ));
            $has_new = $count > 0;
        }

        $response['myavana_new_reports'] = $has_new;
        return $response;
    }

    public function render_global_announcement()
    {
        $announcement = trim((string) get_option('myavana_global_announcement', ''));
        if ($announcement === '') {
            return;
        }

        ?>
        <div id="myavana-global-announcement" class="myavana-global-announcement" role="status" aria-live="polite">
            <div class="myavana-global-announcement__inner">
                <div class="myavana-global-announcement__copy"><?php echo wp_kses_post(wpautop($announcement)); ?></div>
                <button type="button" class="myavana-global-announcement__close" aria-label="Dismiss announcement" onclick="document.getElementById('myavana-global-announcement')?.remove()">Close</button>
            </div>
        </div>
        <style>
            .myavana-global-announcement {
                position: fixed;
                right: 18px;
                bottom: 18px;
                z-index: 100001;
                width: min(420px, calc(100vw - 24px));
                border-radius: 18px;
                background: rgba(34, 35, 35, 0.94);
                color: #fff;
                box-shadow: 0 18px 42px rgba(0, 0, 0, 0.22);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
            }
            .myavana-global-announcement__inner {
                display: flex;
                gap: 14px;
                align-items: flex-start;
                padding: 16px 18px;
            }
            .myavana-global-announcement__copy {
                flex: 1;
                min-width: 0;
                font-size: 14px;
                line-height: 1.6;
            }
            .myavana-global-announcement__copy p {
                margin: 0;
            }
            .myavana-global-announcement__copy p + p {
                margin-top: 8px;
            }
            .myavana-global-announcement__close {
                border: 1px solid rgba(255, 255, 255, 0.18);
                background: rgba(255, 255, 255, 0.08);
                color: #fff;
                border-radius: 999px;
                padding: 8px 12px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                flex-shrink: 0;
            }
            @media (max-width: 767px) {
                .myavana-global-announcement {
                    right: 12px;
                    left: 12px;
                    bottom: calc(12px + env(safe-area-inset-bottom, 0px));
                    width: auto;
                }
                .myavana-global-announcement__inner {
                    flex-direction: column;
                }
            }
        </style>
        <?php
    }

    public function register_rest_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/overview', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_overview'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_users'],
            'permission_callback' => [$this, 'can_manage_users'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_user_detail'],
            'permission_callback' => [$this, 'can_manage_users'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)/reset-onboarding', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reset_user_onboarding'],
            'permission_callback' => [$this, 'can_manage_support'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)/send-reset-link', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'send_user_reset_link'],
            'permission_callback' => [$this, 'can_manage_support'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)/suspend', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'suspend_user'],
            'permission_callback' => [$this, 'can_manage_support'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)/award-points', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'award_user_points'],
            'permission_callback' => [$this, 'can_manage_support'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/users/(?P<id>\d+)/impersonate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'impersonate_user'],
            'permission_callback' => [$this, 'can_manage_users'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/ai', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_ai_summary'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/operations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_operations_summary'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/journey', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_journey_summary'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/community', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_community_summary'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/community/reports/(?P<id>\d+)/resolve', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resolve_community_report'],
            'permission_callback' => [$this, 'can_manage_support'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/gamification', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_gamification_summary'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/gamification/ledger', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_gamification_ledger'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/gamification/config', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_gamification_config'],
            'permission_callback' => [$this, 'can_manage_settings'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/control', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_control_center'],
            'permission_callback' => [$this, 'can_manage_settings'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_manage_settings'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'can_manage_settings'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/audit', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_audit_logs'],
            'permission_callback' => [$this, 'can_view_audit_log'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/export', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'export_data_csv'],
            'permission_callback' => [$this, 'can_view_analytics'],
        ]);
    }

    public function get_overview(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');

        $cache_key = 'myavana_admin_overview_' . md5($date_from . $date_to . $role);
        $payload = empty($request->get_param('refresh')) ? get_transient($cache_key) : false;

        if (false === $payload) {
            $overview = Myavana_Analytics_Model::get_overview_metrics($date_from, $date_to, $role);
            $active_users = Myavana_Analytics_Model::get_active_user_trends($role);
            $funnel = Myavana_Analytics_Model::get_funnel_data($date_from, $date_to, $role);
            $behavior = Myavana_Analytics_Model::get_behavior_metrics($date_from, $date_to, $role);
            $ai = Myavana_Analytics_Model::get_ai_component_metrics($date_from, $date_to, $role);
            $moderation = Myavana_Analytics_Model::get_moderation_reports();

            $payload = [
                'range' => [
                'date_from' => $date_from,
                'date_to' => $date_to,
                'role' => $role,
            ],
            'cards' => [
                [
                    'id' => 'members',
                    'label' => 'Total Members',
                    'value' => number_format_i18n((int) $overview['total_users']),
                    'meta' => sprintf('+%s new in range', number_format_i18n((int) $overview['new_users'])),
                ],
                [
                    'id' => 'visitors',
                    'label' => 'Unique Visitors',
                    'value' => number_format_i18n((int) $overview['unique_visitors']),
                    'meta' => sprintf('%s total visits', number_format_i18n((int) $overview['total_visits'])),
                ],
                [
                    'id' => 'ai-components',
                    'label' => 'AI Components',
                    'value' => number_format_i18n((int) $ai['generated']),
                    'meta' => sprintf('%s applied', number_format_i18n((int) $ai['applied'])),
                ],
                [
                    'id' => 'moderation',
                    'label' => 'Pending Reports',
                    'value' => number_format_i18n((int) $moderation['pending']),
                    'meta' => sprintf('%s tracked actions', number_format_i18n((int) $behavior['tracked_actions'])),
                ],
            ],
            'highlights' => [
                'active_users' => $active_users,
                'section_usage' => $overview['section_usage'],
                'funnel' => array_slice((array) $funnel, 0, 5),
                'behavior' => [
                    'total_events' => (int) $behavior['total_events'],
                    'tracked_sessions' => (int) $behavior['tracked_sessions'],
                    'avg_section_dwell' => round((float) $behavior['avg_section_dwell'], 1),
                    'top_actions' => array_slice((array) $behavior['top_actions'], 0, 5),
                ],
                'ai' => [
                    'generated' => (int) $ai['generated'],
                    'saved' => (int) $ai['saved'],
                    'dismissed' => (int) $ai['dismissed'],
                    'top_pages' => array_slice((array) $ai['top_pages'], 0, 5),
                ],
            ],
        ];
            set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        }

        return $this->success_response($payload);
    }

    public function get_users(WP_REST_Request $request)
    {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(25, absint($request->get_param('per_page') ?: 8)));
        $search = sanitize_text_field($request->get_param('search') ?: '');

        $query_args = [
            'number' => $per_page,
            'paged' => $page,
            'orderby' => 'registered',
            'order' => 'DESC',
            'count_total' => true,
        ];

        if ($search !== '') {
            $query_args['search'] = '*' . $search . '*';
            $query_args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $query = new WP_User_Query($query_args);
        $users = [];

        foreach ((array) $query->get_results() as $user) {
            $users[] = $this->format_user_summary($user);
        }

        return $this->success_response([
            'items' => $users,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => (int) $query->get_total(),
                'total_pages' => (int) ceil(max(1, $query->get_total()) / $per_page),
            ],
        ]);
    }

    public function get_user_detail(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }

        $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
        $routines = get_user_meta($user_id, 'myavana_current_routine', true);
        $analysis_history = get_user_meta($user_id, 'myavana_hair_analysis_history', true);
        $profile_row = $this->get_profile_row($user_id);
        $last_activity = $this->get_user_last_activity($user_id);
        $entry_count = count_user_posts($user_id, 'hair_journey_entry', true);
        $audit = Myavana_Admin_Portal_Audit_Log::get_recent_logs(1, 20);

        $user_audit = array_values(array_filter($audit['items'], static function ($item) use ($user_id) {
            $context_user_id = isset($item['context']['user_id']) ? absint($item['context']['user_id']) : 0;
            return ((int) $item['target_id'] === $user_id && $item['target_type'] === 'user') || $context_user_id === $user_id;
        }));

        return $this->success_response([
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'roles' => array_values((array) $user->roles),
            'registered' => $user->user_registered,
            'suspended' => (bool) get_user_meta($user_id, 'myavana_user_suspended', true),
            'onboarding_status' => get_user_meta($user_id, 'myavana_onboarding_status', true) ?: 'pending',
            'show_onboarding' => (bool) get_user_meta($user_id, 'myavana_show_onboarding', true),
            'profile' => [
                'hair_type' => $profile_row['hair_type'] ?? '',
                'location' => $profile_row['location'] ?? '',
                'stage' => $profile_row['hair_journey_stage'] ?? '',
            ],
            'last_activity' => $last_activity,
            'journey_stats' => [
                'entries' => (int) $entry_count,
                'goals' => $this->count_collection_items($goals),
                'routines' => $this->count_collection_items($routines),
                'analysis_runs' => $this->count_collection_items($analysis_history),
            ],
            'audit' => array_slice($user_audit, 0, 8),
        ]);
    }

    public function reset_user_onboarding(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }

        update_user_meta($user_id, 'myavana_onboarding_status', 'pending');
        update_user_meta($user_id, 'myavana_show_onboarding', true);
        delete_user_meta($user_id, 'myavana_onboarding_started');
        delete_user_meta($user_id, 'myavana_onboarding_step');

        Myavana_Admin_Portal_Audit_Log::log(
            'reset_onboarding',
            ['user_id' => $user_id, 'email' => $user->user_email],
            'user',
            $user_id,
            'success'
        );

        return $this->success_response([
            'user_id' => $user_id,
            'onboarding_status' => 'pending',
            'show_onboarding' => true,
        ], 'Onboarding reset.');
    }

    public function send_user_reset_link(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return $this->error_response('Unable to generate a reset link for this account.', 400);
        }

        $reset_url = add_query_arg([
            'key' => $key,
            'login' => rawurlencode($user->user_login),
        ], home_url('/password-reset/'));

        $site_name = get_bloginfo('name');
        $subject = 'Reset Your MYAVANA Password';
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . wp_parse_url(home_url(), PHP_URL_HOST) . '>',
        ];

        $message = $this->build_password_reset_email($user, $reset_url, $site_name);
        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if (!$sent) {
            return $this->error_response('Unable to send reset email. Please try again later.', 500);
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'send_password_reset',
            ['user_id' => $user_id, 'email' => $user->user_email],
            'user',
            $user_id,
            'success'
        );

        return $this->success_response([
            'user_id' => $user_id,
            'email' => $user->user_email,
        ], 'Password reset email sent.');
    }

    public function suspend_user(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }

        $is_suspended = (bool) get_user_meta($user_id, 'myavana_user_suspended', true);
        $new_status = !$is_suspended;
        update_user_meta($user_id, 'myavana_user_suspended', $new_status);

        Myavana_Admin_Portal_Audit_Log::log(
            $new_status ? 'suspend_user' : 'unsuspend_user',
            ['user_id' => $user_id, 'email' => $user->user_email],
            'user',
            $user_id,
            'success'
        );

        return $this->success_response([
            'user_id' => $user_id,
            'suspended' => $new_status,
        ], $new_status ? 'User account suspended.' : 'User account restored.');
    }

    public function award_user_points(WP_REST_Request $request)
    {
        global $wpdb;
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);
        $points = absint($request->get_param('points'));
        $reason = sanitize_text_field($request->get_param('reason') ?: 'Admin bonus');

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }
        if ($points <= 0) {
            return $this->error_response('Invalid points amount.', 400);
        }

        $table = $wpdb->prefix . 'myavana_points_history';
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'points' => $points,
                'reason' => 'admin_award',
                'description' => $reason,
                'created_at' => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s', '%s']);
            
            if (class_exists('Myavana_Gamification') && method_exists('Myavana_Gamification', 'award_points')) {
                Myavana_Gamification::award_points($user_id, 'admin_award', $points);
            }
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'award_points',
            ['user_id' => $user_id, 'points' => $points, 'reason' => $reason],
            'user',
            $user_id,
            'success'
        );

        return $this->success_response([
            'user_id' => $user_id,
            'awarded' => $points,
        ], "Successfully awarded {$points} points.");
    }

    public function impersonate_user(WP_REST_Request $request)
    {
        $user_id = absint($request['id']);
        $user = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return $this->error_response('User not found.', 404);
        }

        if (!current_user_can('manage_options')) {
            return $this->error_response('Insufficient permissions to impersonate users.', 403);
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'impersonate_user',
            ['user_id' => $user_id, 'email' => $user->user_email],
            'user',
            $user_id,
            'success'
        );

        wp_clear_auth_cookie();
        wp_set_auth_cookie($user_id);

        return $this->success_response([
            'redirect_url' => home_url('/profile/'),
        ], 'Session swapped.');
    }

    public function get_ai_summary(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');

        $cache_key = 'myavana_admin_ai_' . md5($date_from . $date_to . $role);
        $payload = empty($request->get_param('refresh')) ? get_transient($cache_key) : false;

        if (false === $payload) {
            $ai = Myavana_Analytics_Model::get_ai_component_metrics($date_from, $date_to, $role);
            $behavior = Myavana_Analytics_Model::get_behavior_metrics($date_from, $date_to, $role);

            $payload = [
                'range' => [
                'date_from' => $date_from,
                'date_to' => $date_to,
            ],
            'summary' => [
                'generated' => (int) $ai['generated'],
                'active' => (int) $ai['active'],
                'saved' => (int) $ai['saved'],
                'dismissed' => (int) $ai['dismissed'],
                'applied' => (int) $ai['applied'],
                'impressions' => (int) $ai['impressions'],
            ],
            'top_pages' => array_slice((array) $ai['top_pages'], 0, 6),
            'top_types' => array_slice((array) $ai['top_types'], 0, 6),
            'recent_components' => array_slice((array) $ai['recent_components'], 0, 8),
            'behavior' => [
                'top_actions' => array_values(array_filter((array) $behavior['top_actions'], static function ($action) {
                    return strpos((string) ($action['event_name'] ?? ''), 'ai_') === 0 || strpos((string) ($action['event_name'] ?? ''), 'ai') !== false;
                })),
            ],
        ];
            set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        }

        return $this->success_response($payload);
    }

    public function get_operations_summary(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');

        $cache_key = 'myavana_admin_ops_' . md5($date_from . $date_to . $role);
        $payload = empty($request->get_param('refresh')) ? get_transient($cache_key) : false;

        if (false === $payload) {
            $active_users = Myavana_Analytics_Model::get_active_users($date_from, $date_to, $role);
            $top_pages = Myavana_Analytics_Model::get_top_pages($date_from, $date_to, $role);
            $engagement = Myavana_Analytics_Model::get_engagement_metrics($date_from, $date_to, $role);
            $moderation = Myavana_Analytics_Model::get_moderation_reports();

            $payload = [
                'summary' => [
                'avg_time_site' => round((float) $engagement['avg_time_site'], 1),
                'session_depth' => round((float) $engagement['session_depth'], 1),
                'returning_session_rate' => round((float) $engagement['returning_session_rate'], 1),
                'pending_reports' => (int) $moderation['pending'],
            ],
            'active_users' => array_map(static function ($row) {
                return [
                    'id' => (int) $row->ID,
                    'display_name' => $row->display_name,
                    'user_email' => $row->user_email,
                    'hair_type' => $row->hair_type,
                    'entries_count' => (int) $row->entries_count,
                    'visits_in_range' => (int) $row->visits_in_range,
                ];
            }, array_slice((array) $active_users, 0, 8)),
            'top_pages' => array_map(static function ($row) {
                return [
                    'path' => $row->path,
                    'visits' => (int) $row->visits,
                    'avg_time' => round((float) $row->avg_time, 1),
                    'last_seen' => $row->last_seen,
                ];
            }, array_slice((array) $top_pages, 0, 8)),
            'recent_reports' => array_slice((array) ($moderation['recent'] ?? []), 0, 6),
        ];
            set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        }

        return $this->success_response($payload);
    }

    public function get_journey_summary(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');

        $cache_key = 'myavana_admin_journey_' . md5($date_from . $date_to . $role);
        $payload = empty($request->get_param('refresh')) ? get_transient($cache_key) : false;

        if (false === $payload) {
            $overview = Myavana_Analytics_Model::get_overview_metrics($date_from, $date_to, $role);
            $funnel = Myavana_Analytics_Model::get_funnel_data($date_from, $date_to, $role);
            $ai_entry_trend = Myavana_Analytics_Model::get_ai_entry_trend($date_from, $date_to, $role);
            $signup_trend = Myavana_Analytics_Model::get_signup_trend($date_from, $date_to, $role);
            $active_users = Myavana_Analytics_Model::get_active_users($date_from, $date_to, $role);

            $payload = [
                'summary' => [
                'analysis_users' => (int) ($overview['analysis_users'] ?? 0),
                'ai_entries' => (int) ($overview['ai_entries'] ?? 0),
                'new_users' => (int) ($overview['new_users'] ?? 0),
                'unique_visitors' => (int) ($overview['unique_visitors'] ?? 0),
            ],
            'funnel' => array_slice((array) $funnel, 0, 5),
            'ai_entry_trend' => array_map(static function ($row) {
                return [
                    'entry_date' => $row->entry_date,
                    'total' => (int) $row->total,
                ];
            }, array_slice((array) $ai_entry_trend, 0, 14)),
            'signup_trend' => array_map(static function ($row) {
                return [
                    'signup_date' => $row->signup_date,
                    'total' => (int) $row->total,
                ];
            }, array_slice((array) $signup_trend, 0, 14)),
            'active_users' => array_map(static function ($row) {
                return [
                    'display_name' => $row->display_name,
                    'entries_count' => (int) $row->entries_count,
                    'visits_in_range' => (int) $row->visits_in_range,
                ];
            }, array_slice((array) $active_users, 0, 8)),
        ];
            set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        }

        return $this->success_response($payload);
    }

    public function get_community_summary(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');

        $cache_key = 'myavana_admin_community_' . md5($date_from . $date_to . $role);
        $payload = empty($request->get_param('refresh')) ? get_transient($cache_key) : false;

        if (false === $payload) {
            $community = Myavana_Analytics_Model::get_community_stats($date_from, $date_to, $role);
            $moderation = Myavana_Analytics_Model::get_moderation_reports();

            $payload = [
                'summary' => [
                'posts_count' => (int) ($community['posts_count'] ?? 0),
                'video_count' => (int) ($community['video_count'] ?? 0),
                'interactions' => (int) ($community['interactions'] ?? 0),
                'pending_reports' => (int) ($moderation['pending'] ?? 0),
            ],
            'top_creators' => array_map(static function ($row) {
                return [
                    'id' => (int) $row->ID,
                    'display_name' => $row->display_name,
                    'posts_count' => (int) $row->posts_count,
                    'engagement' => (int) $row->engagement,
                ];
            }, array_slice((array) ($community['top_creators'] ?? []), 0, 8)),
            'reports' => array_map(static function ($row) {
                if (is_object($row)) {
                    $row = (array) $row;
                }
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'content_type' => $row['content_type'] ?? '',
                    'content_id' => (int) ($row['content_id'] ?? 0),
                    'reason' => $row['reason'] ?? '',
                    'status' => $row['status'] ?? 'pending',
                    'created_at' => $row['created_at'] ?? '',
                ];
            }, array_slice((array) ($moderation['recent'] ?? []), 0, 10)),
        ];
            set_transient($cache_key, $payload, HOUR_IN_SECONDS);
        }

        return $this->success_response($payload);
    }

    public function resolve_community_report(WP_REST_Request $request)
    {
        global $wpdb;

        $report_id = absint($request['id']);
        $status = sanitize_key($request->get_param('status') ?: 'resolved');
        if (!in_array($status, ['resolved', 'dismissed'], true)) {
            $status = 'resolved';
        }

        $table = $wpdb->prefix . 'myavana_ci_content_reports';
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return $this->error_response('Reports table is not available.', 404);
        }

        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $report_id), ARRAY_A);
        if (!is_array($report)) {
            return $this->error_response('Report not found.', 404);
        }

        $updated = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $report_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            return $this->error_response('Unable to update report.', 500);
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'resolve_community_report',
            ['report_id' => $report_id, 'status' => $status],
            'community_report',
            $report_id,
            'success'
        );

        return $this->success_response([
            'id' => $report_id,
            'status' => $status,
        ], 'Report updated.');
    }

    public function get_gamification_summary(WP_REST_Request $request)
    {
        $date_to = $this->sanitize_date($request->get_param('date_to'), wp_date('Y-m-d'));
        $date_from = $this->sanitize_date($request->get_param('date_from'), wp_date('Y-m-d', strtotime('-29 days')));
        $role = sanitize_key($request->get_param('role') ?: 'all');
        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));

        $data = myavana_get_gamification_admin_data($date_from_sql, $date_to_sql, $role);

        return $this->success_response([
            'summary' => $data['metrics'],
            'challenge_rows' => $data['challenge_rows'],
            'reward_reason_rows' => $data['reward_reason_rows'],
            'reward_user_rows' => $data['reward_user_rows'],
            'reward_settings' => Myavana_Gamification::get_reward_settings(),
            'challenge_definitions' => Myavana_Gamification::get_challenge_definitions(),
            'reward_field_labels' => $this->get_reward_field_labels(),
            'challenge_metric_options' => $this->get_challenge_metric_options(),
        ]);
    }

    public function update_gamification_config(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $changed = [];

        if (isset($payload['reward_settings']) && is_array($payload['reward_settings'])) {
            $settings = myavana_sanitize_gamification_reward_settings($payload['reward_settings']);
            update_option('myavana_gamification_reward_settings', $settings, false);
            $changed[] = 'reward_settings';
        }

        if (isset($payload['challenges']) && is_array($payload['challenges'])) {
            $challenges = myavana_sanitize_gamification_challenges($payload['challenges']);
            update_option('myavana_gamification_active_challenges', $challenges, false);
            $changed[] = 'challenges';
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'update_gamification_config',
            ['changed' => $changed],
            'gamification',
            0,
            'success'
        );

        return $this->success_response([
            'changed' => $changed,
        ], 'Gamification settings updated.');
    }

    public function get_control_center(WP_REST_Request $request)
    {
        global $wpdb;

        $components_table = $wpdb->prefix . 'myavana_ai_components';
        $events_table = $wpdb->prefix . 'myavana_site_events';
        $reports_table = $wpdb->prefix . 'myavana_ci_content_reports';
        $history_table = $wpdb->prefix . 'myavana_points_history';

        $recent_components = [];
        if ($this->table_exists($components_table)) {
            $recent_components = $wpdb->get_results(
                "SELECT page_context, component_type, title, status, model_name, generated_at
                 FROM {$components_table}
                 ORDER BY generated_at DESC, id DESC
                 LIMIT 10",
                ARRAY_A
            );
        }

        $diagnostics = [
            'portal_page_url' => self::get_portal_url(),
            'gemini_model' => class_exists('Myavana_Site_Intelligence') ? Myavana_Site_Intelligence::GEMINI_MODEL : 'gemini-3.1-flash-lite-preview',
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'auth_rewrite_version' => get_option('myavana_auth_rewrite_version', ''),
            'site_intelligence_db_version' => get_option('myavana_site_intelligence_db_version', ''),
            'tables' => [
                'events' => $this->table_exists($events_table),
                'ai_components' => $this->table_exists($components_table),
                'community_reports' => $this->table_exists($reports_table),
                'points_history' => $this->table_exists($history_table),
                'admin_audit' => $this->table_exists($wpdb->prefix . Myavana_Admin_Portal_Audit_Log::TABLE_SUFFIX),
            ],
        ];

        return $this->success_response([
            'diagnostics' => $diagnostics,
            'feature_flags' => [
                'admin_portal_enabled' => (bool) get_option('myavana_admin_portal_enabled', '1'),
                'site_intelligence_enabled' => (bool) get_option('myavana_site_intelligence_enabled', '1'),
                'ai_intelligence_enabled' => (bool) get_option('myavana_ai_intelligence_enabled', '1'),
            ],
            'api_keys' => [
                'gemini' => $this->mask_secret(get_option('myavana_gemini_api_key', '')),
                'openai' => $this->mask_secret(get_option('myavana_openai_api_key', '')),
                'xai' => $this->mask_secret(get_option('myavana_xai_api_key', '')),
            ],
            'recent_components' => $recent_components,
            'audit_preview' => Myavana_Admin_Portal_Audit_Log::get_recent_logs(1, 8),
        ]);
    }

    public function get_gamification_ledger(WP_REST_Request $request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'myavana_points_history';
        
        if (!(bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return $this->success_response(['items' => []]);
        }

        $rows = $wpdb->get_results(
            "SELECT p.*, u.display_name, u.user_email 
             FROM {$table} p 
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id 
             ORDER BY p.created_at DESC 
             LIMIT 100", 
            ARRAY_A
        );

        return $this->success_response(['items' => $rows]);
    }

    public function get_settings(?WP_REST_Request $request = null)
    {
        return $this->success_response([
            'api_keys' => [
                'gemini' => $this->mask_secret(get_option('myavana_gemini_api_key', '')),
                'openai' => $this->mask_secret(get_option('myavana_openai_api_key', '')),
                'xai' => $this->mask_secret(get_option('myavana_xai_api_key', '')),
            ],
            'feature_flags' => [
                'admin_portal_enabled' => (bool) get_option('myavana_admin_portal_enabled', '1'),
                'site_intelligence_enabled' => (bool) get_option('myavana_site_intelligence_enabled', '1'),
                'ai_intelligence_enabled' => (bool) get_option('myavana_ai_intelligence_enabled', '1'),
            ],
        ]);
    }

    public function update_settings(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $changed_keys = [];
        $api_keys = isset($payload['api_keys']) && is_array($payload['api_keys']) ? $payload['api_keys'] : [];
        $feature_flags = isset($payload['feature_flags']) && is_array($payload['feature_flags']) ? $payload['feature_flags'] : [];

        $api_key_map = [
            'gemini' => 'myavana_gemini_api_key',
            'openai' => 'myavana_openai_api_key',
            'xai' => 'myavana_xai_api_key',
        ];

        foreach ($api_key_map as $input_key => $option_key) {
            if (!array_key_exists($input_key, $api_keys)) {
                continue;
            }

            $value = trim((string) $api_keys[$input_key]);
            if ($value === '') {
                continue;
            }

            update_option($option_key, sanitize_text_field($value), false);
            $changed_keys[] = $option_key;
        }

        $feature_flag_map = [
            'admin_portal_enabled' => 'myavana_admin_portal_enabled',
            'site_intelligence_enabled' => 'myavana_site_intelligence_enabled',
            'ai_intelligence_enabled' => 'myavana_ai_intelligence_enabled',
        ];

        foreach ($feature_flag_map as $input_key => $option_key) {
            if (!array_key_exists($input_key, $feature_flags)) {
                continue;
            }

            update_option($option_key, !empty($feature_flags[$input_key]) ? '1' : '0', false);
            $changed_keys[] = $option_key;
        }

        if (array_key_exists('global_announcement', $payload)) {
            update_option('myavana_global_announcement', wp_kses_post($payload['global_announcement']), false);
            $changed_keys[] = 'myavana_global_announcement';
        }

        Myavana_Admin_Portal_Audit_Log::log(
            'update_settings',
            ['changed_keys' => $changed_keys],
            'settings',
            0,
            'success'
        );

        return $this->success_response([
            'changed_keys' => $changed_keys,
        ], 'Settings updated.');
    }

    public function get_audit_logs(WP_REST_Request $request)
    {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = max(1, min(50, absint($request->get_param('per_page') ?: 12)));

        return $this->success_response(Myavana_Admin_Portal_Audit_Log::get_recent_logs($page, $per_page));
    }

    public function export_data_csv(WP_REST_Request $request)
    {
        $type = sanitize_key($request->get_param('type') ?: 'users');
        $filename = 'myavana-export-' . $type . '-' . wp_date('Y-m-d-His') . '.csv';

        switch ($type) {
            case 'users':
                $rows = $this->get_export_users_rows();
                break;

            case 'reports':
                $rows = $this->get_export_reports_rows();
                break;

            case 'audit':
                if (!$this->can_view_audit_log()) {
                    return $this->error_response('You do not have permission to export audit logs.', 403);
                }
                $rows = $this->get_export_audit_rows();
                break;

            default:
                return $this->error_response('Unsupported export type.', 400);
        }

        $response = new WP_REST_Response($this->build_csv_string($rows), 200);
        $response->header('Content-Type', 'text/csv; charset=' . get_option('blog_charset', 'UTF-8'));
        $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        Myavana_Admin_Portal_Audit_Log::log(
            'export_csv',
            ['type' => $type, 'rows' => max(0, count($rows) - 1)],
            'export',
            0,
            'success'
        );

        return $response;
    }

    public function register_launch_link()
    {
        add_submenu_page(
            'myavana-intelligence-dashboard',
            'Admin Portal',
            'Admin Portal',
            Myavana_Admin_Portal_Permissions::ACCESS_CAP,
            'myavana-admin-portal-launch',
            [$this, 'render_launch_redirect']
        );
    }

    public function render_launch_redirect()
    {
        wp_safe_redirect(self::get_portal_url());
        exit;
    }

    public static function get_portal_url()
    {
        $page = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        return $page ? get_permalink($page) : home_url('/' . self::PAGE_SLUG . '/');
    }

    public static function is_portal_request_static()
    {
        global $post;

        if (is_a($post, 'WP_Post') && !empty($post->post_content) && has_shortcode($post->post_content, 'myavana_admin_portal')) {
            return true;
        }

        $path = strtolower(trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));
        return strpos($path, self::PAGE_SLUG) === 0;
    }

    public static function ensure_portal_page()
    {
        $existing = get_page_by_path(self::PAGE_SLUG, OBJECT, 'page');
        if ($existing) {
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post([
            'post_title' => self::PAGE_TITLE,
            'post_name' => self::PAGE_SLUG,
            'post_content' => '[myavana_admin_portal]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id() ?: 1,
        ]);

        if (!is_wp_error($page_id)) {
            flush_rewrite_rules(false);
            return (int) $page_id;
        }

        return 0;
    }

    private static function ensure_feature_flag_defaults()
    {
        $defaults = [
            'myavana_admin_portal_enabled' => '1',
            'myavana_site_intelligence_enabled' => '1',
            'myavana_ai_intelligence_enabled' => '1',
        ];

        foreach ($defaults as $option_key => $value) {
            if (get_option($option_key, null) === null) {
                add_option($option_key, $value, '', false);
            }
        }
    }

    private function is_portal_request()
    {
        return self::is_portal_request_static();
    }

    private function sanitize_date($date, $fallback)
    {
        $date = is_string($date) ? $date : '';
        $timestamp = strtotime($date);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : $fallback;
    }

    private function count_collection_items($value)
    {
        if (empty($value)) {
            return 0;
        }

        if (is_array($value)) {
            return count($value);
        }

        return 1;
    }

    private function build_csv_string(array $rows)
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(static function ($value) {
                if (is_array($value) || is_object($value)) {
                    return wp_json_encode($value);
                }

                return (string) $value;
            }, (array) $row));
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    private function get_export_users_rows()
    {
        $rows = [[
            'ID',
            'Display Name',
            'Email',
            'Roles',
            'Registered',
            'Onboarding Status',
            'Goals',
            'Routines',
        ]];

        $query = new WP_User_Query([
            'number' => 5000,
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);

        foreach ((array) $query->get_results() as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $summary = $this->format_user_summary($user);
            $rows[] = [
                $summary['id'],
                $summary['display_name'],
                $summary['user_email'],
                implode(', ', (array) $summary['roles']),
                $summary['registered'],
                $summary['onboarding_status'],
                $summary['goal_count'],
                $summary['routine_count'],
            ];
        }

        return $rows;
    }

    private function get_export_reports_rows()
    {
        global $wpdb;

        $rows = [[
            'ID',
            'Content Type',
            'Content ID',
            'Reason',
            'Status',
            'Created At',
        ]];

        $reports_table = $wpdb->prefix . 'myavana_ci_content_reports';
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $reports_table));
        if (!$exists) {
            return $rows;
        }

        $reports = $wpdb->get_results(
            "SELECT id, content_type, content_id, reason, status, created_at
             FROM {$reports_table}
             ORDER BY created_at DESC, id DESC
             LIMIT 2000",
            ARRAY_A
        );

        foreach ((array) $reports as $report) {
            $rows[] = [
                (int) ($report['id'] ?? 0),
                $report['content_type'] ?? '',
                (int) ($report['content_id'] ?? 0),
                $report['reason'] ?? '',
                $report['status'] ?? '',
                $report['created_at'] ?? '',
            ];
        }

        return $rows;
    }

    private function get_export_audit_rows()
    {
        $rows = [[
            'ID',
            'Actor User ID',
            'Actor Name',
            'Actor Email',
            'Action',
            'Target Type',
            'Target ID',
            'Status',
            'IP Address',
            'Created At',
            'Context',
        ]];

        $audit = Myavana_Admin_Portal_Audit_Log::get_recent_logs(1, 500);
        foreach ((array) ($audit['items'] ?? []) as $item) {
            $rows[] = [
                (int) ($item['id'] ?? 0),
                (int) ($item['actor_user_id'] ?? 0),
                $item['actor_name'] ?? '',
                $item['actor_email'] ?? '',
                $item['action_key'] ?? '',
                $item['target_type'] ?? '',
                (int) ($item['target_id'] ?? 0),
                $item['status'] ?? '',
                $item['ip_address'] ?? '',
                $item['created_at'] ?? '',
                wp_json_encode($item['context'] ?? []),
            ];
        }

        return $rows;
    }

    private function format_user_summary(WP_User $user)
    {
        return [
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'roles' => array_values((array) $user->roles),
            'registered' => $user->user_registered,
            'onboarding_status' => get_user_meta($user->ID, 'myavana_onboarding_status', true) ?: 'pending',
            'goal_count' => $this->count_collection_items(get_user_meta($user->ID, 'myavana_hair_goals_structured', true)),
            'routine_count' => $this->count_collection_items(get_user_meta($user->ID, 'myavana_current_routine', true)),
        ];
    }

    private function get_profile_row($user_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'myavana_profiles';
        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return [];
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT hair_type, location, hair_journey_stage
             FROM {$table}
             WHERE user_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $user_id
        ), ARRAY_A);

        return is_array($row) ? $row : [];
    }

    private function get_user_last_activity($user_id)
    {
        global $wpdb;

        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        $events_table = $wpdb->prefix . 'myavana_site_events';

        $analytics_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $analytics_table));
        $events_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table));

        $visited_at = '';
        $event_at = '';

        if ($analytics_exists) {
            $visited_at = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(visited_at) FROM {$analytics_table} WHERE user_id = %d",
                $user_id
            ));
        }

        if ($events_exists) {
            $event_at = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(created_at) FROM {$events_table} WHERE user_id = %d",
                $user_id
            ));
        }

        $timestamps = array_filter([$visited_at, $event_at]);
        rsort($timestamps);

        return !empty($timestamps) ? $timestamps[0] : '';
    }

    private function get_reward_field_labels()
    {
        return [
            'daily_checkin_base' => 'Daily check-in base',
            'daily_checkin_streak_bonus_7' => '7-day streak bonus',
            'daily_checkin_streak_bonus_30' => '30-day streak bonus',
            'daily_checkin_streak_bonus_10x' => '10-day milestone bonus',
            'entry_created' => 'Entry created',
            'entry_photo_bonus' => 'Photo bonus',
            'entry_video_bonus' => 'Video bonus',
            'ai_entry_bonus' => 'AI entry bonus',
            'ai_analysis_saved' => 'AI analysis saved',
            'goal_created' => 'Goal created',
            'goal_milestone' => 'Goal milestone',
            'goal_completed' => 'Goal completed',
            'routine_created' => 'Routine created',
            'routine_completed' => 'Routine completed',
            'onboarding_completed' => 'Onboarding completed',
        ];
    }

    private function get_challenge_metric_options()
    {
        return [
            'entries_week' => 'Entries this week',
            'routines_week' => 'Routines completed this week',
            'ai_week' => 'AI analyses this week',
            'community_posts_week' => 'Community posts this week',
            'community_posts_month' => 'Community posts this month',
            'video_entries_month' => 'Video entries this month',
            'current_streak' => 'Current streak',
            'goals_completed_total' => 'Goals completed total',
            'total_entries' => 'Total entries',
            'total_ai_analyses' => 'Total AI analyses',
            'checkins_week' => 'Check-ins this week',
        ];
    }

    private function table_exists($table_name)
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
    }

    private function build_password_reset_email($user, $reset_url, $site_name)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Reset Your Password - <?php echo esc_html($site_name); ?></title>
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
                    <p style="margin: 0; font-size: 18px;">Password Reset Request</p>
                </div>
                <div class="content">
                    <p>Hi <strong><?php echo esc_html($user->display_name ?: $user->user_login); ?></strong>,</p>
                    <p>Someone requested a password reset for your MYAVANA account. If this was you, click the button below to reset your password:</p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($reset_url); ?>" class="button">Reset My Password</a>
                    </p>
                    <p><strong>Security Notes:</strong></p>
                    <ul>
                        <li>This link expires in 24 hours</li>
                        <li>If you didn't request this, ignore this email</li>
                        <li>Your password remains secure until you use this link</li>
                    </ul>
                </div>
                <div class="footer">
                    <p>This email was sent from MYAVANA Hair Journey</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function mask_secret($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return [
                'has_value' => false,
                'masked' => '',
            ];
        }

        $length = strlen($value);
        $suffix = $length > 4 ? substr($value, -4) : $value;

        return [
            'has_value' => true,
            'masked' => str_repeat('•', max(4, $length - strlen($suffix))) . $suffix,
        ];
    }

    public function can_view_analytics(?WP_REST_Request $request = null)
    {
        return Myavana_Admin_Portal_Permissions::current_user_can_view_analytics();
    }

    public function can_manage_users(?WP_REST_Request $request = null)
    {
        return Myavana_Admin_Portal_Permissions::current_user_can_manage_users();
    }

    public function can_manage_settings(?WP_REST_Request $request = null)
    {
        return Myavana_Admin_Portal_Permissions::current_user_can_manage_settings();
    }

    public function can_view_audit_log(?WP_REST_Request $request = null)
    {
        return Myavana_Admin_Portal_Permissions::current_user_can_view_audit_log();
    }

    public function can_manage_support(?WP_REST_Request $request = null)
    {
        return Myavana_Admin_Portal_Permissions::current_user_can_manage_support();
    }

    private function success_response($data = [], $message = '')
    {
        return rest_ensure_response([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    private function error_response($message, $status = 400)
    {
        return new WP_REST_Response([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
