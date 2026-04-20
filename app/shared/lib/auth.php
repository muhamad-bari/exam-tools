<?php

$authLocalConfigPath = APP_ROOT . '/shared/config/auth.local.php';
if (is_file($authLocalConfigPath)) {
    require_once $authLocalConfigPath;
}

if (!defined('AUTH_CONFIG_USERNAME')) {
    $authConfigUsername = getenv('EXAM_TOOLS_AUTH_USERNAME');
    define('AUTH_CONFIG_USERNAME', is_string($authConfigUsername) ? trim($authConfigUsername) : '');
}

if (!defined('AUTH_CONFIG_PASSWORD_HASH')) {
    $authConfigPasswordHash = getenv('EXAM_TOOLS_AUTH_PASSWORD_HASH');
    define('AUTH_CONFIG_PASSWORD_HASH', is_string($authConfigPasswordHash) ? trim($authConfigPasswordHash) : '');
}

if (!defined('AUTH_SESSION_KEY')) {
    define('AUTH_SESSION_KEY', 'exam_tools_authenticated_user');
}

if (!defined('AUTH_FLASH_KEY')) {
    define('AUTH_FLASH_KEY', 'exam_tools_auth_flash');
}

if (!function_exists('auth_is_authenticated')) {
    function auth_is_authenticated(): bool
    {
        return isset($_SESSION[AUTH_SESSION_KEY]['username'])
            && is_string($_SESSION[AUTH_SESSION_KEY]['username'])
            && $_SESSION[AUTH_SESSION_KEY]['username'] !== '';
    }
}

if (!function_exists('auth_get_authenticated_username')) {
    function auth_get_authenticated_username(): ?string
    {
        if (!auth_is_authenticated()) {
            return null;
        }

        return $_SESSION[AUTH_SESSION_KEY]['username'];
    }
}

if (!function_exists('auth_attempt_login')) {
    function auth_attempt_login(string $username, string $password): bool
    {
        if (AUTH_CONFIG_USERNAME === '' || AUTH_CONFIG_PASSWORD_HASH === '') {
            return false;
        }

        $normalizedUsername = trim($username);

        if ($normalizedUsername === '' || $password === '') {
            return false;
        }

        if (!hash_equals(AUTH_CONFIG_USERNAME, $normalizedUsername)) {
            return false;
        }

        if (!password_verify($password, AUTH_CONFIG_PASSWORD_HASH)) {
            return false;
        }

        auth_login_user(AUTH_CONFIG_USERNAME);

        return true;
    }
}

if (!function_exists('auth_login_user')) {
    function auth_login_user(string $username): void
    {
        session_regenerate_id(true);

        $_SESSION[AUTH_SESSION_KEY] = [
            'username' => $username,
            'logged_in_at' => time(),
        ];
    }
}

if (!function_exists('auth_logout_user')) {
    function auth_logout_user(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}

if (!function_exists('auth_set_flash_message')) {
    function auth_set_flash_message(string $key, string $message): void
    {
        if (!isset($_SESSION[AUTH_FLASH_KEY]) || !is_array($_SESSION[AUTH_FLASH_KEY])) {
            $_SESSION[AUTH_FLASH_KEY] = [];
        }

        $_SESSION[AUTH_FLASH_KEY][$key] = $message;
    }
}

if (!function_exists('auth_pull_flash_message')) {
    function auth_pull_flash_message(string $key): ?string
    {
        if (!isset($_SESSION[AUTH_FLASH_KEY][$key]) || !is_string($_SESSION[AUTH_FLASH_KEY][$key])) {
            return null;
        }

        $message = $_SESSION[AUTH_FLASH_KEY][$key];
        unset($_SESSION[AUTH_FLASH_KEY][$key]);

        if ($_SESSION[AUTH_FLASH_KEY] === []) {
            unset($_SESSION[AUTH_FLASH_KEY]);
        }

        return $message;
    }
}
