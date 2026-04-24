<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request();
ini_set('memory_limit', '512M');
set_time_limit(300);

$currentDateLabel = date('d F Y');
$scheduleDefaults = [
    'defaultHeaderLine1' => 'AKADEMI KEBIDANAN WIJAYA HUSADA',
    'defaultSubTitle' => 'JADWAL UJIAN TENGAH SEMESTER (UTS) SEMESTER GENAP T.A 2024 / 2025',
    'defaultSignerName' => 'Elpinaria Girsang, S.ST., M.K.M.',
    'defaultSignerInstitution' => 'Akademi Kebidanan Wijaya Husada',
    'defaultSignerTitle' => 'Direktur',
    'defaultSignerDate' => $currentDateLabel,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator Jadwal - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="assets/css/components/schedule.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php require_once PROJECT_ROOT . '/app/shared/layout/navbar.php'; ?>
    <div id="toast-container"></div>

    <div class="split-container">
        <?php require __DIR__ . '/partials/schedule_form.php'; ?>
        <?php require __DIR__ . '/partials/schedule_preview.php'; ?>
    </div>

    <script>
        window.schedulePageConfig = <?= json_encode($scheduleDefaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="assets/js/shared/utils.js"></script>
    <script src="assets/js/schedule/core.js"></script>
    <script src="assets/js/schedule/uploads.js"></script>
    <script src="assets/js/schedule/editor.js"></script>
    <script src="assets/js/schedule/sessions.js"></script>
    <script src="assets/js/schedule/page.js"></script>
</body>
</html>
