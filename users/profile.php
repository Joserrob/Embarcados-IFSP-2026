<?php
// ============================================================
// users/profile.php — Perfil público de um usuário
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pdo = getConnection();

// Aceita /users/profile.php?name=NomeAqui
// Com .htaccess também funciona via /users/NomeAqui
$name = trim($_GET['name'] ?? '');

if (empty($name)) {
    header('Location: ' . APP_BASE . '/users/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? AND active = 1 LIMIT 1");
$stmt->execute([$name]);
$profile = $stmt->fetch();

if (!$profile) {
    // Tenta busca parcial como fallback
    $stmt = $pdo->prepare("SELECT * FROM users WHERE name LIKE ? AND active = 1 LIMIT 1");
    $stmt->execute([$name . '%']);
    $profile = $stmt->fetch();
}

if (!$profile) {
    http_response_code(404);
    $pageTitle  = 'Usuário não encontrado';
    $activeMenu = 'users';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div style="text-align:center;padding:4rem 0">
            <div style="font-size:3rem;margin-bottom:1rem">◌</div>
            <h2 style="color:var(--t1);font-family:\'Sora\',sans-serif;margin-bottom:.5rem">Usuário não encontrado</h2>
            <p style="color:var(--t2);margin-bottom:1.5rem">Nenhum usuário ativo com este nome foi encontrado.</p>
            <a href="' . APP_BASE . '/users/index.php" class="btn btn-secondary">← Ver todos os usuários</a>
          </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Publicações do usuário (visitors só veem publicadas)
$statusFilter = hasRole('editor') ? "AND status IN ('published','draft')" : "AND status = 'published'";
$stmtPosts = $pdo->prepare("
    SELECT id, title, slug, status, created_at
    FROM posts
    WHERE user_id = ? $statusFilter
    ORDER BY created_at DESC
");
$stmtPosts->execute([$profile['id']]);
$posts = $stmtPosts->fetchAll();

// Contadores
$totalPosts     = count($posts);
$publishedPosts = count(array_filter($posts, fn($p) => $p['status'] === 'published'));

$me = currentUser();
$isOwnProfile = ((int)$me['id'] === (int)$profile['id']);
$canEdit      = hasRole('admin');

$pageTitle  = $profile['name'];
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:840px">

    <!-- Breadcrumb -->
    <nav style="font-size:.8rem;color:var(--t3);margin-bottom:1.5rem;display:flex;align-items:center;gap:.4rem">
        <a href="<?= APP_BASE ?>/users/index.php" style="color:var(--t3)">Usuários</a>
        <span>›</span>
        <span style="color:var(--t2)"><?= htmlspecialchars($profile['name']) ?></span>
    </nav>

    <!-- Card de perfil -->
    <div class="card" style="margin-bottom:1.5rem">
        <div style="display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap">

            <!-- Avatar grande -->
            <div style="width:72px;height:72px;border-radius:50%;background:var(--accent-dim);
                        border:3px solid var(--accent);color:var(--accent);font-family:'Sora',sans-serif;
                        font-weight:700;font-size:1.8rem;display:flex;align-items:center;
                        justify-content:center;flex-shrink:0;box-shadow:0 0 24px var(--accent-dim)">
                <?= mb_strtoupper(mb_substr($profile['name'], 0, 1)) ?>
            </div>

            <!-- Info principal -->
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin-bottom:.4rem">
                    <h1 style="font-family:'Sora',sans-serif;font-size:1.4rem;font-weight:700;
                               color:var(--t1);letter-spacing:-.02em">
                        <?= htmlspecialchars($profile['name']) ?>
                    </h1>
                    <span class="badge role-<?= $profile['role'] ?>"><?= ucfirst($profile['role']) ?></span>
                    <?php if ($isOwnProfile): ?>
                        <span class="badge" style="background:var(--purple-dim);color:var(--purple)">Você</span>
                    <?php endif; ?>
                </div>

                <div style="color:var(--t2);font-size:.875rem;margin-bottom:.8rem">
                    <?= htmlspecialchars($profile['email']) ?>
                </div>

                <?php if ($profile['job']): ?>
                    <div style="display:inline-flex;align-items:center;gap:.4rem;
                                background:var(--surface);border:1px solid var(--border-soft);
                                border-radius:20px;padding:.25rem .8rem;font-size:.8rem;color:var(--t2)">
                        ◈ <?= htmlspecialchars($profile['job']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ações -->
            <?php if ($canEdit || $isOwnProfile): ?>
                <div style="display:flex;gap:.5rem;flex-shrink:0">
                    <?php if ($canEdit): ?>
                        <a href="<?= APP_BASE ?>/users/edit.php?id=<?= $profile['id'] ?>"
                           class="btn btn-secondary btn-sm">✏️ Editar perfil</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats do usuário -->
        <div style="display:flex;gap:2rem;margin-top:1.5rem;padding-top:1.2rem;
                    border-top:1px solid var(--border-soft);flex-wrap:wrap">
            <div style="text-align:center">
                <div style="font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:700;color:var(--t1)">
                    <?= $totalPosts ?>
                </div>
                <div style="font-size:.75rem;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">
                    Publicações
                </div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:700;color:var(--accent)">
                    <?= $publishedPosts ?>
                </div>
                <div style="font-size:.75rem;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">
                    Publicadas
                </div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Sora',sans-serif;font-size:1.5rem;font-weight:700;color:var(--t1)">
                    <?= date('M Y', strtotime($profile['created_at'])) ?>
                </div>
                <div style="font-size:.75rem;color:var(--t3);text-transform:uppercase;letter-spacing:.05em">
                    Membro desde
                </div>
            </div>
        </div>
    </div>

    <!-- Publicações do usuário -->
    <div style="margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between">
        <h2 style="font-family:'Sora',sans-serif;font-size:1rem;font-weight:600;color:var(--t1)">
            Publicações de <?= htmlspecialchars($profile['name']) ?>
        </h2>
        <span style="font-size:.8rem;color:var(--t3)"><?= $totalPosts ?> no total</span>
    </div>

    <?php if (empty($posts)): ?>
        <div class="card" style="text-align:center;padding:3rem;color:var(--t3)">
            <div style="font-size:2rem;margin-bottom:.8rem">◌</div>
            <p>Nenhuma publicação ainda.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.8rem">
            <?php foreach ($posts as $p): ?>
                <a href="<?= APP_BASE ?>/posts/view.php?id=<?= $p['id'] ?>"
                   style="display:block;text-decoration:none;color:inherit">
                    <div class="card" style="transition:border-color var(--transition),transform var(--transition);
                                            display:flex;align-items:center;gap:1.2rem;padding:1.1rem 1.4rem"
                         onmouseover="this.style.borderColor='var(--border)';this.style.transform='translateX(4px)'"
                         onmouseout="this.style.borderColor='';this.style.transform=''">

                        <!-- Ícone de status -->
                        <span style="font-size:1.2rem;flex-shrink:0;color:var(--t3)">
                            <?= match($p['status']) {
                                'published' => '<span style="color:var(--accent)">◉</span>',
                                'draft'     => '<span style="color:var(--yellow)">◎</span>',
                                'archived'  => '◌',
                            } ?>
                        </span>

                        <!-- Título + meta -->
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:500;color:var(--t1);font-size:.925rem;
                                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                                        margin-bottom:.2rem">
                                <?= htmlspecialchars($p['title']) ?>
                            </div>
                            <div style="font-size:.78rem;color:var(--t3)">
                                <?= date('d \d\e F \d\e Y', strtotime($p['created_at'])) ?>
                            </div>
                        </div>

                        <!-- Badge de status -->
                        <span class="badge status-<?= $p['status'] ?>" style="flex-shrink:0">
                            <?= ucfirst($p['status']) ?>
                        </span>

                        <span style="color:var(--t3);font-size:1rem">›</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:2rem">
        <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-ghost">← Voltar aos usuários</a>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>