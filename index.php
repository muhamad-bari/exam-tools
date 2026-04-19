<?php
require_once __DIR__ . '/app/bootstrap.php';

$webRoutes = [
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

$api = $_GET['api'] ?? '';
if ($api !== '') {
    if (!isset($apiRoutes[$api])) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'API route not found']);
        exit;
    }

    require $apiRoutes[$api];
    exit;
}

$route = $_GET['route'] ?? 'qr';
if (!isset($webRoutes[$route])) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

require $webRoutes[$route];
