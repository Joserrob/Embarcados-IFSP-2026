<?php
// ============================================================
// api/device.php
// GET  ?device_id=esp32-01   → estado atual (dashboard)
// POST {device_id, ...}      → ESP32 reporta, recebe comandos
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/db.php';

$pdo    = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

function jsonOut(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $deviceId = $_GET['device_id'] ?? 'esp32-01';

    $stmt = $pdo->prepare('
        SELECT *,
               TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS secs_ago
        FROM   iot_devices
        WHERE  device_id = ?
    ');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        jsonOut(['error' => 'Device not found'], 404);
    }

    $secsAgo = ($device['last_seen'] !== null) ? (int) $device['secs_ago'] : 9999;

    jsonOut([
        'device_id'   => $device['device_id'],
        'button'      => (int) $device['button'],
        'led'         => (int) $device['led'],
        'temperature' => (int) $device['temperature'],
        'alarm'       => (int) $device['alarm'],
        'sprinkler'   => (int) $device['sprinkler'],
        'online'      => $secsAgo <= 15,
        'secs_ago'    => $secsAgo,
        'last_seen'   => $device['last_seen'],
    ]);
}

// ── POST ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) $body = $_POST;

    $deviceId = $body['device_id'] ?? null;
    if (!$deviceId) jsonOut(['error' => 'device_id required'], 400);

    $button      = (int) ($body['button']      ?? 0);
    $led         = (int) ($body['led']         ?? 0);
    $temperature = (int) ($body['temperature'] ?? 0);
    $alarm       = (int) ($body['alarm']       ?? 0);
    $sprinkler   = (int) ($body['sprinkler']   ?? 0);

    $temperature = max(0, min(100, $temperature));

    $pdo->prepare('
        INSERT INTO iot_devices
            (device_id, button, led, temperature, alarm, sprinkler, last_seen)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            button      = VALUES(button),
            led         = VALUES(led),
            temperature = VALUES(temperature),
            alarm       = VALUES(alarm),
            sprinkler   = VALUES(sprinkler),
            last_seen   = NOW()
    ')->execute([$deviceId, $button, $led, $temperature, $alarm, $sprinkler]);

    $pdo->prepare('
        INSERT INTO iot_events (device_id, temperature, alarm, sprinkler)
        VALUES (?, ?, ?, ?)
    ')->execute([$deviceId, $temperature, $alarm, $sprinkler]);

    $stmt = $pdo->prepare('
        SELECT id, command, value
        FROM   iot_commands
        WHERE  device_id = ? AND executed = 0
        ORDER  BY id ASC
    ');
    $stmt->execute([$deviceId]);
    $commands = $stmt->fetchAll();

    if (!empty($commands)) {
        $ids = implode(',', array_column($commands, 'id'));
        $pdo->exec("UPDATE iot_commands SET executed = 1 WHERE id IN ($ids)");
    }

    jsonOut([
        'status'   => 'ok',
        'commands' => array_map(fn($c) => [
            'command' => $c['command'],
            'value'   => (int) $c['value'],
        ], $commands),
    ]);
}

jsonOut(['error' => 'Method not allowed'], 405);