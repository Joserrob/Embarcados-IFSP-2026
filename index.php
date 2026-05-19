<?php
// ============================================================
// index.php — Página de boas-vindas
// ============================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$pdo  = getConnection();
$user = currentUser();

$totalUsers  = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPosts  = $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$myPosts     = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE user_id = ?');
$myPosts->execute([$user['id']]);
$myPosts = $myPosts->fetchColumn();

$pageTitle  = 'Início';
$activeMenu = 'home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero de boas-vindas -->
<div class="welcome-hero">
    <h1>Olá, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p>Bem-vindo ao <?= APP_NAME ?>. O que você quer fazer hoje?</p>
</div>

<!-- Quick links -->
<p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;
          color:var(--t3);margin-bottom:.8rem">Acesso rápido</p>

<div class="quick-grid">
    <a href="<?= APP_BASE ?>/posts/index.php" class="quick-card">
        <span class="quick-card-icon">◉</span>
        <span class="quick-card-text">
            <strong>Ver publicações</strong>
            <span>Listar e buscar posts</span>
        </span>
    </a>

    <?php if (hasRole('editor')): ?>
    <a href="<?= APP_BASE ?>/posts/create.php" class="quick-card">
        <span class="quick-card-icon yellow">✎</span>
        <span class="quick-card-text">
            <strong>Nova publicação</strong>
            <span>Escrever um novo post</span>
        </span>
    </a>
    <?php endif; ?>

    <a href="<?= APP_BASE ?>/users/index.php" class="quick-card">
        <span class="quick-card-icon blue">◈</span>
        <span class="quick-card-text">
            <strong>Usuários</strong>
            <span>Ver todos os membros</span>
        </span>
    </a>

    <a href="<?= APP_BASE ?>/users/<?= urlencode($user['name']) ?>" class="quick-card">
        <span class="quick-card-icon purple">◎</span>
        <span class="quick-card-text">
            <strong>Meu perfil</strong>
            <span>Ver minha página pública</span>
        </span>
    </a>

    <?php if (hasRole('admin')): ?>
    <a href="<?= APP_BASE ?>/users/create.php" class="quick-card">
        <span class="quick-card-icon">+</span>
        <span class="quick-card-text">
            <strong>Novo usuário</strong>
            <span>Criar conta manualmente</span>
        </span>
    </a>

    <a href="<?= APP_BASE ?>/admin.php" class="quick-card">
        <span class="quick-card-icon red">◆</span>
        <span class="quick-card-text">
            <strong>Painel Admin</strong>
            <span>Estatísticas e dados</span>
        </span>
    </a>
    <?php endif; ?>
</div>

<!-- Mini stats -->
<p style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;
          color:var(--t3);margin-bottom:.8rem">Visão geral</p>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-icon">◈</span>
        <span class="stat-label">Total de usuários</span>
        <span class="stat-value stat-accent"><?= $totalUsers ?></span>
        <span class="stat-sub">cadastrados no sistema</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">◉</span>
        <span class="stat-label">Publicações</span>
        <span class="stat-value stat-purple"><?= $totalPosts ?></span>
        <span class="stat-sub">no total</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">✎</span>
        <span class="stat-label">Minhas publicações</span>
        <span class="stat-value stat-blue"><?= $myPosts ?></span>
        <span class="stat-sub">criadas por mim</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">◎</span>
        <span class="stat-label">Seu role</span>
        <span class="stat-value" style="font-size:1.3rem"><?= ucfirst($user['role']) ?></span>
        <span class="stat-sub"><?= match($user['role']) {
            'admin'   => 'Acesso total ao sistema',
            'editor'  => 'Pode criar e editar posts',
            'visitor' => 'Somente leitura',
            default   => ''
        } ?></span>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>