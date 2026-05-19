<?php
// ============================================================
// register.php — Página de cadastro
// ============================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_BASE . '/index.php');
    exit;
}

$erros = [];
$dados = ['name' => '', 'email' => '', 'job' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'name'  => trim($_POST['name']  ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'job'   => trim($_POST['job']   ?? ''),
    ];
    $senha   = $_POST['password']         ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (empty($dados['name']))         $erros[] = 'Nome é obrigatório.';
    if (empty($dados['email']))        $erros[] = 'E-mail é obrigatório.';
    elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL))
                                       $erros[] = 'E-mail inválido.';
    if (strlen($senha) < 8)            $erros[] = 'A senha deve ter ao menos 8 caracteres.';
    if ($senha !== $confirm)           $erros[] = 'As senhas não coincidem.';

    if (empty($erros)) {
        $pdo   = getConnection();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$dados['email']]);
        if ($check->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password, role, job)
                VALUES (?, ?, ?, \'visitor\', ?)
            ');
            $stmt->execute([$dados['name'], $dados['email'], $hash, $dados['job']]);
            header('Location: ' . APP_BASE . '/login.php?registered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_BASE ?>/style.css">
    <script>(function(){var t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>

    <style>
        .auth-card { max-width: 460px; }
        .form-group { margin-bottom: .1rem; }
        .form-grid  { gap: 1rem; }
        .btn-block  { width: 100%; justify-content: center; padding: .75rem; font-size: .95rem; margin-top: .5rem; }
    </style>
</head>
<body class="auth-body">
    <button class="theme-toggle auth-theme-btn" onclick="toggleTheme()" title="Alternar tema"><span class="icon-moon">🌙</span><span class="icon-sun">☀️</span></button>
    <button class="theme-toggle auth-theme-btn" onclick="toggleTheme()" title="Alternar tema"><span class="icon-moon">&#127769;</span><span class="icon-sun">&#9728;&#65039;</span></button>
    <div class="auth-card">
        <div class="auth-logo">
            <span class="logo-mark">✦</span>
            <span class="logo-text"><?= APP_NAME ?></span>
        </div>

        <h2 class="auth-title">Criar conta</h2>
        <p class="auth-sub">Preencha os dados para se registrar</p>

        <?php if ($erros): ?>
            <div class="alert alert-danger">
                <span>⚠</span>
                <ul><?php foreach ($erros as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="form-grid" novalidate>
            <div class="form-group">
                <label for="name">Nome completo</label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($dados['name']) ?>"
                       placeholder="Seu nome" autocomplete="name" required>
            </div>
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($dados['email']) ?>"
                       placeholder="seu@email.com" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="job">Cargo / Função <span style="color:var(--t3)">(opcional)</span></label>
                <input type="text" id="job" name="job"
                       value="<?= htmlspecialchars($dados['job']) ?>"
                       placeholder="Ex: Desenvolvedor, Designer...">
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password"
                       placeholder="Mínimo 8 caracteres" autocomplete="new-password" required>
                <span class="form-hint">Mínimo 8 caracteres</span>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmar senha</label>
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="Repita a senha" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Criar conta</button>
        </form>

        <p class="auth-footer">
            Já tem conta? <a href="<?= APP_BASE ?>/login.php">Entrar</a>
        </p>
    </div>
<script>
function toggleTheme(){var h=document.documentElement;var c=h.getAttribute("data-theme")||"light";var n=c==="dark"?"light":"dark";h.setAttribute("data-theme",n);localStorage.setItem("theme",n);}
</script>
</body>
</html>