<?php
/**
 * Tables for the community interaction features (reactions, comment likes,
 * reports, bookmark collections, drafts) and the comments parent_id column
 * used for replies. Ported from the original Hair Journey plugin
 * (includes/myavana-ci-database.php): the handlers here query these tables,
 * but only the original plugin ever created them, so on a site without it
 * reactions, comment likes, replies, reports and collections silently did
 * nothing. Every statement is IF NOT EXISTS / column-checked, so this is a
 * no-op wherever the original plugin already ran.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

function myavana_next_ci_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Post Reactions Table
    $table_name = $wpdb->prefix . 'myavana_ci_post_reactions';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        reaction_type varchar(20) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_post_reaction (post_id, user_id),
        KEY post_id (post_id),
        KEY user_id (user_id),
        KEY reaction_type (reaction_type)
    ) $charset_collate;";
    dbDelta($sql);

    // Comment Likes Table
    $table_name = $wpdb->prefix . 'myavana_ci_comment_likes';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_comment_like (comment_id, user_id),
        KEY comment_id (comment_id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Content Reports Table
    $table_name = $wpdb->prefix . 'myavana_ci_content_reports';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        content_type varchar(20) NOT NULL,
        content_id bigint(20) NOT NULL,
        reporter_user_id bigint(20) NOT NULL,
        reason varchar(100) NOT NULL,
        details text,
        status varchar(20) DEFAULT 'pending',
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY content_type_id (content_type, content_id),
        KEY reporter_user_id (reporter_user_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql);

    // Bookmark Collections Table
    $table_name = $wpdb->prefix . 'myavana_ci_bookmark_collections';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        name varchar(100) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Collection Items Table
    $table_name = $wpdb->prefix . 'myavana_ci_collection_items';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        collection_id bigint(20) NOT NULL,
        post_id bigint(20) NOT NULL,
        added_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY collection_post (collection_id, post_id),
        KEY collection_id (collection_id),
        KEY post_id (post_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Draft Posts Table
    $table_name = $wpdb->prefix . 'myavana_ci_post_drafts';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        title varchar(200),
        content text,
        post_type varchar(50) DEFAULT 'general',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Add parent_id column to existing comments table if not exists
    $comments_table = $wpdb->prefix . 'myavana_post_comments';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $comments_table)) !== $comments_table) {
        update_option('myavana_next_ci_db_version', '1.0.0', false);
        return;
    }
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $comments_table LIKE 'parent_id'");

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $comments_table ADD COLUMN parent_id bigint(20) DEFAULT 0 AFTER post_id");
        $wpdb->query("ALTER TABLE $comments_table ADD KEY parent_id (parent_id)");
    }

    // Update plugin version
    update_option('myavana_next_ci_db_version', '1.0.0', false);
}

if (!function_exists('myavana_next_ci_check_database')) {
    function myavana_next_ci_check_database() {
        if (version_compare((string) get_option('myavana_next_ci_db_version', '0.0.0'), '1.0.0', '<')) {
            myavana_next_ci_create_tables();
        }
    }
}
myavana_next_ci_check_database();
