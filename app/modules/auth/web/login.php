<?php
require_once __DIR__ . "/../../../bootstrap.php";
app_require_router_request();
require_once PROJECT_ROOT . "/app/shared/lib/auth.php";

$errorMessage = auth_pull_flash_message("login_error");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Exam Tools</title>
    <link rel="stylesheet" href="assets/css/core/base.css">
    <link rel="stylesheet" href="assets/css/components/layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="page-login">
    <?php require_once PROJECT_ROOT . "/app/shared/layout/navbar.php"; ?>

    <main class="app-screen login-screen">
        <section class="login-panel">
            <div class="login-copy-block">
                <span class="section-eyebrow">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    Secure operator access
                </span>
                <h1 class="login-title">Masuk ke pusat kendali ujian.</h1>
                <p class="section-lead">Kelola master data, jadwal, rekap nilai, QR, dan tindak lanjut dari satu workspace yang terhubung ke routing serta sesi server-side aplikasi.</p>
            </div>

            <div class="login-stack">
                <?php if ($errorMessage !== null): ?>
                    <div class="notice-banner notice-banner-error">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span><?= htmlspecialchars(
                            $errorMessage,
                            ENT_QUOTES,
                            "UTF-8",
                        ) ?></span>
                    </div>
                <?php endif; ?>

                <form class="login-form" action="index.php?route=login" method="post">
                    <div class="auth-field">
                        <label for="username">Username</label>
                        <input class="auth-control" type="text" id="username" name="username" autocomplete="username" placeholder="Masukkan username operator" required>
                    </div>

                    <div class="auth-field">
                        <label for="password">Password</label>
                        <input class="auth-control" type="password" id="password" name="password" autocomplete="current-password" placeholder="Masukkan password akun operator" required>
                    </div>

                    <div class="form-split">
                        <span class="form-helper-text">Gunakan akun resmi yang diterbitkan administrator ujian.</span>
                    </div>

                    <button type="submit" class="button-primary">
                        <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                        <span>Masuk ke dashboard</span>
                    </button>
                </form>

                <div class="support-note">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>Halaman login ini hanya menampilkan antarmuka. Kredensial tetap divalidasi oleh fondasi autentikasi PHP yang sudah aktif di router.</span>
                </div>
            </div>
        </section>

        <aside class="login-aside">
            <div class="login-copy-block">
                <span class="section-eyebrow section-eyebrow-muted">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    Akses operator
                </span>
                <h2 class="panel-title">Masuk untuk membuka workspace operasional lengkap</h2>
                <p class="section-lead">Semua ringkasan data, analitik kesiapan, dan konteks akademik hanya ditampilkan setelah autentikasi berhasil.</p>
            </div>

            <div class="metric-grid">
                <article class="metric-card">
                    <span class="metric-label">Akses terpusat</span>
                    <strong class="metric-value">01</strong>
                    <span class="metric-note">Satu pintu masuk ke seluruh modul</span>
                </article>
                <article class="metric-card">
                    <span class="metric-label">Session server-side</span>
                    <strong class="metric-value">PHP</strong>
                    <span class="metric-note">Validasi tidak dikirim ke source halaman</span>
                </article>
                <article class="metric-card">
                    <span class="metric-label">Route aman</span>
                    <strong class="metric-value">401</strong>
                    <span class="metric-note">API publik ditahan sebelum login</span>
                </article>
                <article class="metric-card metric-card-attention">
                    <span class="metric-label">Dashboard setelah masuk</span>
                    <strong class="metric-value">→</strong>
                    <span class="metric-note">Analitik lengkap baru tampil pasca autentikasi</span>
                </article>
            </div>

            <div class="insight-stack">
                <article class="insight-card">
                    <span class="insight-label">Boundary autentikasi</span>
                    <strong>Login publik, insight privat</strong>
                    <p>Halaman ini tidak menampilkan statistik internal. Begitu berhasil masuk, dashboard akan memuat ringkasan operasional yang relevan untuk pekerjaan harian.</p>
                </article>

                <div class="feature-list">
                    <div class="feature-row">
                        <span class="feature-icon"><i class="fa-solid fa-table-columns" aria-hidden="true"></i></span>
                        <div>
                            <strong>Dashboard analitis</strong>
                            <p>Lihat kesiapan data, impor terbaru, dan item follow-up yang masih perlu perhatian.</p>
                        </div>
                    </div>
                    <div class="feature-row">
                        <span class="feature-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                        <div>
                            <strong>Workflow tetap terhubung</strong>
                            <p>Berpindah dari login ke dashboard, jadwal, rekap, dan follow-up tanpa melewati router atau aset shared aplikasi.</p>
                        </div>
                    </div>
                    <div class="feature-row">
                        <span class="feature-icon"><i class="fa-solid fa-wave-square" aria-hidden="true"></i></span>
                        <div>
                            <strong>Praktis untuk operasional harian</strong>
                            <p>Setelah masuk, ringkasan server-side siap membantu memutuskan pekerjaan mana yang perlu didahulukan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>
