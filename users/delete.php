<?php
// ============================================================
// users/delete.php — Confirmar e excluir usuário
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getConnection();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ' . APP_BASE . '/users/index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { header('Location: ' . APP_BASE . '/users/index.php'); exit; }

// Impede auto-exclusão
$me = currentUser();
if ((int)$me['id'] === $id) {
    header('Location: ' . APP_BASE . '/users/index.php?msg=self_delete');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: ' . APP_BASE . '/users/index.php?msg=deletado');
    exit;
}

$pageTitle  = 'Excluir Usuário';
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2>Excluir Usuário</h2>
    <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="confirm-card">
    <h3>⚠️ Confirmar exclusão</h3>
    <p>Você está prestes a excluir o usuário <strong><?= htmlspecialchars($user['name']) ?></strong>
    (<?= htmlspecialchars($user['email']) ?>). Todas as publicações deste usuário também serão removidas.</p>
    <p style="margin-top:.8rem;color:var(--red)">Esta ação <strong>não pode ser desfeita</strong>.</p>

    <div style="display:flex;gap:.8rem;margin-top:1.5rem">
        <form method="POST">
            <button type="submit" class="btn btn-danger">🗑 Sim, excluir</button>
        </form>
        <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
