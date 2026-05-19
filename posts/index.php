<?php
// ============================================================
// posts/index.php — Listar publicações
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pdo    = getConnection();
$busca  = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? '';

$sql    = 'SELECT p.*, u.name AS author FROM posts p LEFT JOIN users u ON u.id = p.user_id WHERE 1=1';
$params = [];

if ($busca !== '') {
    $sql    .= ' AND (p.title LIKE ? OR p.content LIKE ?)';
    $like    = "%$busca%";
    $params  = array_merge($params, [$like, $like]);
}
if ($status && in_array($status, ['draft','published','archived'])) {
    $sql    .= ' AND p.status = ?';
    $params[] = $status;
}
// Visitors só veem publicações publicadas
if (!hasRole('editor')) {
    $sql    .= " AND p.status = 'published'";
}
$sql .= ' ORDER BY p.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';

$pageTitle  = 'Publicações';
$activeMenu = 'posts';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <?php $alerts = ['criado' => ['success','✅ Publicação criada!'], 'editado' => ['info','✏️ Publicação atualizada!'], 'deletado' => ['danger','🗑 Publicação removida.']]; ?>
    <?php if (isset($alerts[$msg])): [$type, $text] = $alerts[$msg]; ?>
        <div class="alert alert-<?= $type ?>"><?= $text ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Publicações</h2>
        <p><?= count($posts) ?> resultado(s)</p>
    </div>
    <?php if (hasRole('editor')): ?>
        <a href="<?= APP_BASE ?>/posts/create.php" class="btn btn-primary">+ Nova publicação</a>
    <?php endif; ?>
</div>

<form method="GET" style="margin-bottom:1.2rem">
    <div class="toolbar">
        <input type="text" name="busca" class="search-input"
               placeholder="Buscar por título..." value="<?= htmlspecialchars($busca) ?>">
        <?php if (hasRole('editor')): ?>
        <select name="status" class="search-input" style="width:auto">
            <option value="">Todos os status</option>
            <?php foreach (['draft','published','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary">🔍 Filtrar</button>
        <?php if ($busca || $status): ?>
            <a href="<?= APP_BASE ?>/posts/index.php" class="btn btn-ghost">✕ Limpar</a>
        <?php endif; ?>
    </div>
</form>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Título</th>
                <th>Autor</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Atualizado</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($posts)): ?>
            <tr><td colspan="7" class="empty-cell">Nenhuma publicação encontrada.</td></tr>
        <?php else: ?>
            <?php foreach ($posts as $p): ?>
            <tr>
                <td style="color:var(--t3);font-size:.8rem"><?= $p['id'] ?></td>
                <td class="name-cell"><?= htmlspecialchars($p['title']) ?></td>
                <td><?= htmlspecialchars($p['author'] ?? '—') ?></td>
                <td><span class="badge status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
                <td><?= date('d/m/Y', strtotime($p['updated_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:.4rem">
                        <?php $me = currentUser(); ?>
                        <?php if (hasRole('admin') || (hasRole('editor') && $p['user_id'] == $me['id'])): ?>
                            <a href="<?= APP_BASE ?>/posts/edit.php?id=<?= $p['id'] ?>"
                               class="btn btn-ghost btn-sm btn-icon" title="Editar">✏️</a>
                            <a href="<?= APP_BASE ?>/posts/delete.php?id=<?= $p['id'] ?>"
                               class="btn btn-danger btn-sm btn-icon" title="Excluir">🗑</a>
                        <?php else: ?>
                            <span style="color:var(--t3);font-size:.8rem">—</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
