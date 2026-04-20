<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request(true);
require_once PROJECT_ROOT . '/app/shared/lib/auth.php';

function backup_send_json_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message,
    ]);
    exit;
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod !== 'GET') {
    header('Allow: GET');
    backup_send_json_error(405, 'Method not allowed');
}

$action = trim((string) ($_GET['action'] ?? ''));
if ($action !== 'download_full') {
    backup_send_json_error(400, 'Invalid action');
}

$authenticatedUsername = auth_get_authenticated_username();
if (
    !is_string($authenticatedUsername)
    || $authenticatedUsername === ''
    || AUTH_CONFIG_USERNAME === ''
    || !hash_equals(AUTH_CONFIG_USERNAME, $authenticatedUsername)
) {
    backup_send_json_error(403, 'Forbidden');
}

$databasePath = PROJECT_ROOT . '/database.sqlite';
if (!is_file($databasePath) || !is_readable($databasePath)) {
    backup_send_json_error(404, 'Database backup source not found');
}

$downloadName = 'database_backup_' . date('Ymd_His') . '.sqlite';
$fileSize = filesize($databasePath);
if ($fileSize === false) {
    backup_send_json_error(500, 'Failed to prepare database backup');
}

if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . $fileSize);
readfile($databasePath);
exit;
