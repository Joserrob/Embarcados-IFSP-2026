<?php
// ============================================================
// temperature_chart.php — Gráfico de variação de temperatura
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();

$pdo      = getConnection();
$user     = currentUser();          // ← corrigido: necessário para header.php
$deviceId = $_GET['device'] ?? 'esp32-01';
$period   = $_GET['period'] ?? '24h';

// Dispositivos disponíveis
$devices = $pdo->query("SELECT device_id FROM iot_devices ORDER BY device_id")->fetchAll();

// Configuração de períodos (janela + tamanho do bucket de agrupamento)
$periodConfig = [
    '1h'  => ['interval' => 'INTERVAL 1 HOUR',  'bucket_sec' => 60,   'label' => 'Última 1 hora'],
    '6h'  => ['interval' => 'INTERVAL 6 HOUR',  'bucket_sec' => 300,  'label' => 'Últimas 6 horas'],
    '24h' => ['interval' => 'INTERVAL 24 HOUR', 'bucket_sec' => 900,  'label' => 'Últimas 24 horas'],
];

if (!array_key_exists($period, $periodConfig)) {
    $period = '24h';
}

$cfg         = $periodConfig[$period];
$bucketSec   = $cfg['bucket_sec'];
$sqlInterval = $cfg['interval'];

// Temperatura média/máx/mín agrupada por bucket de tempo
$stmt = $pdo->prepare("
    SELECT
        FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(recorded_at) / :bucket) * :bucket2) AS bucket_time,
        ROUND(AVG(temperature), 1) AS avg_temp,
        MAX(temperature)            AS max_temp,
        MIN(temperature)            AS min_temp
    FROM   iot_events
    WHERE  device_id = :device
      AND  recorded_at >= NOW() - {$sqlInterval}
    GROUP  BY bucket_time
    ORDER  BY bucket_time ASC
");
$stmt->execute([':bucket' => $bucketSec, ':bucket2' => $bucketSec, ':device' => $deviceId]);
$rows = $stmt->fetchAll();

// Formata para Chart.js
$labels = $avgTemps = $maxTemps = $minTemps = [];
foreach ($rows as $row) {
    $dt       = new DateTime($row['bucket_time']);
    $labels[] = $period === '7d' ? $dt->format('d/m H:i') : $dt->format('H:i');
    $avgTemps[] = (float) $row['avg_temp'];
    $maxTemps[] = (float) $row['max_temp'];
    $minTemps[] = (float) $row['min_temp'];
}

// Estado atual do dispositivo
$stmtDev = $pdo->prepare('SELECT temperature, alarm, last_seen FROM iot_devices WHERE device_id = ?');
$stmtDev->execute([$deviceId]);
$currentDevice = $stmtDev->fetch();

$pageTitle  = 'Gráfico de Temperatura';
$activeMenu = 'temperature_chart';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.chart-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
}
.chart-header h1 {
    font-family: var(--font-display); font-size: 1.4rem; font-weight: 700;
    color: var(--t1); display: flex; align-items: center; gap: .6rem;
}
.chart-filters { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.filter-select {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-md); color: var(--t1);
    padding: .4rem .8rem; font-size: .83rem; cursor: pointer; outline: none;
}
.period-btn {
    padding: .38rem .85rem; border-radius: var(--r-md);
    border: 1px solid var(--border-soft); background: var(--surface);
    color: var(--t2); font-size: .82rem; font-weight: 500;
    cursor: pointer; transition: all .2s; text-decoration: none;
}
.period-btn:hover, .period-btn.active {
    background: var(--accent); border-color: var(--accent); color: #fff;
}
.summary-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem; margin-bottom: 1.5rem;
}
.summary-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); padding: 1.2rem 1.4rem;
    display: flex; flex-direction: column; gap: .3rem;
    box-shadow: var(--shadow-xs);
}
.s-label {
    font-size: .72rem; color: var(--t3);
    text-transform: uppercase; letter-spacing: .04em;
}
.s-value {
    font-family: var(--font-display); font-size: 1.6rem; font-weight: 700;
    font-variant-numeric: tabular-nums; line-height: 1;
}
.s-value.ok      { color: var(--green);  }
.s-value.warning { color: var(--yellow); }
.s-value.danger  { color: var(--accent); }

.chart-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); padding: 1.5rem; margin-bottom: 1.5rem;
    box-shadow: var(--shadow-xs);
}
.chart-card h3 {
    font-family: var(--font-display); font-size: .9rem; font-weight: 700;
    color: var(--t1); margin-bottom: 1.2rem;
}
.chart-wrap { position: relative; height: 340px; }

.state-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .28rem .75rem; border-radius: 99px;
    font-size: .75rem; font-weight: 600;
}
.state-badge.normal { background: var(--green-soft);  color: var(--green);  }
.state-badge.fire   { background: var(--accent-soft);  color: var(--accent); }

.no-data {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; height: 280px;
    color: var(--t3); gap: .6rem; font-size: .9rem;
}
.no-data .no-data-icon { font-size: 2.5rem; }
</style>

<div class="chart-header">
    <h1>
        🌡️ Temperatura
        <?php if ($currentDevice): ?>
            <?php $cls = $currentDevice['alarm'] ? 'fire' : 'normal'; ?>
            <span class="state-badge <?= $cls ?>">
                <?= $currentDevice['alarm'] ? 'Risco de Incêndio' : 'Normal' ?>
            </span>
        <?php endif; ?>
    </h1>
    <div class="chart-filters">
        <select class="filter-select" onchange="changeDevice(this.value)">
            <?php foreach ($devices as $d): ?>
                <option value="<?= htmlspecialchars($d['device_id']) ?>"
                    <?= $d['device_id'] === $deviceId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['device_id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php foreach ($periodConfig as $key => $c): ?>
            <a href="?device=<?= urlencode($deviceId) ?>&period=<?= $key ?>"
               class="period-btn <?= $period === $key ? 'active' : '' ?>">
                <?= $key ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="summary-grid">
    <?php
        $avg = !empty($avgTemps) ? round(array_sum($avgTemps) / count($avgTemps), 1) : null;
        $max = !empty($maxTemps) ? max($maxTemps) : null;
        $min = !empty($minTemps) ? min($minTemps) : null;
        $cur = $currentDevice ? (int) $currentDevice['temperature'] : null;

        function tempClass(float $v): string {
            if ($v >= 60) return 'danger';
            if ($v >= 40) return 'warning';
            return 'ok';
        }
    ?>
    <div class="summary-card">
        <span class="s-label">Atual</span>
        <span class="s-value <?= $cur !== null ? tempClass((float)$cur) : '' ?>">
            <?= $cur !== null ? $cur . ' °C' : '—' ?>
        </span>
    </div>
    <div class="summary-card">
        <span class="s-label">Média (<?= $cfg['label'] ?>)</span>
        <span class="s-value <?= $avg !== null ? tempClass($avg) : '' ?>">
            <?= $avg !== null ? $avg . ' °C' : '—' ?>
        </span>
    </div>
    <div class="summary-card">
        <span class="s-label">Máxima</span>
        <span class="s-value <?= $max !== null ? tempClass((float)$max) : '' ?>">
            <?= $max !== null ? $max . ' °C' : '—' ?>
        </span>
    </div>
    <div class="summary-card">
        <span class="s-label">Mínima</span>
        <span class="s-value ok">
            <?= $min !== null ? $min . ' °C' : '—' ?>
        </span>
    </div>
</div>

<div class="chart-card">
    <h3>📈 Variação de temperatura — <?= htmlspecialchars($cfg['label']) ?></h3>
    <?php if (empty($rows)): ?>
        <div class="no-data">
            <span class="no-data-icon">📭</span>
            <span>Nenhum dado no período selecionado.</span>
            <span style="font-size:.8rem">Aguarde o ESP32 enviar leituras.</span>
        </div>
    <?php else: ?>
        <div class="chart-wrap">
            <canvas id="tempChart"></canvas>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($rows)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const labels   = <?= json_encode($labels) ?>;
const avgTemps = <?= json_encode($avgTemps) ?>;
const maxTemps = <?= json_encode($maxTemps) ?>;
const minTemps = <?= json_encode($minTemps) ?>;

function pointColor(temp) {
    if (temp >= 60) return '#C8391C';
    if (temp >= 40) return '#B86A0A';
    return '#1A7B46';
}

const pointColors = avgTemps.map(pointColor);

// Gradiente de fundo adaptado ao tema claro
function makeGradient(ctx, chartArea) {
    const grad = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
    grad.addColorStop(0,   'rgba(200, 57, 28, 0.20)');
    grad.addColorStop(0.5, 'rgba(184,106, 10, 0.08)');
    grad.addColorStop(1,   'rgba( 26,123, 70, 0.03)');
    return grad;
}

const ctx = document.getElementById('tempChart').getContext('2d');
let chart = null;

function buildChart() {
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Temperatura Média (°C)',
                    data: avgTemps,
                    backgroundColor: (context) => {
                        const { chart, chartArea } = context;
                        if (!chartArea) return 'transparent';
                        return makeGradient(chart.ctx, chartArea);
                    },
                    borderWidth: 2.5,
                    pointBackgroundColor: pointColors,
                    pointRadius: avgTemps.length > 80 ? 0 : 4,
                    pointHoverRadius: 7,
                    tension: 0.4,
                    fill: true,
                    segment: {
                        borderColor: ctx => pointColor(avgTemps[ctx.p1DataIndex]),
                    },
                },
                {
                    label: 'Máxima',
                    data: maxTemps,
                    borderColor: 'rgba(200,57,28,0.35)',
                    borderWidth: 1,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    tension: 0.4,
                    fill: false,
                },
                {
                    label: 'Mínima',
                    data: minTemps,
                    borderColor: 'rgba(26,123,70,0.35)',
                    borderWidth: 1,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    tension: 0.4,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: {
                        color: '#5C564E',       /* --t2 hardcoded: CSS vars não funcionam no Canvas */
                        font: { size: 12 },
                        boxWidth: 12,
                        usePointStyle: true,
                    },
                },
                tooltip: {
                    backgroundColor: '#FFFFFF',
                    borderColor: '#D9D3CA',
                    borderWidth: 1,
                    titleColor: '#1A1714',
                    bodyColor: '#5C564E',
                    callbacks: {
                        label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} °C`,
                    },
                },
            },
            scales: {
                x: {
                    grid:  { color: 'rgba(26,23,20,.07)' },    /* visível no fundo claro */
                    ticks: { color: '#9C958C', maxTicksLimit: 10, font: { size: 11 } },
                },
                y: {
                    min: 0, max: 100,
                    grid:  { color: 'rgba(26,23,20,.07)' },
                    ticks: {
                        color: '#9C958C',
                        font: { size: 11 },
                        callback: v => v + ' °C',
                    },
                },
            },
        },
        plugins: [{
            id: 'threshold-line',
            afterDraw(chart) {
                const { ctx, scales: { y }, chartArea } = chart;
                const yPos = y.getPixelForValue(60);
                ctx.save();
                ctx.setLineDash([6, 4]);
                ctx.strokeStyle = 'rgba(200,57,28,.55)';
                ctx.lineWidth   = 1.5;
                ctx.beginPath();
                ctx.moveTo(chartArea.left,  yPos);
                ctx.lineTo(chartArea.right, yPos);
                ctx.stroke();
                ctx.fillStyle = 'rgba(200,57,28,.7)';
                ctx.font      = '11px Figtree,sans-serif';
                ctx.fillText('Limiar 60 °C', chartArea.right - 86, yPos - 5);
                ctx.restore();
            },
        }],
    });
}

buildChart();

// Auto-refresh a cada 10s
setInterval(() => window.location.replace(window.location.href), 10_000);
</script>
<?php endif; ?>

<script>
function changeDevice(id) {
    const url = new URL(window.location);
    url.searchParams.set('device', id);
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>