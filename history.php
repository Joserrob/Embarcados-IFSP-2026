<?php
// ============================================================
// history.php — Histórico completo de eventos IoT
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();

$pdo      = getConnection();
$user     = currentUser();

// ── Filtros ───────────────────────────────────────────────────
$deviceId    = $_GET['device'] ?? 'all';
$period      = $_GET['period'] ?? '7d';
$filterAlarm = $_GET['alarm']  ?? 'all';   // all | alarm | normal
$page        = max(1, (int) ($_GET['page'] ?? 1));
$perPage     = 50;
$offset      = ($page - 1) * $perPage;

// ── Dispositivos disponíveis ──────────────────────────────────
$devices = $pdo->query("SELECT device_id FROM iot_devices ORDER BY device_id")->fetchAll();

// ── Configuração de período ───────────────────────────────────
$periodConfig = [
    '1h'    => ['sql' => 'recorded_at >= NOW() - INTERVAL 1 HOUR',    'label' => 'Última 1 hora'],
    '24h'   => ['sql' => 'recorded_at >= NOW() - INTERVAL 24 HOUR',   'label' => 'Últimas 24 horas'],
    'today' => ['sql' => 'DATE(recorded_at) = CURDATE()',              'label' => 'Hoje'],
    '7d'    => ['sql' => 'recorded_at >= NOW() - INTERVAL 7 DAY',     'label' => 'Últimos 7 dias'],
    '30d'   => ['sql' => 'recorded_at >= NOW() - INTERVAL 30 DAY',    'label' => 'Últimos 30 dias'],
    'all'   => ['sql' => '1=1',                                        'label' => 'Todo o histórico'],
];
if (!array_key_exists($period, $periodConfig)) $period = '7d';

// ── Monta cláusulas WHERE ─────────────────────────────────────
$where  = [];
$params = [];

$where[] = $periodConfig[$period]['sql'];

if ($deviceId !== 'all') {
    $where[]            = 'device_id = :device';
    $params[':device']  = $deviceId;
}

if ($filterAlarm === 'alarm')  $where[] = 'alarm = 1';
if ($filterAlarm === 'normal') $where[] = 'alarm = 0';

$whereClause = implode(' AND ', $where);

// ── Export CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExp = $pdo->prepare("
        SELECT recorded_at, device_id, temperature, alarm, sprinkler
        FROM   iot_events
        WHERE  {$whereClause}
        ORDER  BY recorded_at DESC
    ");
    $stmtExp->execute($params);
    $rows = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historico_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Data/Hora', 'Dispositivo', 'Temperatura (°C)', 'Alarme', 'Sprinkler']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['recorded_at'],
            $row['device_id'],
            $row['temperature'],
            $row['alarm']     ? 'Risco de Incêndio' : 'Normal',
            $row['sprinkler'] ? 'Ativo'              : 'Inativo',
        ]);
    }
    fclose($out);
    exit;
}

// ── Total de registros (para paginação) ───────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM iot_events WHERE {$whereClause}");
$stmtCount->execute($params);
$totalRows  = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);

// ── KPIs do período ───────────────────────────────────────────
$stmtKpi = $pdo->prepare("
    SELECT
        COUNT(*)                                AS total,
        SUM(alarm = 1)                          AS alarm_count,
        ROUND(AVG(temperature), 1)              AS avg_temp,
        MAX(temperature)                        AS max_temp,
        ROUND(SUM(alarm = 1) * 2.0 / 3600, 1)  AS alarm_hours
    FROM iot_events
    WHERE {$whereClause}
");
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch();

// ── Registros da página atual ─────────────────────────────────
$stmtRows = $pdo->prepare("
    SELECT recorded_at, device_id, temperature, alarm, sprinkler
    FROM   iot_events
    WHERE  {$whereClause}
    ORDER  BY recorded_at DESC
    LIMIT  :limit OFFSET :offset
");
foreach ($params as $k => $v) $stmtRows->bindValue($k, $v);
$stmtRows->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmtRows->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmtRows->execute();
$rows = $stmtRows->fetchAll();

// ── URL helper ────────────────────────────────────────────────
function buildUrl(array $overrides = []): string {
    $base = [
        'device' => $_GET['device'] ?? 'all',
        'period' => $_GET['period'] ?? '7d',
        'alarm'  => $_GET['alarm']  ?? 'all',
        'page'   => $_GET['page']   ?? 1,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}

$pageTitle  = 'Histórico';
$activeMenu = 'history';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.history-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
}
.history-header h1 {
    font-family: var(--font-display); font-size: 1.4rem;
    font-weight: 800; color: var(--t1);
    display: flex; align-items: center; gap: .6rem;
}

/* Filtros */
.filter-bar {
    display: flex; align-items: center; gap: .55rem; flex-wrap: wrap;
    margin-bottom: 1.5rem; padding: 1rem 1.2rem;
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); box-shadow: var(--shadow-xs);
}
.filter-group { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.filter-label {
    font-size: .72rem; font-weight: 600; color: var(--t3);
    text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.filter-sep { width: 1px; height: 20px; background: var(--border-soft); margin: 0 .3rem; flex-shrink: 0; }

.pill-btn {
    padding: .28rem .72rem; border-radius: 99px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--t2); font-size: .78rem; font-weight: 500;
    cursor: pointer; text-decoration: none; transition: all .15s; white-space: nowrap;
}
.pill-btn:hover        { border-color: var(--accent); color: var(--accent); text-decoration: none; }
.pill-btn.active       { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; }
.pill-btn.active-green { background: #1A7B46; border-color: #1A7B46; color: #fff; font-weight: 600; }

.filter-select {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r-md); color: var(--t1);
    padding: .3rem .75rem; font-size: .8rem;
    cursor: pointer; outline: none; font-family: var(--font-body);
}

.btn-export {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .42rem .9rem; border-radius: var(--r-md);
    border: 1px solid var(--border); background: var(--surface);
    color: var(--t2); font-size: .8rem; font-weight: 600;
    cursor: pointer; text-decoration: none; transition: all .15s;
    white-space: nowrap; font-family: var(--font-body); margin-left: auto;
}
.btn-export:hover { background: #1A7B46; border-color: #1A7B46; color: #fff; text-decoration: none; }

/* KPIs */
.kpi-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem; margin-bottom: 1.5rem;
}
.kpi-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); padding: 1.1rem 1.3rem; box-shadow: var(--shadow-xs);
}
.kpi-label { font-size: .7rem; color: var(--t3); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
.kpi-value {
    font-family: var(--font-display); font-size: 1.5rem; font-weight: 700;
    line-height: 1; font-variant-numeric: tabular-nums; color: var(--t1);
}
.kpi-value.red { color: #C8391C; }
.kpi-value.grn { color: #1A7B46; }
.kpi-value.ylw { color: #B86A0A; }
.kpi-sub { font-size: .7rem; color: var(--t3); margin-top: .25rem; }

/* Tabela */
.table-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); box-shadow: var(--shadow-xs);
    overflow: hidden; margin-bottom: 1.5rem;
}
.table-card-header {
    padding: 1rem 1.4rem; border-bottom: 1px solid var(--border-soft);
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    background: var(--surface-2);
}
.table-card-title { font-family: var(--font-display); font-size: .88rem; font-weight: 700; color: var(--t1); }
.table-count { font-size: .78rem; color: var(--t3); }

.hist-table { width: 100%; border-collapse: collapse; font-size: .845rem; }
.hist-table thead { background: var(--surface-2); }
.hist-table th {
    padding: .7rem 1.1rem; text-align: left;
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--t3);
    border-bottom: 1px solid var(--border-soft);
}
.hist-table td {
    padding: .65rem 1.1rem; color: var(--t1);
    border-bottom: 1px solid var(--border-soft); vertical-align: middle;
}
.hist-table tr:last-child td { border-bottom: none; }
.hist-table tbody tr { transition: background .12s; }
.hist-table tbody tr:hover { background: var(--bg); }

.temp-cell { display: inline-flex; align-items: center; gap: .4rem; font-family: var(--font-mono); font-weight: 500; }
.temp-dot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.state-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 99px;
    font-size: .72rem; font-weight: 600; white-space: nowrap;
}
.chip-alarm  { background: #FDF0EE; color: #C8391C; }
.chip-normal { background: #EDFAF4; color: #1A7B46; }
.chip-active { background: #EFF4FF; color: #1D4ED8; }
.chip-off    { background: var(--border-soft); color: var(--t3); }

.date-cell { white-space: nowrap; }
.date-main { font-weight: 500; color: var(--t1); }
.date-time { font-size: .75rem; color: var(--t3); font-family: var(--font-mono); }

/* Paginação */
.pagination {
    display: flex; align-items: center; justify-content: center;
    gap: .4rem; flex-wrap: wrap; padding: 1.2rem;
    border-top: 1px solid var(--border-soft);
}
.page-btn {
    display: flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 .6rem;
    border-radius: var(--r-md); border: 1px solid var(--border);
    background: var(--surface); color: var(--t2);
    font-size: .82rem; font-weight: 500; text-decoration: none;
    transition: all .15s; white-space: nowrap;
}
.page-btn:hover   { border-color: var(--accent); color: var(--accent); text-decoration: none; }
.page-btn.current { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 700; cursor: default; }
.page-btn.disabled { opacity: .35; pointer-events: none; }
.page-info { font-size: .8rem; color: var(--t3); padding: 0 .4rem; }

/* Vazio */
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 2rem; color: var(--t3); gap: .6rem; }
.empty-icon  { font-size: 2.8rem; }
.empty-title { font-family: var(--font-display); font-size: .95rem; font-weight: 600; color: var(--t2); }
</style>

<!-- Header -->
<div class="history-header">
    <h1>📋 Histórico de Eventos</h1>
    <a class="btn-export" href="<?= buildUrl(['page' => 1, 'export' => 'csv']) ?>">
        ⬇ Exportar CSV
    </a>
</div>

<!-- Filtros -->
<div class="filter-bar">

    <span class="filter-label">Dispositivo</span>
    <select class="filter-select" onchange="applyFilter('device', this.value)">
        <option value="all" <?= $deviceId === 'all' ? 'selected' : '' ?>>Todos</option>
        <?php foreach ($devices as $d): ?>
            <option value="<?= htmlspecialchars($d['device_id']) ?>"
                    <?= $d['device_id'] === $deviceId ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['device_id']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <div class="filter-sep"></div>

    <span class="filter-label">Período</span>
    <div class="filter-group">
        <?php foreach ($periodConfig as $key => $cfg): ?>
            <a href="<?= buildUrl(['period' => $key, 'page' => 1]) ?>"
               class="pill-btn <?= $period === $key ? 'active' : '' ?>">
                <?= $cfg['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="filter-sep"></div>

    <span class="filter-label">Estado</span>
    <div class="filter-group">
        <a href="<?= buildUrl(['alarm' => 'all',    'page' => 1]) ?>"
           class="pill-btn <?= $filterAlarm === 'all'    ? 'active'       : '' ?>">Todos</a>
        <a href="<?= buildUrl(['alarm' => 'alarm',  'page' => 1]) ?>"
           class="pill-btn <?= $filterAlarm === 'alarm'  ? 'active'       : '' ?>">🔥 Alarme</a>
        <a href="<?= buildUrl(['alarm' => 'normal', 'page' => 1]) ?>"
           class="pill-btn <?= $filterAlarm === 'normal' ? 'active-green' : '' ?>">✅ Normal</a>
    </div>

</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total de leituras</div>
        <div class="kpi-value"><?= number_format($kpi['total'] ?? 0) ?></div>
        <div class="kpi-sub"><?= $periodConfig[$period]['label'] ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Eventos de alarme</div>
        <div class="kpi-value red"><?= number_format($kpi['alarm_count'] ?? 0) ?></div>
        <div class="kpi-sub">
            <?php
                $alarmPct = ($kpi['total'] ?? 0) > 0
                    ? round(($kpi['alarm_count'] / $kpi['total']) * 100, 1) : 0;
                echo $alarmPct . '% do total';
            ?>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Horas em alarme</div>
        <div class="kpi-value red"><?= number_format($kpi['alarm_hours'] ?? 0, 1) ?> h</div>
        <div class="kpi-sub">~2s por leitura</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Temperatura média</div>
        <?php
            $avgT = $kpi['avg_temp'] ?? null;
            $cls  = $avgT >= 60 ? 'red' : ($avgT >= 40 ? 'ylw' : 'grn');
        ?>
        <div class="kpi-value <?= $avgT !== null ? $cls : '' ?>">
            <?= $avgT !== null ? $avgT . ' °C' : '—' ?>
        </div>
        <div class="kpi-sub">máx: <?= $kpi['max_temp'] ?? '—' ?> °C</div>
    </div>
</div>

<!-- Tabela -->
<div class="table-card">
    <div class="table-card-header">
        <span class="table-card-title">Registros</span>
        <span class="table-count">
            <?php
                $from = $totalRows > 0 ? $offset + 1 : 0;
                $to   = min($offset + $perPage, $totalRows);
                echo "{$from}–{$to} de " . number_format($totalRows) . " registros";
            ?>
        </span>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <span class="empty-icon">📭</span>
            <span class="empty-title">Nenhum registro encontrado</span>
            <span>Tente ajustar os filtros ou aguarde o ESP32 enviar dados.</span>
        </div>
    <?php else: ?>

    <table class="hist-table">
        <thead>
            <tr>
                <th>Data / Hora</th>
                <?php if ($deviceId === 'all'): ?><th>Dispositivo</th><?php endif; ?>
                <th>Temperatura</th>
                <th>Estado</th>
                <th>Sprinkler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $dt         = new DateTime($row['recorded_at']);
                $temp       = (int) $row['temperature'];
                $isAlarm    = (bool) $row['alarm'];
                $isSpr      = (bool) $row['sprinkler'];
                $tempColor  = $temp >= 60 ? '#C8391C' : ($temp >= 40 ? '#B86A0A' : '#1A7B46');
            ?>
                <tr>
                    <td class="date-cell">
                        <div class="date-main"><?= $dt->format('d/m/Y') ?></div>
                        <div class="date-time"><?= $dt->format('H:i:s') ?></div>
                    </td>

                    <?php if ($deviceId === 'all'): ?>
                        <td style="font-family:var(--font-mono);font-size:.8rem;color:var(--t2)">
                            <?= htmlspecialchars($row['device_id']) ?>
                        </td>
                    <?php endif; ?>

                    <td>
                        <span class="temp-cell">
                            <span class="temp-dot" style="background:<?= $tempColor ?>"></span>
                            <?= $temp ?> °C
                        </span>
                    </td>

                    <td>
                        <span class="state-chip <?= $isAlarm ? 'chip-alarm' : 'chip-normal' ?>">
                            <?= $isAlarm ? '🔥 Alarme' : '✅ Normal' ?>
                        </span>
                    </td>

                    <td>
                        <span class="state-chip <?= $isSpr ? 'chip-active' : 'chip-off' ?>">
                            <?= $isSpr ? '💧 Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="page-btn" href="<?= buildUrl(['page' => $page - 1]) ?>">‹ Anterior</a>
        <?php else: ?>
            <span class="page-btn disabled">‹ Anterior</span>
        <?php endif; ?>

        <?php
            $window = 2;
            $start  = max(1, $page - $window);
            $end    = min($totalPages, $page + $window);
            if ($start > 1) {
                echo '<a class="page-btn" href="' . buildUrl(['page' => 1]) . '">1</a>';
                if ($start > 2) echo '<span class="page-info">…</span>';
            }
            for ($p = $start; $p <= $end; $p++):
        ?>
            <a class="page-btn <?= $p === $page ? 'current' : '' ?>"
               href="<?= buildUrl(['page' => $p]) ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
        <?php
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="page-info">…</span>';
                echo '<a class="page-btn" href="' . buildUrl(['page' => $totalPages]) . '">' . $totalPages . '</a>';
            }
        ?>

        <?php if ($page < $totalPages): ?>
            <a class="page-btn" href="<?= buildUrl(['page' => $page + 1]) ?>">Próxima ›</a>
        <?php else: ?>
            <span class="page-btn disabled">Próxima ›</span>
        <?php endif; ?>

        <span class="page-info">Página <?= $page ?> de <?= $totalPages ?></span>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function applyFilter(param, value) {
    var url = new URL(window.location.href);
    url.searchParams.set(param, value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>