<?php

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

if (!function_exists('_app_ctype_normalize')) {
    function _app_ctype_normalize($value): string
    {
        if (is_int($value)) {
            if ($value >= 0 && $value <= 255) {
                return chr($value);
            }

            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}

if (!function_exists('ctype_xdigit')) {
    function ctype_xdigit($value): bool
    {
        $text = _app_ctype_normalize($value);
        return $text !== '' && preg_match('/^[0-9A-Fa-f]+$/', $text) === 1;
    }
}

if (!function_exists('ctype_digit')) {
    function ctype_digit($value): bool
    {
        $text = _app_ctype_normalize($value);
        return $text !== '' && preg_match('/^[0-9]+$/', $text) === 1;
    }
}

if (!function_exists('ctype_alpha')) {
    function ctype_alpha($value): bool
    {
        $text = _app_ctype_normalize($value);
        return $text !== '' && preg_match('/^[A-Za-z]+$/', $text) === 1;
    }
}

if (!function_exists('ctype_upper')) {
    function ctype_upper($value): bool
    {
        $text = _app_ctype_normalize($value);
        return $text !== '' && preg_match('/^[A-Z]+$/', $text) === 1;
    }
}

if (!function_exists('app_require_router_request')) {
    function app_require_router_request(bool $expectsJson = false): void
    {
        if (defined('APP_ROUTER_REQUEST') && APP_ROUTER_REQUEST === true) {
            return;
        }

        http_response_code(404);

        if ($expectsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'API route not found',
            ]);
            exit;
        }

        echo 'Page not found';
        exit;
    }
}
