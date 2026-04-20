<?php
require_once __DIR__ . '/../../../bootstrap.php';
app_require_router_request();
require_once PROJECT_ROOT . '/app/shared/lib/auth.php';
require_once PROJECT_ROOT . '/app/shared/lib/database.php';

function dashboard_scalar(PDO $db, string $sql): int
{
    return (int) $db->query($sql)->fetchColumn();
}

function dashboard_fetch_all(PDO $db, string $sql): array
{
    $statement = $db->query($sql);
    return $statement ? $statement->fetchAll() : [];
}

function dashboard_greeting(): string
{
    $hour = (int) date('G');

    if ($hour < 11) {
        return 'Selamat pagi';
    }

    if ($hour < 15) {
        return 'Selamat siang';
    }

    if ($hour < 18) {
        return 'Selamat sore';
    }

    return 'Selamat malam';
}

function dashboard_format_timestamp(?string $timestamp): string
{
    if ($timestamp === null || trim($timestamp) === '') {
        return 'Belum ada aktivitas tersimpan';
    }

    return date('d M Y H:i', strtotime($timestamp));
}

function dashboard_status_label(string $status): string
{
    $normalizedStatus = trim($status);
    if ($normalizedStatus === '') {
        return 'Pending';
    }

    return ucwords(str_replace('_', ' ', $normalizedStatus));
}

$authenticatedUsername = auth_get_authenticated_username() ?? 'Authenticated User';
$overviewStats = [
    'classes' => 0,
    'active_students' => 0,
    'subjects' => 0,
    'academic_periods' => 0,
    'imports' => 0,
    'saved_sessions' => 0,
    'pending_follow_ups' => 0,
    'resolved_follow_ups' => 0,
    'total_follow_ups' => 0,
];
$latestPeriodLabel = 'Belum ada periode akademik aktif';
$latestImport = null;
$topClasses = [];
$recentImports = [];
$followUpBreakdown = [];
$dashboardAlert = null;

try {
    $db = getDatabaseConnection();

    $overviewStats['classes'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM master_classes');
    $overviewStats['active_students'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM master_students WHERE is_active = 1');
    $overviewStats['subjects'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM master_subjects');
    $overviewStats['academic_periods'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM master_academic_periods');
    $overviewStats['imports'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM grade_recap_imports');
    $overviewStats['saved_sessions'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM sessions');
    $overviewStats['pending_follow_ups'] = dashboard_scalar($db, "SELECT COUNT(*) FROM follow_up_statuses WHERE LOWER(TRIM(status)) IN ('pending', 'belum mengikuti', 'dijadwalkan')");
    $overviewStats['resolved_follow_ups'] = dashboard_scalar($db, "SELECT COUNT(*) FROM follow_up_statuses WHERE LOWER(TRIM(status)) = 'sudah mengikuti'");
    $overviewStats['total_follow_ups'] = dashboard_scalar($db, 'SELECT COUNT(*) FROM follow_up_statuses');

    $latestPeriod = $db->query('SELECT academic_year, term FROM master_academic_periods ORDER BY created_at DESC, id DESC LIMIT 1')->fetch();
    if ($latestPeriod) {
        $latestPeriodLabel = trim(($latestPeriod['academic_year'] ?? '') . ' • ' . ($latestPeriod['term'] ?? ''));
    }

    $latestImport = $db->query("SELECT gri.file_name, COALESCE(ms.name, 'Mata kuliah belum dipilih') AS subject_name, COALESCE(NULLIF(TRIM(gri.exam_type), ''), 'UAS') AS exam_type, COALESCE(NULLIF(TRIM(gri.academic_year), ''), 'Tahun ajaran belum diatur') AS academic_year, COALESCE(NULLIF(TRIM(gri.term), ''), 'Periode belum diatur') AS term, gri.total_rows, gri.matched_students, gri.unmatched_students, gri.created_at FROM grade_recap_imports gri LEFT JOIN master_subjects ms ON ms.id = gri.subject_id ORDER BY gri.created_at DESC, gri.id DESC LIMIT 1")->fetch();

    $topClasses = dashboard_fetch_all($db, "SELECT mc.name, COUNT(ms.id) AS total_students, SUM(CASE WHEN ms.is_active = 1 THEN 1 ELSE 0 END) AS active_students, SUM(CASE WHEN ms.student_status = 'tidak_naik' THEN 1 ELSE 0 END) AS hold_students FROM master_classes mc LEFT JOIN master_students ms ON ms.class_id = mc.id GROUP BY mc.id, mc.name ORDER BY active_students DESC, total_students DESC, mc.name ASC LIMIT 6");

    $recentImports = dashboard_fetch_all($db, "SELECT gri.file_name, COALESCE(ms.name, 'Mata kuliah belum dipilih') AS subject_name, COALESCE(NULLIF(TRIM(gri.exam_type), ''), 'UAS') AS exam_type, COALESCE(NULLIF(TRIM(gri.academic_year), ''), 'Tahun ajaran belum diatur') AS academic_year, COALESCE(NULLIF(TRIM(gri.term), ''), 'Periode belum diatur') AS term, gri.total_rows, gri.matched_students, gri.unmatched_students, gri.created_at FROM grade_recap_imports gri LEFT JOIN master_subjects ms ON ms.id = gri.subject_id ORDER BY gri.created_at DESC, gri.id DESC LIMIT 4");

    $followUpBreakdown = dashboard_fetch_all($db, "SELECT COALESCE(NULLIF(TRIM(status), ''), 'pending') AS status_label, COUNT(*) AS total FROM follow_up_statuses GROUP BY COALESCE(NULLIF(TRIM(status), ''), 'pending') ORDER BY total DESC, status_label ASC LIMIT 5");
} catch (Throwable $throwable) {
    $dashboardAlert = 'Ringkasan database belum tersedia. Dashboard tetap tampil dan akan terisi otomatis setelah data siap.';
}

$completionRate = $overviewStats['total_follow_ups'] > 0
    ? (int) round(($overviewStats['resolved_follow_ups'] / $overviewStats['total_follow_ups']) * 100)
    : null;
$latestImportMatchRate = ($latestImport && (int) ($latestImport['total_rows'] ?? 0) > 0)
    ? (int) round((((int) ($latestImport['matched_students'] ?? 0)) / (int) $latestImport['total_rows']) * 100)
    : null;

$readinessChecks = [
    [
        'label' => 'Master data inti',
        'value' => number_format($overviewStats['classes']) . ' kelas • ' . number_format($overviewStats['active_students']) . ' mahasiswa aktif',
        'detail' => $overviewStats['classes'] > 0 && $overviewStats['active_students'] > 0 ? 'Siap dipakai untuk penjadwalan dan QR.' : 'Tambahkan kelas dan mahasiswa agar workflow berikutnya siap.',
        'tone' => $overviewStats['classes'] > 0 && $overviewStats['active_students'] > 0 ? 'ready' : 'attention',
    ],
    [
        'label' => 'Mata kuliah & periode',
        'value' => number_format($overviewStats['subjects']) . ' mata kuliah • ' . number_format($overviewStats['academic_periods']) . ' periode',
        'detail' => $overviewStats['subjects'] > 0 && $overviewStats['academic_periods'] > 0 ? 'Scope akademik sudah tersusun untuk pelaporan.' : 'Lengkapi mata kuliah dan periode akademik untuk menjaga konteks nilai.',
        'tone' => $overviewStats['subjects'] > 0 && $overviewStats['academic_periods'] > 0 ? 'ready' : 'attention',
    ],
    [
        'label' => 'Rekap nilai terbaru',
        'value' => $latestImport ? (($latestImportMatchRate ?? 0) . '% mahasiswa cocok') : 'Belum ada impor nilai',
        'detail' => $latestImport ? 'Impor terakhir: ' . $latestImport['subject_name'] . ' pada ' . dashboard_format_timestamp($latestImport['created_at'] ?? null) : 'Upload rekap nilai untuk mulai membangun insight akademik.',
        'tone' => $latestImport ? 'ready' : 'neutral',
    ],
    [
        'label' => 'Tindak lanjut remedial',
        'value' => $completionRate !== null ? $completionRate . '% terselesaikan' : 'Belum ada data tindak lanjut',
        'detail' => $overviewStats['pending_follow_ups'] > 0 ? number_format($overviewStats['pending_follow_ups']) . ' item masih perlu dipantau.' : 'Tidak ada antrean tindak lanjut yang terbuka.',
        'tone' => $overviewStats['pending_follow_ups'] > 0 ? 'attention' : 'ready',
    ],
];

$readinessScore = (int) round(
    (count(array_filter($readinessChecks, static fn(array $check): bool => $check['tone'] === 'ready')) / max(count($readinessChecks), 1)) * 100
);

$quickActions = [
    [
        'href' => 'index.php?route=master-data',
        'icon' => 'fa-solid fa-database',
        'label' => 'Perbarui master data',
        'description' => 'Tambah kelas, mahasiswa, mata kuliah, dan periode akademik.',
    ],
    [
        'href' => 'index.php?route=schedule',
        'icon' => 'fa-solid fa-calendar-days',
        'label' => 'Susun jadwal ujian',
        'description' => 'Bangun jadwal berbasis data master dan sesi terbaru.',
    ],
    [
        'href' => 'index.php?route=grade-recap',
        'icon' => 'fa-solid fa-chart-column',
        'label' => 'Tinjau rekap nilai',
        'description' => 'Lihat impor terkini dan kecocokan mahasiswa.',
    ],
    [
        'href' => 'index.php?route=follow-up',
        'icon' => 'fa-solid fa-clipboard-check',
        'label' => 'Kelola remedial & susulan',
        'description' => 'Prioritaskan item pending dan jadwal tindak lanjut.',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="page-dashboard">
    <?php require_once PROJECT_ROOT . '/app/shared/layout/navbar.php'; ?>

    <main class="app-screen dashboard-screen">
        <section class="dashboard-hero">
            <div class="dashboard-hero-copy">
                <span class="section-eyebrow section-eyebrow-dark">
                    <i class="fa-solid fa-wave-square" aria-hidden="true"></i>
                    Practical analytics dashboard
                </span>
                <h1 class="dashboard-title"><?= htmlspecialchars(dashboard_greeting() . ', ' . $authenticatedUsername, ENT_QUOTES, 'UTF-8') ?>.</h1>
                <p class="dashboard-hero-text">Mulai dari ringkasan yang langsung bisa ditindaklanjuti: kesiapan master data, hasil impor nilai terbaru, dan antrean remedial yang masih perlu perhatian.</p>

                <div class="dashboard-pill-list">
                    <span class="dashboard-pill"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= htmlspecialchars($latestPeriodLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="dashboard-pill"><i class="fa-solid fa-file-import" aria-hidden="true"></i><?= number_format($overviewStats['imports']) ?> impor tersimpan</span>
                    <span class="dashboard-pill"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><?= number_format($overviewStats['pending_follow_ups']) ?> prioritas terbuka</span>
                </div>
            </div>

            <div class="dashboard-hero-panel">
                <div class="hero-panel-top">
                    <span class="hero-panel-label">Skor kesiapan</span>
                    <strong><?= $readinessScore ?>%</strong>
                </div>
                <div class="hero-panel-metrics">
                    <div>
                        <span class="hero-panel-label">Mata kuliah</span>
                        <strong><?= number_format($overviewStats['subjects']) ?></strong>
                    </div>
                    <div>
                        <span class="hero-panel-label">Sesi tersimpan</span>
                        <strong><?= number_format($overviewStats['saved_sessions']) ?></strong>
                    </div>
                </div>
                <p class="hero-panel-note">Gunakan skor ini sebagai tanda cepat untuk melihat seberapa siap data Anda dipakai lintas modul hari ini.</p>
            </div>
        </section>

        <?php if ($dashboardAlert !== null): ?>
            <div class="notice-banner notice-banner-info dashboard-alert">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span><?= htmlspecialchars($dashboardAlert, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <section class="metric-grid dashboard-metric-grid">
            <article class="metric-card">
                <span class="metric-label">Kelas aktif</span>
                <strong class="metric-value"><?= number_format($overviewStats['classes']) ?></strong>
                <span class="metric-note">Siap dipakai untuk jadwal</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Mahasiswa aktif</span>
                <strong class="metric-value"><?= number_format($overviewStats['active_students']) ?></strong>
                <span class="metric-note">Terhubung ke master data</span>
            </article>
            <article class="metric-card">
                <span class="metric-label">Rekap nilai</span>
                <strong class="metric-value"><?= number_format($overviewStats['imports']) ?></strong>
                <span class="metric-note">Riwayat impor tersimpan</span>
            </article>
            <article class="metric-card metric-card-attention">
                <span class="metric-label">Butuh tindak lanjut</span>
                <strong class="metric-value"><?= number_format($overviewStats['pending_follow_ups']) ?></strong>
                <span class="metric-note">Remedial &amp; susulan terbuka</span>
            </article>
        </section>

        <section class="dashboard-grid">
            <div class="dashboard-main-column">
                <article class="dashboard-card-shell">
                    <div class="dashboard-card-head">
                        <div>
                            <h2 class="panel-title">Kesiapan operasional</h2>
                            <p class="panel-copy">Empat indikator utama ini membantu menentukan modul mana yang perlu Anda sentuh lebih dulu.</p>
                        </div>
                    </div>

                    <div class="readiness-list">
                        <?php foreach ($readinessChecks as $check): ?>
                            <article class="readiness-item readiness-item-<?= htmlspecialchars($check['tone'], ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <span class="readiness-label"><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= htmlspecialchars($check['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <p><?= htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dashboard-card-shell">
                    <div class="dashboard-card-head">
                        <div>
                            <h2 class="panel-title">Kelas dengan populasi terbesar</h2>
                            <p class="panel-copy">Prioritaskan kelas terbesar untuk review data, jadwal, dan tindak lanjut.</p>
                        </div>
                    </div>

                    <?php if (!empty($topClasses)): ?>
                        <div class="table-shell">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Mahasiswa aktif</th>
                                        <th>Total</th>
                                        <th>Tidak naik</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topClasses as $classRow): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($classRow['name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= number_format((int) ($classRow['active_students'] ?? 0)) ?></td>
                                            <td><?= number_format((int) ($classRow['total_students'] ?? 0)) ?></td>
                                            <td><?= number_format((int) ($classRow['hold_students'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-analytics">
                            <i class="fa-solid fa-users-viewfinder" aria-hidden="true"></i>
                            <div>Belum ada data kelas yang bisa dianalisis. Tambahkan master data untuk menampilkan distribusi mahasiswa.</div>
                        </div>
                    <?php endif; ?>
                </article>
            </div>

            <aside class="dashboard-side-column">
                <article class="dashboard-card-shell">
                    <div class="dashboard-card-head">
                        <div>
                            <h2 class="panel-title">Quick actions</h2>
                            <p class="panel-copy">Jalur cepat menuju modul kerja yang paling sering dipakai.</p>
                        </div>
                    </div>

                    <div class="quick-action-list">
                        <?php foreach ($quickActions as $action): ?>
                            <a class="quick-action-link" href="<?= htmlspecialchars($action['href'], ENT_QUOTES, 'UTF-8') ?>">
                                <span class="quick-action-icon"><i class="<?= htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                                <span class="quick-action-copy">
                                    <strong><?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($action['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dashboard-card-shell">
                    <div class="dashboard-card-head">
                        <div>
                            <h2 class="panel-title">Snapshot impor terakhir</h2>
                            <p class="panel-copy">Gunakan kartu ini untuk mengecek kualitas impor terbaru tanpa membuka detail rekap.</p>
                        </div>
                    </div>

                    <div class="insight-card dashboard-insight-card">
                        <span class="insight-label">Subjek terbaru</span>
                        <strong><?= htmlspecialchars($latestImport['subject_name'] ?? 'Belum ada impor rekap', ENT_QUOTES, 'UTF-8') ?></strong>
                        <p>
                            <?= htmlspecialchars($latestImport
                                ? trim(($latestImport['exam_type'] ?? 'UAS') . ' • ' . ($latestImport['academic_year'] ?? '') . ' ' . ($latestImport['term'] ?? ''))
                                : 'Impor terbaru akan muncul di sini setelah file nilai tersimpan.', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php if ($latestImport !== null): ?>
                            <div class="insight-chip-row">
                                <span class="insight-chip"><i class="fa-solid fa-link" aria-hidden="true"></i><?= number_format((int) ($latestImport['matched_students'] ?? 0)) ?> cocok</span>
                                <span class="insight-chip"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><?= number_format((int) ($latestImport['unmatched_students'] ?? 0)) ?> belum cocok</span>
                                <span class="insight-chip"><i class="fa-regular fa-clock" aria-hidden="true"></i><?= htmlspecialchars(dashboard_format_timestamp($latestImport['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="dashboard-card-shell">
                    <div class="dashboard-card-head">
                        <div>
                            <h2 class="panel-title">Status follow-up</h2>
                            <p class="panel-copy">Distribusi status membantu Anda memutuskan antrean yang perlu ditangani hari ini.</p>
                        </div>
                    </div>

                    <?php if (!empty($followUpBreakdown)): ?>
                        <div class="status-list">
                            <?php foreach ($followUpBreakdown as $statusRow): ?>
                                <div class="status-row">
                                    <span class="status-chip"><?= htmlspecialchars(dashboard_status_label((string) ($statusRow['status_label'] ?? 'pending')), ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= number_format((int) ($statusRow['total'] ?? 0)) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-analytics compact-empty-state">
                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                            <div>Belum ada status tindak lanjut yang tersimpan.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($recentImports)): ?>
                        <div class="recent-list">
                            <?php foreach ($recentImports as $importRow): ?>
                                <article class="recent-item">
                                    <div class="recent-item-head">
                                        <strong><?= htmlspecialchars((string) ($importRow['subject_name'] ?? 'Mata kuliah belum dipilih'), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="recent-meta-chip"><?= htmlspecialchars((string) ($importRow['exam_type'] ?? 'UAS'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <p><?= htmlspecialchars((string) ($importRow['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?> • <?= htmlspecialchars((string) ($importRow['term'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="recent-item-metrics">
                                        <span><?= number_format((int) ($importRow['matched_students'] ?? 0)) ?> cocok</span>
                                        <span><?= number_format((int) ($importRow['unmatched_students'] ?? 0)) ?> belum cocok</span>
                                        <span><?= htmlspecialchars(dashboard_format_timestamp($importRow['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </aside>
        </section>
    </main>
</body>
</html>
