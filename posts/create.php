<?php
// ============================================================
// posts/create.php — Criar publicação (editor ou admin)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('editor');

$erros = [];
$dados = ['title' => '', 'content' => '', 'status' => 'draft'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'title'   => trim($_POST['title']   ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'status'  => $_POST['status'] ?? 'draft',
    ];

    if (empty($dados['title']))   $erros[] = 'Título é obrigatório.';
    if (empty($dados['content'])) $erros[] = 'Conteúdo é obrigatório.';
    if (!in_array($dados['status'], ['draft','published','archived']))
                                  $erros[] = 'Status inválido.';

    if (empty($erros)) {
        $pdo  = getConnection();
        $me   = currentUser();
        $base = slugify($dados['title']);
        $slug = $base;
        $i    = 1;
        // Garante slug único
        while ($pdo->prepare('SELECT id FROM posts WHERE slug = ?')->execute([$slug]) &&
               $pdo->prepare('SELECT id FROM posts WHERE slug = ?')->execute([$slug]) &&
               $pdo->query("SELECT id FROM posts WHERE slug = '$slug'")->fetch()) {
            $slug = $base . '-' . $i++;
        }

        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, slug, content, status) VALUES (?,?,?,?,?)');
        $stmt->execute([$me['id'], $dados['title'], $slug, $dados['content'], $dados['status']]);
        header('Location: ' . APP_BASE . '/posts/index.php?msg=criado');
        exit;
    }
}

$pageTitle  = 'Nova Publicação';
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
        <h2>Nova Publicação</h2>
        <p>Escreva e publique um novo conteúdo</p>
    </div>
    <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-card" style="max-width:700px">
    <div class="form-title">Conteúdo</div>
    <form method="POST" novalidate>
        <div class="form-grid">
            <div class="form-group">
                <label>Título *</label>
                <input type="text" name="title" value="<?= htmlspecialchars($dados['title']) ?>"
                       required placeholder="Título da publicação">
            </div>
            <div class="form-group">
                <label>Conteúdo *</label>
                <textarea name="content" rows="10" required
                          placeholder="Escreva o conteúdo aqui..."><?= htmlspecialchars($dados['content']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft' => 'Rascunho', 'published' => 'Publicado', 'archived' => 'Arquivado'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $dados['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar publicação</button>
            <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
