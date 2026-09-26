<?php
if (!defined('ABSPATH')) {
    exit;
}

class Myavana_Admin_Portal_Permissions
{
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'myavana_admin_portal_roles_version';

    const ACCESS_CAP = 'myavana_access_admin_portal';
    const VIEW_ANALYTICS_CAP = 'myavana_view_admin_analytics';
    const MANAGE_USERS_CAP = 'myavana_manage_members';
    const MANAGE_SETTINGS_CAP = 'myavana_manage_portal_settings';
    const VIEW_AUDIT_CAP = 'myavana_view_admin_audit_log';
    const MANAGE_SUPPORT_CAP = 'myavana_manage_support_ops';

    public static function boot()
    {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 5);
    }

    public static function activate()
    {
        self::sync_roles_and_capabilities();
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    public static function maybe_upgrade()
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }

        self::sync_roles_and_capabilities();
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    public static function current_user_can_access_portal()
    {
        return current_user_can(self::ACCESS_CAP) || current_user_can('manage_options');
    }

    public static function current_user_can_manage_settings()
    {
        return current_user_can(self::MANAGE_SETTINGS_CAP) || current_user_can('manage_options');
    }

    public static function current_user_can_view_audit_log()
    {
        return current_user_can(self::VIEW_AUDIT_CAP) || current_user_can('manage_options');
    }

    public static function current_user_can_manage_users()
    {
        return current_user_can(self::MANAGE_USERS_CAP) || current_user_can('manage_options');
    }

    public static function current_user_can_view_analytics()
    {
        return current_user_can(self::VIEW_ANALYTICS_CAP) || current_user_can('manage_options');
    }

    public static function current_user_can_manage_support()
    {
        return current_user_can(self::MANAGE_SUPPORT_CAP) || current_user_can('manage_options');
    }

    public static function get_capability_matrix()
    {
        return [
            'access_portal' => self::current_user_can_access_portal(),
            'view_analytics' => self::current_user_can_view_analytics(),
            'manage_users' => self::current_user_can_manage_users(),
            'manage_settings' => self::current_user_can_manage_settings(),
            'view_audit_log' => self::current_user_can_view_audit_log(),
            'manage_support' => self::current_user_can_manage_support(),
        ];
    }

    private static function sync_roles_and_capabilities()
    {
        $role_map = [
            'myavana_super_admin' => [
                'label' => 'MYAVANA Super Admin',
                'caps' => self::all_custom_capabilities(),
            ],
            'myavana_ops_admin' => [
                'label' => 'MYAVANA Ops Admin',
                'caps' => [
                    self::ACCESS_CAP => true,
                    self::VIEW_ANALYTICS_CAP => true,
                    self::MANAGE_USERS_CAP => true,
                    self::VIEW_AUDIT_CAP => true,
                    self::MANAGE_SUPPORT_CAP => true,
                ],
            ],
            'myavana_support_admin' => [
                'label' => 'MYAVANA Support Admin',
                'caps' => [
                    self::ACCESS_CAP => true,
                    self::MANAGE_USERS_CAP => true,
                    self::MANAGE_SUPPORT_CAP => true,
                ],
            ],
            'myavana_content_admin' => [
                'label' => 'MYAVANA Content Admin',
                'caps' => [
                    self::ACCESS_CAP => true,
                    self::VIEW_ANALYTICS_CAP => true,
                ],
            ],
            'myavana_analytics_viewer' => [
                'label' => 'MYAVANA Analytics Viewer',
                'caps' => [
                    self::ACCESS_CAP => true,
                    self::VIEW_ANALYTICS_CAP => true,
                    self::VIEW_AUDIT_CAP => true,
                ],
            ],
        ];

        foreach ($role_map as $role_slug => $definition) {
            $role = get_role($role_slug);

            if (!$role) {
                $role = add_role($role_slug, $definition['label'], []);
            }

            if (!$role instanceof WP_Role) {
                continue;
            }

            foreach ($definition['caps'] as $capability => $grant) {
                if ($grant) {
                    $role->add_cap($capability);
                } else {
                    $role->remove_cap($capability);
                }
            }
        }

        $administrator = get_role('administrator');
        if ($administrator instanceof WP_Role) {
            foreach (array_keys(self::all_custom_capabilities()) as $capability) {
                $administrator->add_cap($capability);
            }
        }
    }

    private static function all_custom_capabilities()
    {
        return [
            self::ACCESS_CAP => true,
            self::VIEW_ANALYTICS_CAP => true,
            self::MANAGE_USERS_CAP => true,
            self::MANAGE_SETTINGS_CAP => true,
            self::VIEW_AUDIT_CAP => true,
            self::MANAGE_SUPPORT_CAP => true,
        ];
    }
}

Myavana_Admin_Portal_Permissions::boot();
