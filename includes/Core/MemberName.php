<?php
/**
 * How the app addresses a member in her own views: her first name when she
 * gave one, else her display name, unless that is only her login handle
 * (WordPress defaults display_name to user_login, which reads like a
 * username rather than a person).
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class MemberName {
    /**
     * @param \WP_User|int $user
     * @param bool $allowLogin Fall back to the login handle when nothing better exists.
     */
    public static function forUser($user, bool $allowLogin = true): string {
        $user = $user instanceof \WP_User ? $user : get_userdata((int) $user);
        if (!$user || !$user->exists()) {
            return '';
        }
        $first = trim((string) get_user_meta($user->ID, 'first_name', true));
        if ($first !== '') {
            return $first;
        }
        $display = trim((string) $user->display_name);
        if ($display !== '' && strcasecmp($display, $user->user_login) !== 0) {
            return $display;
        }
        return $allowLogin ? (string) $user->user_login : '';
    }

    /** A soft coral monogram, used when her photo can't load. */
    public static function initialAvatarUri(string $name): string {
        $letter = strtoupper(mb_substr(trim($name) ?: 'M', 0, 1));
        $letter = htmlspecialchars($letter, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" fill="#fce5d7"/>'
            . '<text x="60" y="60" dy=".35em" text-anchor="middle" font-family="Archivo, Helvetica, Arial, sans-serif" font-size="52" font-weight="700" fill="#9b5a49">'
            . $letter . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** onerror attribute that swaps a broken avatar for her monogram. */
    public static function avatarFallbackAttr(string $name): string {
        return ' onerror="this.onerror=null;this.src=\'' . esc_attr(self::initialAvatarUri($name)) . '\'"';
    }

    /**
     * The name she chose to show (Edit profile writes display_name), falling
     * back to her first name when display_name is only the login handle.
     *
     * @param \WP_User|int $user
     */
    public static function displayFor($user): string {
        $user = $user instanceof \WP_User ? $user : get_userdata((int) $user);
        if (!$user || !$user->exists()) {
            return '';
        }
        $display = trim((string) $user->display_name);
        if ($display !== '' && strcasecmp($display, $user->user_login) !== 0) {
            return $display;
        }
        return self::forUser($user);
    }
}
