<?php
require_once __DIR__ . "/../../bootstrap.php";
app_require_router_request();

$currentRoute = $_GET["route"] ?? "dashboard";
$isAuthenticated = function_exists("auth_is_authenticated")
    ? auth_is_authenticated()
    : false;
$authenticatedUsername = function_exists("auth_get_authenticated_username")
    ? auth_get_authenticated_username()
    : null;

$showProtectedNavigation = $isAuthenticated && $currentRoute !== "login";
$navigationItems = [
    [
        "route" => "dashboard",
        "href" => "index.php?route=dashboard",
        "icon" => "fa-solid fa-table-columns",
        "label" => "Dashboard",
    ],
    [
        "route" => "qr",
        "href" => "index.php?route=qr",
        "icon" => "fa-solid fa-qrcode",
        "label" => "QR Generator",
    ],
    [
        "route" => "master-data",
        "href" => "index.php?route=master-data",
        "icon" => "fa-solid fa-database",
        "label" => "Master Data",
    ],
    [
        "route" => "schedule",
        "href" => "index.php?route=schedule",
        "icon" => "fa-solid fa-calendar-days",
        "label" => "Jadwal Ujian",
    ],
    [
        "route" => "grade-recap",
        "href" => "index.php?route=grade-recap",
        "icon" => "fa-solid fa-chart-column",
        "label" => "Rekap Nilai",
    ],
    [
        "route" => "follow-up",
        "href" => "index.php?route=follow-up",
        "icon" => "fa-solid fa-clipboard-check",
        "label" => "Remedial & Susulan",
    ],
];

$userInitials = "ET";
if (is_string($authenticatedUsername) && trim($authenticatedUsername) !== "") {
    $userInitials = strtoupper(substr(trim($authenticatedUsername), 0, 2));
}

$brandHref = $showProtectedNavigation
    ? "index.php?route=dashboard"
    : "index.php?route=login";
?>
<nav class="navbar <?= $showProtectedNavigation
    ? "navbar-authenticated"
    : "navbar-public" ?>">
    <a href="<?= htmlspecialchars(
        $brandHref,
        ENT_QUOTES,
        "UTF-8",
    ) ?>" class="navbar-brand">
        <span class="navbar-brand-mark" aria-hidden="true">
            <i class="fa-solid fa-graduation-cap"></i>
        </span>
        <span class="navbar-brand-copy">
            <strong>Exam Tools</strong>
            <small>Operational workspace</small>
        </span>
    </a>

    <?php if ($showProtectedNavigation): ?>
        <div class="navbar-panel">
            <div class="navbar-navigation">
                <ul class="navbar-menu" aria-label="Primary navigation">
                    <?php foreach ($navigationItems as $item): ?>
                        <?php $isActive = $currentRoute === $item["route"]; ?>
                        <li>
                            <a
                                href="<?= htmlspecialchars(
                                    $item["href"],
                                    ENT_QUOTES,
                                    "UTF-8",
                                ) ?>"
                                class="navbar-item-link <?= $isActive
                                    ? "active"
                                    : "" ?>"
                                aria-label="<?= htmlspecialchars(
                                    $item["label"],
                                    ENT_QUOTES,
                                    "UTF-8",
                                ) ?>"
                                <?= $isActive ? 'aria-current="page"' : "" ?>
                            >
                                <span class="navbar-item-icon" aria-hidden="true">
                                    <i class="<?= htmlspecialchars(
                                        $item["icon"],
                                        ENT_QUOTES,
                                        "UTF-8",
                                    ) ?>"></i>
                                </span>
                                <span class="navbar-item-label"><?= htmlspecialchars(
                                    $item["label"],
                                    ENT_QUOTES,
                                    "UTF-8",
                                ) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form action="index.php?route=logout" method="post" class="navbar-logout-form">
                    <button type="submit" class="navbar-item-link navbar-item-link-logout" aria-label="Logout">
                        <span class="navbar-item-icon" aria-hidden="true">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </span>
                        <span class="navbar-item-label">Logout</span>
                    </button>
                </form>
            </div>

            <div class="navbar-session-badge" aria-label="Authenticated user">
                <span class="navbar-session-avatar"><?= htmlspecialchars(
                    $userInitials,
                    ENT_QUOTES,
                    "UTF-8",
                ) ?></span>
                <span class="navbar-session-copy">
                    <strong><?= htmlspecialchars(
                        $authenticatedUsername ?? "Authenticated User",
                        ENT_QUOTES,
                        "UTF-8",
                    ) ?></strong>
                </span>
            </div>
        </div>
    <?php else: ?>
        <div class="navbar-public-panel">
            <span class="navbar-status-chip">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>Secure access</span>
            </span>
            <span class="navbar-public-note">Masuk dengan akun operator resmi</span>
        </div>
    <?php endif; ?>
</nav>
