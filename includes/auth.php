<?php
// ============================================================
// includes/auth.php — Funções de autenticação e sessão
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Hierarquia de roles (quanto maior, mais permissões)
const ROLE_LEVELS = [
    'visitor' => 1,
    'editor'  => 2,
    'admin'   => 3,
];

/**
 * Exige que o usuário esteja logado.
 * Redireciona para login caso contrário.
 */
function requireAuth(): void
{
    if (empty($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: ' . APP_BASE . '/login.php?redirect=' . $redirect);
        exit;
    }
}

/**
 * Exige que o usuário tenha ao menos o role especificado.
 * Exibe 403 ou redireciona caso contrário.
 */
function requireRole(string $minRole): void
{
    requireAuth();
    $userLevel = ROLE_LEVELS[$_SESSION['user_role']] ?? 0;
    $minLevel  = ROLE_LEVELS[$minRole] ?? 99;
    if ($userLevel < $minLevel) {
        http_response_code(403);
        die(renderForbidden());
    }
}

/**
 * Verifica se o usuário logado tem ao menos o role especificado.
 */
function hasRole(string $minRole): bool
{
    $userLevel = ROLE_LEVELS[$_SESSION['user_role'] ?? 'visitor'] ?? 0;
    $minLevel  = ROLE_LEVELS[$minRole] ?? 99;
    return $userLevel >= $minLevel;
}

/**
 * Retorna os dados da sessão do usuário logado.
 */
function currentUser(): array
{
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'Convidado',
        'role' => $_SESSION['user_role'] ?? 'visitor',
    ];
}

/**
 * Inicia a sessão do usuário após login bem-sucedido.
 */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
}

/**
 * Encerra a sessão do usuário.
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Gera um slug a partir de um texto.
 */
function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[áàãâä]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[íìîï]/u', 'i', $text);
    $text = preg_replace('/[óòõôö]/u', 'o', $text);
    $text = preg_replace('/[úùûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Retorna HTML de página 403.
 */
function renderForbidden(): string
{
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">
    <title>403 — Acesso Negado</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0b0d17;color:#e8ecff;}
    .box{text-align:center}.h{font-size:5rem;margin:0;color:#4fffb0}.p{color:#8b95c9}
    a{color:#4fffb0;text-decoration:none}</style></head><body>
    <div class="box"><div class="h">403</div>
    <p class="p">Você não tem permissão para acessar esta página.</p>
    <a href="' . APP_BASE . '/index.php">← Voltar ao início</a></div></body></html>';
}
