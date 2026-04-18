<?php
$currentRoute = $_GET['route'] ?? 'qr';
?>
<nav class="navbar">
    <div class="navbar-brand">
        <i class="fa-solid fa-graduation-cap"></i> Exam Tools
    </div>
    <ul class="navbar-menu">
        <li>
            <a href="index.php?route=qr" class="<?= $currentRoute === 'qr' ? 'active' : '' ?>">
                <i class="fa-solid fa-qrcode"></i> QR Generator
            </a>
        </li>
        <li>
            <a href="index.php?route=master-data" class="<?= $currentRoute === 'master-data' ? 'active' : '' ?>">
                <i class="fa-solid fa-database"></i> Master Data
            </a>
        </li>
        <li>
            <a href="index.php?route=schedule" class="<?= $currentRoute === 'schedule' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i> Jadwal Ujian
            </a>
        </li>
        <li>
            <a href="index.php?route=grade-recap" class="<?= $currentRoute === 'grade-recap' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-column"></i> Rekap Nilai
            </a>
        </li>
    </ul>
</nav>
