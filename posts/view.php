<?php
// ============================================================
// posts/view.php — Visualizar publicação individual
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pdo = getConnection();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . APP_BASE . '/posts/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.name AS author_name, u.job AS author_job, u.role AS author_role, u.id AS author_id
    FROM posts p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: ' . APP_BASE . '/posts/index.php');
    exit;
}

// Visitors só veem posts publicados
if (!hasRole('editor') && $post['status'] !== 'published') {
    http_response_code(403);
    die(renderForbidden());
}

$me = currentUser();
$canEdit = hasRole('admin') || (hasRole('editor') && $post['user_id'] == $me['id']);

$pageTitle  = $post['title'];
$activeMenu = 'posts';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:780px">

    <!-- Breadcrumb -->
    <nav style="font-size:.8rem;color:var(--t3);margin-bottom:1.5rem;display:flex;align-items:center;gap:.4rem">
        <a href="<?= APP_BASE ?>/posts/index.php" style="color:var(--t3)">Publicações</a>
        <span>›</span>
        <span style="color:var(--t2)"><?= htmlspecialchars($post['title']) ?></span>
    </nav>

    <!-- Header do post -->
    <div style="margin-bottom:2rem">
        <!-- Status badge -->
        <span class="badge status-<?= $post['status'] ?>" style="margin-bottom:1rem;display:inline-flex">
            <?= match($post['status']) {
                'published' => '◉ Publicado',
                'draft'     => '◎ Rascunho',
                'archived'  => '◌ Arquivado',
            } ?>
        </span>

        <h1 style="font-family:'Sora',sans-serif;font-size:1.8rem;font-weight:700;color:var(--t1);
                   line-height:1.25;letter-spacing:-.03em;margin-bottom:1.2rem">
            <?= htmlspecialchars($post['title']) ?>
        </h1>

        <!-- Meta: autor + datas -->
        <div style="display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;
                    padding-bottom:1.2rem;border-bottom:1px solid var(--border-soft)">
            <!-- Avatar + link do autor -->
            <a href="<?= APP_BASE ?>/users/<?= urlencode($post['author_name']) ?>"
               style="display:flex;align-items:center;gap:.6rem;text-decoration:none;color:inherit">
                <span style="width:36px;height:36px;border-radius:50%;background:var(--accent-dim);
                             border:2px solid var(--accent);color:var(--accent);font-family:'Sora',sans-serif;
                             font-weight:700;font-size:.9rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <?= mb_strtoupper(mb_substr($post['author_name'] ?? '?', 0, 1)) ?>
                </span>
                <div>
                    <div style="font-size:.875rem;font-weight:500;color:var(--t1)">
                        <?= htmlspecialchars($post['author_name'] ?? 'Desconhecido') ?>
                    </div>
                    <?php if ($post['author_job']): ?>
                        <div style="font-size:.75rem;color:var(--t3)"><?= htmlspecialchars($post['author_job']) ?></div>
                    <?php endif; ?>
                </div>
            </a>

            <div style="width:1px;height:28px;background:var(--border-soft)"></div>

            <div style="font-size:.8rem;color:var(--t3);display:flex;flex-direction:column;gap:.1rem">
                <span>Publicado em <?= date('d \d\e F \d\e Y', strtotime($post['created_at'])) ?></span>
                <?php if ($post['updated_at'] !== $post['created_at']): ?>
                    <span>Atualizado em <?= date('d/m/Y H:i', strtotime($post['updated_at'])) ?></span>
                <?php endif; ?>
            </div>

            <!-- Ações (admin/editor dono) -->
            <?php if ($canEdit): ?>
                <div style="margin-left:auto;display:flex;gap:.5rem">
                    <a href="<?= APP_BASE ?>/posts/edit.php?id=<?= $post['id'] ?>"
                       class="btn btn-secondary btn-sm">✏️ Editar</a>
                    <a href="<?= APP_BASE ?>/posts/delete.php?id=<?= $post['id'] ?>"
                       class="btn btn-danger btn-sm">🗑 Excluir</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conteúdo -->
    <article style="color:var(--t2);font-size:1rem;line-height:1.85;letter-spacing:.01em;
                    white-space:pre-wrap;word-break:break-word">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
    </article>

    <!-- Footer do post -->
    <div style="margin-top:3rem;padding-top:1.5rem;border-top:1px solid var(--border-soft);
                display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-ghost">← Voltar às publicações</a>
        <div style="font-size:.78rem;color:var(--t3)">
            Slug: <code style="color:var(--t2)"><?= htmlspecialchars($post['slug']) ?></code>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>