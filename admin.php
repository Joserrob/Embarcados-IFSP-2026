<?php
// ============================================================
// admin.php — Painel administrativo (somente admin)
// ============================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

$pdo = getConnection();

// ── Contadores gerais ──────────────────────────────────────
$totalUsers     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
$inactiveUsers  = $totalUsers - $activeUsers;
$totalPosts     = (int) $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$publishedPosts = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();
$draftPosts     = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn();
$archivedPosts  = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'archived'")->fetchColumn();

// ── Distribuição de roles ──────────────────────────────────
$roleRows = $pdo->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
    ORDER BY FIELD(role, 'admin','editor','visitor')
")->fetchAll();
$roleCounts = array_column($roleRows, 'total', 'role');

// ── Usuários criados nos últimos 30 dias ───────────────────
$newUsers30 = (int) $pdo->query("
    SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

// ── Posts criados nos últimos 30 dias ─────────────────────
$newPosts30 = (int) $pdo->query("
    SELECT COUNT(*) FROM posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

// ── Top autores por número de posts ───────────────────────
$topAuthors = $pdo->query("
    SELECT u.name, u.role, u.job, COUNT(p.id) AS total,
           SUM(p.status = 'published') AS published
    FROM users u
    LEFT JOIN posts p ON p.user_id = u.id
    GROUP BY u.id
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// ── Atividade recente (últimos 10 eventos entre users+posts) ──
$recentActivity = $pdo->query("
    SELECT 'user' AS type, name AS label, created_at, 'created' AS action FROM users
    UNION ALL
    SELECT 'post' AS type, title AS label, created_at, 'created' AS action FROM posts
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll();

// ── Posts por status (para o gráfico de barras) ──────────
$statusData = [
    'Publicados' => $publishedPosts,
    'Rascunhos'  => $draftPosts,
    'Arquivados' => $archivedPosts,
];
$maxStatus = max(array_values($statusData) ?: [1]);

$pageTitle  = 'Painel Admin';
$activeMenu = 'admin';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats principais -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
    <div class="stat-card">
        <span class="stat-icon">◈</span>
        <span class="stat-label">Total usuários</span>
        <span class="stat-value stat-accent"><?= $totalUsers ?></span>
        <span class="stat-sub"><?= $activeUsers ?> ativos · <?= $inactiveUsers ?> inativos</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">◉</span>
        <span class="stat-label">Publicações</span>
        <span class="stat-value stat-purple"><?= $totalPosts ?></span>
        <span class="stat-sub"><?= $publishedPosts ?> publicadas</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">✦</span>
        <span class="stat-label">Novos usuários</span>
        <span class="stat-value stat-blue"><?= $newUsers30 ?></span>
        <span class="stat-sub">últimos 30 dias</span>
    </div>
    <div class="stat-card">
        <span class="stat-icon">✎</span>
        <span class="stat-label">Novos posts</span>
        <span class="stat-value stat-yellow"><?= $newPosts30 ?></span>
        <span class="stat-sub">últimos 30 dias</span>
    </div>
</div>

<div class="admin-grid">
    <!-- Distribuição de roles -->
    <div class="card">
        <div style="font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;color:var(--t1);margin-bottom:1.2rem">
            Distribuição de Roles
        </div>
        <?php
        $roleConfig = [
            'admin'   => ['Admin',   'var(--accent)'],
            'editor'  => ['Editor',  'var(--purple)'],
            'visitor' => ['Visitor', 'var(--blue)'],
        ];
        foreach ($roleConfig as $role => [$label, $color]):
            $count = (int)($roleCounts[$role] ?? 0);
            $pct   = $totalUsers > 0 ? round(($count / $totalUsers) * 100) : 0;
        ?>
        <div class="role-bar-row">
            <span class="role-bar-label"><?= $label ?></span>
            <div class="role-bar-track">
                <div class="role-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <span class="role-bar-count"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-soft);
                    display:flex;gap:1rem;flex-wrap:wrap">
            <div style="font-size:.8rem;color:var(--t3)">
                <span style="color:var(--t2);font-weight:500"><?= $activeUsers ?></span> ativos
            </div>
            <div style="font-size:.8rem;color:var(--t3)">
                <span style="color:var(--t2);font-weight:500"><?= $inactiveUsers ?></span> inativos
            </div>
        </div>
    </div>

    <!-- Posts por status -->
    <div class="card">
        <div style="font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;color:var(--t1);margin-bottom:1.2rem">
            Posts por Status
        </div>
        <?php
        $statusConfig = [
            'Publicados' => ['var(--accent)',  $publishedPosts],
            'Rascunhos'  => ['var(--yellow)',  $draftPosts],
            'Arquivados' => ['var(--t3)',      $archivedPosts],
        ];
        foreach ($statusConfig as $label => [$color, $count]):
            $pct = $totalPosts > 0 ? round(($count / $totalPosts) * 100) : 0;
        ?>
        <div class="role-bar-row">
            <span class="role-bar-label" style="width:80px"><?= $label ?></span>
            <div class="role-bar-track">
                <div class="role-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <span class="role-bar-count"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-soft)">
            <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-ghost btn-sm" style="padding-left:0">
                Ver todas as publicações →
            </a>
        </div>
    </div>

    <!-- Top autores -->
    <div class="card">
        <div style="font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;color:var(--t1);margin-bottom:1.2rem">
            Top Autores
        </div>
        <?php foreach ($topAuthors as $i => $a): ?>
        <div style="display:flex;align-items:center;gap:.8rem;
                    padding:.6rem 0;border-bottom:1px solid var(--border-soft)">
            <span style="font-family:'Sora',sans-serif;font-size:.8rem;font-weight:700;
                         color:var(--t3);width:18px;text-align:center;flex-shrink:0">
                <?= $i + 1 ?>
            </span>
            <span style="width:32px;height:32px;border-radius:50%;background:var(--accent-dim);
                         border:2px solid var(--accent);color:var(--accent);
                         font-family:'Sora',sans-serif;font-weight:700;font-size:.8rem;
                         display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($a['name'], 0, 1)) ?>
            </span>
            <div style="flex:1;min-width:0">
                <a href="<?= APP_BASE ?>/users/<?= urlencode($a['name']) ?>"
                   style="font-size:.87rem;font-weight:500;color:var(--t1);
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block">
                    <?= htmlspecialchars($a['name']) ?>
                </a>
                <span class="badge role-<?= $a['role'] ?>" style="margin-top:.15rem"><?= ucfirst($a['role']) ?></span>
            </div>
            <div style="text-align:right;flex-shrink:0">
                <div style="font-family:'Sora',sans-serif;font-size:1rem;font-weight:700;color:var(--t1)">
                    <?= $a['total'] ?>
                </div>
                <div style="font-size:.72rem;color:var(--t3)"><?= $a['published'] ?> pub.</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topAuthors)): ?>
            <p style="color:var(--t3);font-size:.875rem">Nenhum dado ainda.</p>
        <?php endif; ?>
    </div>

    <!-- Atividade recente -->
    <div class="card">
        <div style="font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;color:var(--t1);margin-bottom:1.2rem">
            Atividade Recente
        </div>
        <?php foreach ($recentActivity as $ev): ?>
        <div class="activity-item">
            <span class="activity-dot <?= $ev['type'] === 'post' ? 'purple' : '' ?>"></span>
            <div>
                <div class="activity-text">
                    <?php if ($ev['type'] === 'user'): ?>
                        Usuário <strong><?= htmlspecialchars($ev['label']) ?></strong> cadastrado
                    <?php else: ?>
                        Post <strong>"<?= htmlspecialchars(mb_strimwidth($ev['label'], 0, 40, '…')) ?>"</strong> criado
                    <?php endif; ?>
                </div>
                <div class="activity-time"><?= date('d/m/Y \à\s H:i', strtotime($ev['created_at'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recentActivity)): ?>
            <p style="color:var(--t3);font-size:.875rem">Nenhuma atividade ainda.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Links rápidos admin -->
<div class="card" style="max-width:100%">
    <div style="font-family:'Sora',sans-serif;font-size:.9rem;font-weight:600;color:var(--t1);margin-bottom:1.2rem">
        Ações Administrativas
    </div>
    <div style="display:flex;gap:.8rem;flex-wrap:wrap">
        <a href="<?= APP_BASE ?>/users/create.php" class="btn btn-secondary">+ Criar usuário</a>
        <a href="<?= APP_BASE ?>/posts/create.php" class="btn btn-secondary">+ Criar publicação</a>
        <a href="<?= APP_BASE ?>/users/index.php"  class="btn btn-ghost">Gerenciar usuários</a>
        <a href="<?= APP_BASE ?>/posts/index.php"  class="btn btn-ghost">Gerenciar posts</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>