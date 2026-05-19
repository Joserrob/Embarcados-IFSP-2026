<?php
// ============================================================
// login.php — Página de autenticação
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
// auth.php já inicia a sessão — NÃO chamar session_start() aqui

// Já logado → redireciona ao dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_BASE . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Preencha e-mail e senha.';
    } else {
        $pdo  = getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            header('Location: ' . APP_BASE . '/dashboard.php');
            exit;
        }

        $error = 'E-mail ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Entrar — <?= APP_NAME ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔥</text></svg>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Sora:wght@700;800&display=swap">

    <style>
        :root {
            --bg:          #F0ECE5;
            --surface:     #FFFFFF;
            --border:      #D9D3CA;
            --border-soft: #EAE5DE;
            --t1:          #1A1714;
            --t2:          #5C564E;
            --t3:          #9C958C;
            --accent:      #C8391C;
            --accent-soft: #FDF0EE;
            --accent-hov:  #A82E15;
            --topbar-line: #C8391C;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { -webkit-font-smoothing: antialiased; color-scheme: light; }

        body {
            font-family: 'Figtree', system-ui, sans-serif;
            background:  var(--bg);
            color:       var(--t1);
            min-height:  100vh;
            display:     flex;
            flex-direction: column;
        }

        .login-topbar {
            height:     4px;
            background: linear-gradient(90deg, #1A1714 0%, var(--topbar-line) 50%, #1A1714 100%);
            flex-shrink: 0;
        }

        .login-wrap {
            flex:            1;
            display:         flex;
            align-items:     center;
            justify-content: center;
            padding:         2rem 1rem;
        }

        .login-box {
            width:     100%;
            max-width: 420px;
            display:   flex;
            flex-direction: column;
            gap:       1.75rem;
            animation: slide-up .35s cubic-bezier(.22,.68,0,1.2) forwards;
        }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: none; }
        }

        .login-brand {
            display:        flex;
            flex-direction: column;
            align-items:    center;
            gap:            .75rem;
            text-align:     center;
        }

        .brand-icon {
            width:           56px;
            height:          56px;
            background:      #1A1714;
            border-radius:   14px;
            display:         flex;
            align-items:     center;
            justify-content: center;
            font-size:       26px;
            box-shadow:      0 4px 16px rgba(26,23,20,.18);
            position:        relative;
        }
        .brand-icon::after {
            content:      '';
            position:     absolute;
            bottom: -1px; left: 12px; right: 12px;
            height:       3px;
            background:   var(--topbar-line);
            border-radius: 0 0 4px 4px;
        }

        .brand-name {
            font-family:     'Sora', sans-serif;
            font-size:       1.6rem;
            font-weight:     800;
            color:           var(--t1);
            letter-spacing:  -.02em;
        }
        .brand-name span { color: var(--topbar-line); }
        .brand-sub { font-size: .82rem; color: var(--t3); margin-top: -.25rem; }

        .login-card {
            background:    var(--surface);
            border:        1px solid var(--border-soft);
            border-radius: 18px;
            padding:       2rem 2rem 1.75rem;
            box-shadow:    0 2px 12px rgba(26,23,20,.08), 0 1px 3px rgba(26,23,20,.06);
        }

        .login-card h2 {
            font-family:   'Sora', sans-serif;
            font-size:     1.05rem;
            font-weight:   700;
            color:         var(--t1);
            margin-bottom: 1.5rem;
        }

        .login-error {
            display:       flex;
            align-items:   flex-start;
            gap:           .55rem;
            padding:       .75rem 1rem;
            background:    var(--accent-soft);
            border:        1px solid rgba(200,57,28,.25);
            border-radius: 10px;
            font-size:     .855rem;
            color:         var(--accent);
            margin-bottom: 1.25rem;
            line-height:   1.4;
        }

        .field { display: flex; flex-direction: column; gap: .38rem; margin-bottom: 1.1rem; }
        .field:last-of-type { margin-bottom: 0; }
        .field label { font-size: .8rem; font-weight: 600; color: var(--t2); letter-spacing: .01em; }
        .field input {
            width: 100%; padding: .65rem .95rem;
            background: var(--surface); border: 1.5px solid var(--border);
            border-radius: 10px; font-family: 'Figtree', sans-serif;
            font-size: .9rem; color: var(--t1);
            outline: none; transition: border-color .15s, box-shadow .15s;
            appearance: none;
        }
        .field input::placeholder { color: var(--t3); }
        .field input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(200,57,28,.12);
        }

        .field-divider { height: 1px; background: var(--border-soft); margin: .6rem 0 1.1rem; }

        .btn-login {
            width: 100%; padding: .75rem;
            background: var(--accent); border: none;
            border-radius: 10px; font-family: 'Figtree', sans-serif;
            font-size: .92rem; font-weight: 700; color: #fff;
            cursor: pointer; transition: background .15s, box-shadow .15s, transform .1s;
            margin-top: 1.25rem;
        }
        .btn-login:hover { background: var(--accent-hov); box-shadow: 0 4px 14px rgba(200,57,28,.32); }
        .btn-login:active { transform: scale(.98); }

        .login-foot { text-align: center; font-size: .8rem; color: var(--t3); }
        .login-foot a { color: var(--accent); font-weight: 600; text-decoration: none; }
        .login-foot a:hover { text-decoration: underline; }

        .page-footer {
            padding: 1.5rem; text-align: center;
            font-size: .75rem; color: var(--t3);
            border-top: 1px solid var(--border-soft);
        }

        @media (max-width: 480px) {
            .login-card { padding: 1.5rem 1.25rem; border-radius: 14px; }
            .brand-name { font-size: 1.35rem; }
        }
    </style>
</head>
<body>

    <div class="login-topbar"></div>

    <div class="login-wrap">
        <div class="login-box">

            <div class="login-brand">
                <div class="brand-icon">🔥</div>
                <div>
                    <div class="brand-name">Fire<span>Watch</span></div>
                    <div class="brand-sub">Sistema de Monitoramento de Incêndio</div>
                </div>
            </div>

            <div class="login-card">
                <h2>Entrar na sua conta</h2>

                <?php if ($error): ?>
                    <div class="login-error">
                        ⚠️ <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="seu@email.com"
                               autocomplete="email" required autofocus>
                    </div>

                    <div class="field-divider"></div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <input type="password" id="password" name="password"
                               placeholder="••••••••"
                               autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn-login">Entrar →</button>
                </form>
            </div>

            <div class="login-foot">
                Ainda não tem conta?
                <a href="<?= APP_BASE ?>/register.php">Criar conta</a>
            </div>

        </div>
    </div>

    <footer class="page-footer">
        <?= APP_NAME ?> &mdash; Sistema de Monitoramento de Incêndio &mdash; <?= date('Y') ?>
    </footer>

</body>
</html>