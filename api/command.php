<?php
// ============================================================
// api/command.php — Dashboard enfileira comandos para o ESP32
//
// POST {device_id, command, value}
//   "led"    → liga/desliga LED          value: 0|1
//   "reset"  → reseta sistema            value: 1
//   "alarm"  → força ativação do alarme  value: 1
// ============================================================

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$deviceId = $body['device_id'] ?? 'esp32-01';
$command  = $body['command']   ?? null;
$value    = isset($body['value']) ? (int) $body['value'] : 0;

$allowedCommands = ['led', 'reset', 'alarm'];

if (!$command || !in_array($command, $allowedCommands, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid command']);
    exit;
}

$pdo = getConnection();

// Remove comandos antigos não executados do mesmo tipo
$pdo->prepare('
    DELETE FROM iot_commands
    WHERE device_id = ? AND command = ? AND executed = 0
')->execute([$deviceId, $command]);

// Enfileira o novo comando
$pdo->prepare('
    INSERT INTO iot_commands (device_id, command, value)
    VALUES (?, ?, ?)
')->execute([$deviceId, $command, $value]);

// Atualiza iot_devices para feedback imediato no dashboard
// (o ESP32 confirmará o estado real no próximo POST)
switch ($command) {
    case 'reset':
        $pdo->prepare('
            UPDATE iot_devices
            SET alarm = 0, sprinkler = 0, led = 0
            WHERE device_id = ?
        ')->execute([$deviceId]);
        break;

    case 'alarm':
        $pdo->prepare('
            UPDATE iot_devices
            SET alarm = 1, sprinkler = 1, led = 1
            WHERE device_id = ?
        ')->execute([$deviceId]);
        break;

    case 'led':
        $pdo->prepare('
            UPDATE iot_devices SET led = ? WHERE device_id = ?
        ')->execute([$value, $deviceId]);
        break;
}

echo json_encode([
    'status'  => 'queued',
    'command' => $command,
    'value'   => $value,
]);