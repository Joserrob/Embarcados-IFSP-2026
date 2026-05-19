<?php
/**
 * seed.php — Cria o usuário admin inicial
 *
 * ⚠️  Execute UMA VEZ acessando: http://localhost/chaves/seed.php
 * ⚠️  DELETE ESTE ARQUIVO APÓS EXECUTAR!
 */

require_once __DIR__ . '/config/db.php';

$pdo = getConnection();

// Verifica se já existe algum admin
$check = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($check > 0) {
    die('⚠️ Já existe um admin cadastrado. Delete este arquivo.');
}

$hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role, job, active)
    VALUES (?, ?, ?, 'admin', 'System Administrator', 1)
");
$stmt->execute(['Administrador', 'admin@exemplo.com', $hash]);

echo '<h2 style="font-family:sans-serif;color:green">✅ Admin criado com sucesso!</h2>';
echo '<p style="font-family:sans-serif"><strong>Email:</strong> admin@exemplo.com</p>';
echo '<p style="font-family:sans-serif"><strong>Senha:</strong> Admin@123</p>';
echo '<p style="font-family:sans-serif;color:red"><strong>⚠️ DELETE ESTE ARQUIVO AGORA!</strong></p>';
echo '<p style="font-family:sans-serif"><a href="/crud-app/login.php">→ Ir para o Login</a></p>';
