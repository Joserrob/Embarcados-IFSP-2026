<?php
// ============================================================
// config/db.php — Conexão com o banco de dados
// ============================================================

define('APP_BASE', '/ze');
define('APP_NAME', 'Projeto ZE');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');   // padrão XAMPP
define('DB_PASS', '');       // padrão XAMPP (sem senha)
define('DB_NAME', 'projeto_ze');

function getConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_NAME
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Em produção, logue o erro em vez de expô-lo
            error_log('DB Error: ' . $e->getMessage());
            http_response_code(500);
            die('<h1>Erro interno de servidor.</h1>');
        }
    }
    return $pdo;
}
