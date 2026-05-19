<?php
// ============================================================
// users/edit.php — Editar usuário (apenas admin)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pdo = getConnection();
$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header('Location: ' . APP_BASE . '/users/index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$dados = $stmt->fetch();
if (!$dados) { header('Location: ' . APP_BASE . '/users/index.php'); exit; }

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'id'     => $id,
        'name'   => trim($_POST['name']   ?? ''),
        'email'  => trim($_POST['email']  ?? ''),
        'job'    => trim($_POST['job']    ?? ''),
        'role'   => $_POST['role']  ?? 'visitor',
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $nova_senha = $_POST['password']         ?? '';
    $confirm    = $_POST['password_confirm'] ?? '';

    if (empty($dados['name']))   $erros[] = 'Nome é obrigatório.';
    if (empty($dados['email']))  $erros[] = 'E-mail é obrigatório.';
    elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL))
                                 $erros[] = 'E-mail inválido.';
    if ($nova_senha !== '' && strlen($nova_senha) < 8) $erros[] = 'Senha deve ter ao menos 8 caracteres.';
    if ($nova_senha !== '' && $nova_senha !== $confirm)  $erros[] = 'As senhas não coincidem.';

    if (empty($erros)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check->execute([$dados['email'], $id]);
        if ($check->fetch()) {
            $erros[] = 'Este e-mail já está em uso por outro usuário.';
        } else {
            if ($nova_senha !== '') {
                $hash = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('UPDATE users SET name=?,email=?,password=?,role=?,job=?,active=? WHERE id=?');
                $stmt->execute([$dados['name'],$dados['email'],$hash,$dados['role'],$dados['job'],$dados['active'],$id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?,email=?,role=?,job=?,active=? WHERE id=?');
                $stmt->execute([$dados['name'],$dados['email'],$dados['role'],$dados['job'],$dados['active'],$id]);
            }
            header('Location: ' . APP_BASE . '/users/index.php?msg=editado');
            exit;
        }
    }
}

$pageTitle  = 'Editar Usuário';
$activeMenu = 'users';
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
        <h2>Editar Usuário <span style="color:var(--t3);font-weight:400">#<?= $id ?></span></h2>
        <p><?= htmlspecialchars($dados['name']) ?></p>
    </div>
    <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-card">
    <div class="form-title">Dados do usuário</div>
    <form method="POST" novalidate>
        <div class="form-grid">
            <div class="form-group">
                <label>Nome completo *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($dados['name']) ?>" required>
            </div>
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($dados['email']) ?>" required>
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Cargo / Função</label>
                    <input type="text" name="job" value="<?= htmlspecialchars($dados['job'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role">
                        <?php foreach (['admin','editor','visitor'] as $r): ?>
                            <option value="<?= $r ?>" <?= $dados['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Senha — deixe em branco para manter a atual -->
            <div class="form-group">
                <label>Nova senha <span style="color:var(--t3)">(deixe em branco para não alterar)</span></label>
                <input type="password" name="password" placeholder="Nova senha (mínimo 8 caracteres)" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Confirmar nova senha</label>
                <input type="password" name="password_confirm" placeholder="Repita a nova senha" autocomplete="new-password">
            </div>
            <div class="form-group checkbox-row">
                <input type="checkbox" name="active" id="active" <?= $dados['active'] ? 'checked' : '' ?>>
                <label for="active">Usuário ativo</label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Salvar alterações</button>
            <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
