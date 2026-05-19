<?php
// ============================================================
// includes/header.php — Topbar de navegação
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garante que $user existe mesmo que a página não o defina
if (!isset($user)) {
    $user = function_exists('currentUser') ? (currentUser() ?? []) : [];
}

// Flash messages
$_flash     = $_SESSION['flash']      ?? null;
$_flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

// Iniciais do avatar
$_initials = 'U';
if (!empty($user['name'])) {
    $parts     = explode(' ', trim($user['name']));
    $_initials = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $_initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
}

// Itens de navegação [slug, url, ícone, label, role mínima (null = todos)]
$_navItems = [
    ['dashboard',         APP_BASE . '/dashboard.php',         '⚡', 'Dashboard',   null],
    ['temperature_chart', APP_BASE . '/temperature_chart.php', '🌡️', 'Temperatura', null],
    ['history',           APP_BASE . '/history.php',           '📋', 'Histórico',   null],
    ['users',             APP_BASE . '/users/index.php',       '👥', 'Usuários',    'admin'],
    ['admin',             APP_BASE . '/admin.php',             '⚙️', 'Admin',       'admin'],
];

$_visibleNav = array_filter($_navItems, function($item) use ($user) {
    return $item[4] === null || (isset($user['role']) && $user['role'] === $item[4]);
});

$_active = $activeMenu ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔥</text></svg>">
    <link rel="stylesheet" href="<?= APP_BASE ?>/style.css">
    <meta name="theme-color" content="#1A1714">
</head>
<body>

<!-- ── Topbar ─────────────────────────────────────────────── -->
<nav class="topbar">
    <div class="topbar-inner">

        <a href="<?= APP_BASE ?>/dashboard.php" class="topbar-logo">
            <span class="logo-icon">🔥</span>
            <span class="logo-text">Fire<span>Watch</span></span>
        </a>

        <!-- Nav desktop -->
        <div class="topbar-nav">
            <?php foreach ($_visibleNav as $item): ?>
                <a href="<?= $item[1] ?>"
                   class="nav-link <?= $_active === $item[0] ? 'active' : '' ?>">
                    <span class="nav-icon"><?= $item[2] ?></span>
                    <?= htmlspecialchars($item[3]) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Usuário desktop -->
        <?php if (!empty($user['name'])): ?>
        <div class="topbar-user">
            <!-- <div class="user-chip">
                <div class="user-avatar"><?= htmlspecialchars($_initials) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="user-role"><?= htmlspecialchars($user['role'] ?? '') ?></div>
                </div>
            </div> -->
            <a href="<?= APP_BASE ?>/logout.php" class="topbar-logout">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Sair
            </a>
        </div>
        <?php endif; ?>

        <!-- Hamburger mobile -->
        <button class="topbar-hamburger" id="hamburgerBtn"
                aria-label="Abrir menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

    </div>
</nav>

<!-- Menu mobile -->
<div class="topbar-mobile-menu" id="mobileMenu">
    <?php foreach ($_visibleNav as $item): ?>
        <a href="<?= $item[1] ?>"
           class="mobile-nav-link <?= $_active === $item[0] ? 'active' : '' ?>">
            <span><?= $item[2] ?></span>
            <?= htmlspecialchars($item[3]) ?>
        </a>
    <?php endforeach; ?>

    <?php if (!empty($user['name'])): ?>
        <div class="mobile-divider"></div>
        <div style="padding:.4rem .85rem;display:flex;align-items:center;gap:.55rem">
            <div class="user-avatar" style="width:28px;height:28px;font-size:.75rem">
                <?= htmlspecialchars($_initials) ?>
            </div>
            <div>
                <div style="font-size:.82rem;color:rgba(255,255,255,.8);font-weight:500">
                    <?= htmlspecialchars($user['name']) ?>
                </div>
                <div style="font-size:.7rem;color:rgba(255,255,255,.4)">
                    <?= htmlspecialchars($user['role'] ?? '') ?>
                </div>
            </div>
        </div>
        <a href="<?= APP_BASE ?>/logout.php" class="mobile-nav-link" style="color:#f87171">
            ↪ Sair
        </a>
    <?php endif; ?>
</div>

<!-- Flash message -->
<?php if ($_flash): ?>
<div class="flash-bar <?= $_flashType === 'error' ? 'error' : 'success' ?>">
    <span><?= $_flashType === 'error' ? '✘' : '✔' ?></span>
    <?= htmlspecialchars($_flash) ?>
</div>
<?php endif; ?>

<!-- Conteúdo -->
<main class="main-content" id="main-content">
    <div class="container fade-in">

<script>
(function () {
    var btn  = document.getElementById('hamburgerBtn');
    var menu = document.getElementById('mobileMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function () {
        var open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', function (e) {
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>