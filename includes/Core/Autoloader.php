<?php
/**
 * PSR-4 Compatible Autoloader for Myavana\Next
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader {
    /**
     * Namespace prefix
     *
     * @var string
     */
    private const PREFIX = 'Myavana\\Next\\';

    /**
     * Base directory for classes
     *
     * @var string
     */
    private static $baseDir = '';

    /**
     * Register autoloader
     */
    public static function register(): void {
        self::$baseDir = MYAVANA_NEXT_PATH . 'includes/';

        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Load class file
     *
     * @param string $class Fully qualified class name.
     */
    public static function autoload(string $class): void {
        if (strpos($class, self::PREFIX) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen(self::PREFIX));
        $file = self::$baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
