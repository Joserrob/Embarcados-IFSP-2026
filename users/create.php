<?php
// ============================================================
// users/create.php — Criar usuário (apenas admin)
// ============================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$erros = [];
$dados = ['name' => '', 'email' => '', 'job' => '', 'role' => 'visitor', 'active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'name'   => trim($_POST['name']   ?? ''),
        'email'  => trim($_POST['email']  ?? ''),
        'job'    => trim($_POST['job']    ?? ''),
        'role'   => $_POST['role']  ?? 'visitor',
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $senha   = $_POST['password']         ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (empty($dados['name']))   $erros[] = 'Nome é obrigatório.';
    if (empty($dados['email']))  $erros[] = 'E-mail é obrigatório.';
    elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL))
                                 $erros[] = 'E-mail inválido.';
    if (strlen($senha) < 8)      $erros[] = 'Senha deve ter ao menos 8 caracteres.';
    if ($senha !== $confirm)     $erros[] = 'As senhas não coincidem.';
    if (!in_array($dados['role'], ['admin','editor','visitor']))
                                 $erros[] = 'Role inválido.';

    if (empty($erros)) {
        $pdo   = getConnection();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$dados['email']]);
        if ($check->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password, role, job, active)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$dados['name'], $dados['email'], $hash, $dados['role'], $dados['job'], $dados['active']]);
            header('Location: ' . APP_BASE . '/users/index.php?msg=criado');
            exit;
        }
    }
}

$pageTitle  = 'Novo Usuário';
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
        <h2>Novo Usuário</h2>
        <p>Preencha os dados para criar um novo usuário</p>
    </div>
    <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">← Voltar</a>
</div>

<div class="form-card">
    <div class="form-title">Dados do usuário</div>
    <form method="POST" novalidate>
        <div class="form-grid">
            <div class="form-group">
                <label>Nome completo *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($dados['name']) ?>" required placeholder="Nome do usuário">
            </div>
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($dados['email']) ?>" required placeholder="email@exemplo.com">
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Cargo / Função</label>
                    <input type="text" name="job" value="<?= htmlspecialchars($dados['job']) ?>" placeholder="Ex: Designer">
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
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label>Senha *</label>
                    <input type="password" name="password" required placeholder="Mínimo 8 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar senha *</label>
                    <input type="password" name="password_confirm" required placeholder="Repita a senha">
                </div>
            </div>
            <div class="form-group checkbox-row">
                <input type="checkbox" name="active" id="active" <?= $dados['active'] ? 'checked' : '' ?>>
                <label for="active">Usuário ativo</label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">💾 Criar usuário</button>
            <a href="<?= APP_BASE ?>/users/index.php" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
