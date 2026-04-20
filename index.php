<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once APP_ROOT . '/shared/lib/auth.php';

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function getSessionCookiePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $directory = str_replace('\\', '/', dirname($scriptName));

    if ($directory === '/' || $directory === '.') {
        return '/';
    }

    return rtrim($directory, '/');
}

function redirectToRoute(string $route): void
{
    header('Location: index.php?route=' . rawurlencode($route));
    exit;
}

function sendJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => getSessionCookiePath(),
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!defined('APP_ROUTER_REQUEST')) {
    define('APP_ROUTER_REQUEST', true);
}

$webRoutes = [
    'login' => APP_ROOT . '/modules/auth/web/login.php',
    'dashboard' => APP_ROOT . '/modules/dashboard/web/index.php',
    'qr' => APP_ROOT . '/modules/qr/web/index.php',
    'master-data' => APP_ROOT . '/modules/master_data/web/master_data.php',
    'schedule' => APP_ROOT . '/modules/schedule/web/jadwal.php',
    'grade-recap' => APP_ROOT . '/modules/grade_recap/web/rekap_nilai.php',
    'follow-up' => APP_ROOT . '/modules/follow_up/web/index.php',
];

$apiRoutes = [
    'download' => APP_ROOT . '/modules/qr/api/download.php',
    'generate_pdf' => APP_ROOT . '/modules/schedule/api/generate_pdf_api.php',
    'generate_jadwal' => APP_ROOT . '/modules/schedule/api/api_generate_jadwal.php',
    'follow_up' => APP_ROOT . '/modules/follow_up/api/api_follow_up.php',
    'master_data' => APP_ROOT . '/modules/master_data/api/api_master_data.php',
    'sessions' => APP_ROOT . '/modules/sessions/api/api_sessions.php',
    'rekap_nilai' => APP_ROOT . '/modules/grade_recap/api/api_rekap_nilai.php',
    'diagnostics' => APP_ROOT . '/modules/diagnostics/web/test_api.php',
    'legacy_cetak' => APP_ROOT . '/modules/qr/legacy/cetak.php',
    'legacy_dompdf' => APP_ROOT . '/modules/qr/legacy/dompdf.php',
];

$publicWebRoutes = ['login'];

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

$api = $_GET['api'] ?? '';
if ($api !== '') {
    if (!isset($apiRoutes[$api])) {
        sendJsonResponse(404, ['success' => false, 'message' => 'API route not found']);
    }

    if (!auth_is_authenticated()) {
        sendJsonResponse(401, ['success' => false, 'message' => 'Unauthorized']);
    }

    require $apiRoutes[$api];
    exit;
}

$route = isset($_GET['route']) ? trim((string) $_GET['route']) : '';
if ($route === '') {
    $route = auth_is_authenticated() ? 'dashboard' : 'login';
}

if ($route === 'logout') {
    if ($requestMethod !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method Not Allowed';
        exit;
    }

    auth_logout_user();
    redirectToRoute('login');
}

if ($route === 'login') {
    if (auth_is_authenticated()) {
        redirectToRoute('dashboard');
    }

    if ($requestMethod === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (auth_attempt_login($username, $password)) {
            redirectToRoute('dashboard');
        }

        auth_set_flash_message('login_error', 'Invalid username or password.');
        redirectToRoute('login');
    }
}

if (!isset($webRoutes[$route])) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

if (!in_array($route, $publicWebRoutes, true) && !auth_is_authenticated()) {
    redirectToRoute('login');
}

require $webRoutes[$route];
