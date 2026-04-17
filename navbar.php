<nav class="navbar">
    <div class="navbar-brand">
        <i class="fa-solid fa-graduation-cap"></i> Exam Tools
    </div>
    <ul class="navbar-menu">
        <li>
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-qrcode"></i> QR Generator
            </a>
        </li>
        <li>
            <a href="master_data.php" class="<?= basename($_SERVER['PHP_SELF']) == 'master_data.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-database"></i> Master Data
            </a>
        </li>
        <li>
            <a href="jadwal.php" class="<?= basename($_SERVER['PHP_SELF']) == 'jadwal.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i> Jadwal Ujian
            </a>
        </li>
    </ul>
</nav>
