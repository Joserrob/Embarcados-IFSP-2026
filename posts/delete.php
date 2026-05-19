<?php
// ============================================================
// posts/delete.php — Confirmar e excluir publicação
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('editor');

$pdo = getConnection();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ' . APP_BASE . '/posts/index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { header('Location: ' . APP_BASE . '/posts/index.php'); exit; }

// Editor só pode excluir o próprio post
$me = currentUser();
if (!hasRole('admin') && $post['user_id'] != $me['id']) {
    http_response_code(403);
    die(renderForbidden());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: ' . APP_BASE . '/posts/index.php?msg=deletado');
    exit;
}

$pageTitle  = 'Excluir Publicação';
$activeMenu = 'posts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Excluir Publicação</h2>
    <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="confirm-card">
    <h3>⚠️ Confirmar exclusão</h3>
    <p>Você está prestes a excluir a publicação <strong>"<?= htmlspecialchars($post['title']) ?>"</strong>.</p>
    <p style="margin-top:.8rem;color:var(--red)">Esta ação <strong>não pode ser desfeita</strong>.</p>
    <div style="display:flex;gap:.8rem;margin-top:1.5rem">
        <form method="POST">
            <button type="submit" class="btn btn-danger">🗑 Sim, excluir</button>
        </form>
        <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
