<?php
// ============================================================
// posts/edit.php — Editar publicação
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('editor');

$pdo = getConnection();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ' . APP_BASE . '/posts/index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
$stmt->execute([$id]);
$dados = $stmt->fetch();
if (!$dados) { header('Location: ' . APP_BASE . '/posts/index.php'); exit; }

// Editor só pode editar o próprio post; admin pode tudo
$me = currentUser();
if (!hasRole('admin') && $dados['user_id'] != $me['id']) {
    http_response_code(403);
    die(renderForbidden());
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = array_merge($dados, [
        'title'   => trim($_POST['title']   ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'status'  => $_POST['status'] ?? 'draft',
    ]);

    if (empty($dados['title']))   $erros[] = 'Título é obrigatório.';
    if (empty($dados['content'])) $erros[] = 'Conteúdo é obrigatório.';

    if (empty($erros)) {
        $stmt = $pdo->prepare('UPDATE posts SET title=?,content=?,status=? WHERE id=?');
        $stmt->execute([$dados['title'], $dados['content'], $dados['status'], $id]);
        header('Location: ' . APP_BASE . '/posts/index.php?msg=editado');
        exit;
    }
}

$pageTitle  = 'Editar Publicação';
$activeMenu = 'posts';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($erros): ?>
    <div class="alert alert-danger">
        <span>⚠</span>
        <ul><?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Editar Publicação <span style="color:var(--t3);font-weight:400">#<?= $id ?></span></h2>
        <p><?= htmlspecialchars($dados['title']) ?></p>
    </div>
    <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-card" style="max-width:700px">
    <div class="form-title">Conteúdo</div>
    <form method="POST" novalidate>
        <div class="form-grid">
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($dados['title']) ?>" required>
            </div>
            <div class="form-group">
                <label>Conteúdo *</label>
                <textarea name="content" rows="10" required><?= htmlspecialchars($dados['content']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft' => 'Rascunho', 'published' => 'Publicado', 'archived' => 'Arquivado'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $dados['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="font-size:.8rem;color:var(--t3)">
                Slug: <code style="color:var(--t2)"><?= htmlspecialchars($dados['slug']) ?></code>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar alterações</button>
            <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
