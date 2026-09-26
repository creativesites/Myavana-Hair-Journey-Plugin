<?php
if (!defined('ABSPATH')) {
    exit;
}

class Myavana_Admin_Portal_Audit_Log
{
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'myavana_admin_portal_audit_version';
    const TABLE_SUFFIX = 'myavana_admin_audit_log';

    public static function boot()
    {
        add_action('init', [__CLASS__, 'maybe_upgrade'], 6);
        add_action('myavana_admin_daily_maintenance', [__CLASS__, 'prune_old_logs']);
    }

    public static function activate()
    {
        self::create_table();
        update_option(self::VERSION_OPTION, self::VERSION, false);

        if (!wp_next_scheduled('myavana_admin_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'myavana_admin_daily_maintenance');
        }
    }

    public static function maybe_upgrade()
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }

        self::create_table();
        update_option(self::VERSION_OPTION, self::VERSION, false);
    }

    public static function create_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_SUFFIX;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            action_key VARCHAR(191) NOT NULL,
            target_type VARCHAR(100) NOT NULL DEFAULT '',
            target_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'success',
            context_json LONGTEXT NULL,
            ip_address VARCHAR(100) NOT NULL DEFAULT '',
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY actor_user_id (actor_user_id),
            KEY action_key (action_key),
            KEY target_lookup (target_type, target_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log($action_key, $context = [], $target_type = '', $target_id = 0, $status = 'success')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_SUFFIX;

        $wpdb->insert(
            $table_name,
            [
                'actor_user_id' => get_current_user_id(),
                'action_key' => sanitize_key($action_key),
                'target_type' => sanitize_key($target_type),
                'target_id' => absint($target_id),
                'status' => sanitize_key($status ?: 'success'),
                'context_json' => !empty($context) ? wp_json_encode($context) : null,
                'ip_address' => sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')),
                'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public static function get_recent_logs($page = 1, $per_page = 20)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_SUFFIX;
        $users_table = $wpdb->users;
        $page = max(1, absint($page));
        $per_page = max(1, min(100, absint($per_page)));
        $offset = ($page - 1) * $per_page;

        $exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if (!$exists) {
            return [
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => 0,
                    'total_pages' => 0,
                ],
            ];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, u.display_name, u.user_email
                 FROM {$table_name} l
                 LEFT JOIN {$users_table} u ON u.ID = l.actor_user_id
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $items = [];
        foreach ((array) $rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'actor_user_id' => (int) $row['actor_user_id'],
                'actor_name' => $row['display_name'] ?: 'System',
                'actor_email' => $row['user_email'] ?: '',
                'action_key' => $row['action_key'],
                'target_type' => $row['target_type'],
                'target_id' => (int) $row['target_id'],
                'status' => $row['status'],
                'context' => self::decode_context($row['context_json']),
                'ip_address' => $row['ip_address'],
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ];
    }

    private static function decode_context($json)
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function prune_old_logs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_SUFFIX;
        
        if ((bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name))) {
            $wpdb->query(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
        }
    }
}

Myavana_Admin_Portal_Audit_Log::boot();
