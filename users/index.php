<?php
// ============================================================
// users/index.php — Listar usuários
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pdo    = getConnection();
$busca  = trim($_GET['busca'] ?? '');
$filtro = $_GET['role'] ?? '';

$sql    = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($busca !== '') {
    $sql    .= ' AND (name LIKE ? OR email LIKE ? OR job LIKE ?)';
    $like    = "%$busca%";
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($filtro && in_array($filtro, ['admin', 'editor', 'visitor'])) {
    $sql    .= ' AND role = ?';
    $params[] = $filtro;
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';

$pageTitle  = 'Usuários';
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <?php $alerts = ['criado' => ['success','✅ Usuário criado!'], 'editado' => ['info','✏️ Usuário atualizado!'], 'deletado' => ['danger','🗑 Usuário removido.']]; ?>
    <?php if (isset($alerts[$msg])): [$type, $text] = $alerts[$msg]; ?>
        <div class="alert alert-<?= $type ?>"><?= $text ?></div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Usuários</h2>
        <p><?= count($users) ?> resultado(s)</p>
    </div>
    <?php if (hasRole('admin')): ?>
        <a href="<?= APP_BASE ?>/users/create.php" class="btn btn-primary">+ Novo usuário</a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<form method="GET" style="margin-bottom:1.2rem">
    <div class="toolbar">
        <input type="text" name="busca" class="search-input"
               placeholder="Buscar nome, e-mail, cargo..." value="<?= htmlspecialchars($busca) ?>">
        <select name="role" class="search-input" style="width:auto">
            <option value="">Todos os roles</option>
            <?php foreach (['admin','editor','visitor'] as $r): ?>
                <option value="<?= $r ?>" <?= $filtro === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">🔍 Filtrar</button>
        <?php if ($busca || $filtro): ?>
            <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-ghost">✕ Limpar</a>
        <?php endif; ?>
    </div>
</form>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Cargo</th>
                <th>Role</th>
                <th>Status</th>
                <th>Cadastrado</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="8" class="empty-cell">Nenhum usuário encontrado.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
            <tr class="<?= $u['active'] ? '' : 'inactive' ?>">
                <td style="color:var(--t3);font-size:.8rem"><?= $u['id'] ?></td>
                <td class="name-cell"><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['job'] ?? '—') ?></td>
                <td><span class="badge role-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                    <?php if ($u['active']): ?>
                        <span class="badge active-yes">Ativo</span>
                    <?php else: ?>
                        <span class="badge active-no">Inativo</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:.4rem">
                        <?php if (hasRole('admin')): ?>
                            <a href="<?= APP_BASE ?>/users/edit.php?id=<?= $u['id'] ?>"
                               class="btn btn-ghost btn-sm btn-icon" title="Editar">✏️</a>
                            <a href="<?= APP_BASE ?>/users/delete.php?id=<?= $u['id'] ?>"
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
