<?php
/**
 * PHP 8 compatibility layer for Viscacha 0.8.2.0
 * Only provides missing functions / softens notices so the original code can run.
 * No new features are added.
 */
if (defined('VISCACHA_PHP8_COMPAT')) return;
define('VISCACHA_PHP8_COMPAT', 1);

// Soften error reporting for legacy code (notices/deprecations common in 2000s-era PHP)
if (function_exists('error_reporting')) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// each() removed in PHP 8.0
if (!function_exists('each')) {
    function each(&$array) {
        $key = key($array);
        if ($key === null) {
            return false;
        }
        $value = current($array);
        next($array);
        return array(1 => $value, 'value' => $value, 0 => $key, 'key' => $key);
    }
}

// create_function() removed in PHP 8.0 – minimal polyfill using eval (same risk profile as original)
if (!function_exists('create_function')) {
    function create_function($args, $code) {
        static $i = 0;
        $name = 'viscacha_lambda_' . (++$i) . '_' . mt_rand(1000, 9999);
        $args = str_replace(array("\r", "\n"), '', $args);
        $code = str_replace(array("\r", "\n"), '', $code);
        eval('function ' . $name . '(' . $args . ') { ' . $code . ' }');
        return $name;
    }
}

// get_magic_quotes_gpc() removed in PHP 8.0
if (!function_exists('get_magic_quotes_gpc')) {
    function get_magic_quotes_gpc() {
        return false;
    }
}
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() {
        return false;
    }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($new_setting) {
        return false;
    }
}

// mysql_* polyfill is NOT added here – Viscacha has a native mysqli driver.
// Installer / config should use dbsystem = 'mysqli'.
