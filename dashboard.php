<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAuth();

$pdo      = getConnection();
$user     = currentUser();
$deviceId = $_GET['device'] ?? 'esp32-01';

$devices = $pdo->query("SELECT device_id, last_seen FROM iot_devices ORDER BY device_id")->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM iot_devices WHERE device_id = ?');
$stmt->execute([$deviceId]);
$device = $stmt->fetch();

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.iot-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;
}
.iot-header h1 {
    font-family: var(--font-display); font-size: 1.4rem; font-weight: 800;
    color: var(--t1); display: flex; align-items: center; gap: .6rem;
}
.device-select {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-md); color: var(--t1);
    padding: .45rem .9rem; font-size: .85rem; cursor: pointer; outline: none;
    font-family: var(--font-body);
}

/* Status pill */
.status-pill {
    display: inline-flex; align-items: center; gap: .45rem;
    padding: .3rem .85rem; border-radius: 99px;
    font-size: .78rem; font-weight: 600;
}
.status-pill.online  { background: #EDFAF4; color: #1A7B46; }
.status-pill.offline { background: #F0ECE5; color: #9C958C; }
.status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.status-pill.online .status-dot { animation: pulse-dot 1.6s ease-in-out infinite; }
@keyframes pulse-dot {
    0%,100% { box-shadow: 0 0 0 0 rgba(26,123,70,.6); }
    50%     { box-shadow: 0 0 0 5px rgba(26,123,70,0); }
}

/* Banner */
.alarm-banner {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem 1.4rem; border-radius: 14px; margin-bottom: 1.5rem;
    font-family: var(--font-display); font-weight: 700; font-size: 1rem;
    border: 1.5px solid; transition: background .4s, border-color .4s;
}
.alarm-banner.normal { background: #EDFAF4; border-color: rgba(26,123,70,.25); color: #1A7B46; }
.alarm-banner.fire   {
    background: #FDF0EE; border-color: rgba(200,57,28,.35); color: #C8391C;
    animation: banner-pulse 1.4s ease-in-out infinite;
}
@keyframes banner-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(200,57,28,.2); }
    50%     { box-shadow: 0 0 0 6px rgba(200,57,28,0); }
}
.banner-icon  { font-size: 1.5rem; flex-shrink: 0; }
.banner-text  { flex: 1; }
.banner-sub   { font-size: .72rem; font-weight: 400; opacity: .75; margin-top: .1rem; }
.banner-temp  { font-size: 1.4rem; font-weight: 800; font-family: var(--font-display); font-variant-numeric: tabular-nums; }

/* Grid */
.iot-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1.2rem; margin-bottom: 1.5rem;
}
.iot-indicator-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); padding: 1.75rem 1.25rem;
    display: flex; flex-direction: column; align-items: center; gap: 1rem;
    box-shadow: var(--shadow-xs); transition: border-color .3s, box-shadow .3s;
}
.iot-indicator-card.on-fire { border-color: rgba(200,57,28,.4); box-shadow: 0 0 0 3px rgba(200,57,28,.08); }

.indicator-ring { position: relative; width: 110px; height: 110px; }
.indicator-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
.indicator-ring .track { fill: none; stroke: #EAE5DE; stroke-width: 8; }
.indicator-ring .fill  {
    fill: none; stroke-width: 8; stroke-linecap: round;
    stroke-dasharray: 283; stroke-dashoffset: 283;
    transition: stroke-dashoffset .6s cubic-bezier(.4,0,.2,1), stroke .4s;
}
.indicator-icon {
    position: absolute; inset: 0; display: flex; align-items: center;
    justify-content: center; font-size: 2rem; transition: filter .4s;
}
.indicator-label    { font-family: var(--font-display); font-size: .88rem; font-weight: 700; color: var(--t1); text-align: center; }
.indicator-sublabel { font-size: .75rem; color: var(--t3); text-align: center; margin-top: -.4rem; }

.temp-indicator[data-level="ok"]      .fill { stroke: #1A7B46; }
.temp-indicator[data-level="warning"] .fill { stroke: #B86A0A; }
.temp-indicator[data-level="danger"]  .fill { stroke: #C8391C; }
.temp-indicator[data-level="danger"]  .indicator-icon { filter: drop-shadow(0 0 8px rgba(200,57,28,.5)); }

.btn-indicator[data-state="1"]   .fill           { stroke: #C8391C; stroke-dashoffset: 0; }
.btn-indicator[data-state="1"]   .indicator-icon { filter: drop-shadow(0 0 10px rgba(200,57,28,.5)); }
.alarm-indicator[data-state="1"] .fill           { stroke: #C8391C; stroke-dashoffset: 0; }
.alarm-indicator[data-state="1"] .indicator-icon { filter: drop-shadow(0 0 14px rgba(200,57,28,.5)); animation: icon-blink .9s step-start infinite; }
@keyframes icon-blink { 50% { opacity: 0; } }
.spr-indicator[data-state="1"]   .fill           { stroke: #1D4ED8; stroke-dashoffset: 0; }
.spr-indicator[data-state="1"]   .indicator-icon { filter: drop-shadow(0 0 10px rgba(29,78,216,.5)); }

/* Controles */
.control-card {
    background: var(--surface); border: 1px solid var(--border-soft);
    border-radius: var(--r-lg); padding: 1.75rem; display: flex;
    flex-direction: column; gap: 1.2rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-xs);
}
.control-card h3 { font-family: var(--font-display); font-size: .9rem; font-weight: 700; color: var(--t1); }
.control-row { display: flex; gap: .8rem; flex-wrap: wrap; }
.btn-fire  { flex:1;min-width:150px;background:#FDF0EE;color:#C8391C;border:1px solid rgba(200,57,28,.25);border-radius:var(--r-md);padding:.7rem 1rem;font-family:var(--font-body);font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s; }
.btn-fire:hover  { background:#C8391C;color:#fff;box-shadow:0 4px 12px rgba(200,57,28,.25); }
.btn-reset { flex:1;min-width:150px;background:#EDFAF4;color:#1A7B46;border:1px solid rgba(26,123,70,.25);border-radius:var(--r-md);padding:.7rem 1rem;font-family:var(--font-body);font-size:.875rem;font-weight:600;cursor:pointer;transition:all .18s; }
.btn-reset:hover { background:#1A7B46;color:#fff;box-shadow:0 4px 12px rgba(26,123,70,.2); }
.cmd-status { font-size: .75rem; color: var(--t3); text-align: center; min-height: 1.2em; }

/* Log */
.event-log { background:var(--surface);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:1.5rem;margin-bottom:1.5rem;box-shadow:var(--shadow-xs); }
.event-log h3 { font-family:var(--font-display);font-size:.9rem;font-weight:700;color:var(--t1);margin-bottom:1rem; }
.log-list { display:flex;flex-direction:column;gap:.45rem;max-height:220px;overflow-y:auto; }
.log-item { display:flex;align-items:center;gap:.65rem;padding:.5rem .65rem;border-radius:var(--r-sm);background:var(--bg);font-size:.8rem;color:var(--t2);animation:log-appear .25s ease; }
@keyframes log-appear { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }
.log-dot  { width:7px;height:7px;border-radius:50%;flex-shrink:0; }
.log-time { color:var(--t3);font-size:.72rem;margin-left:auto;white-space:nowrap; }
.last-seen-txt { font-size:.75rem;color:var(--t3); }

/* API ref */
.api-ref { background:var(--surface);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:1.5rem;box-shadow:var(--shadow-xs); }
.api-ref h3 { font-family:var(--font-display);font-size:.9rem;font-weight:700;color:var(--t1);margin-bottom:1rem; }
.api-code { background:var(--bg);border:1px solid var(--border-soft);border-radius:var(--r-md);padding:1rem 1.2rem;font-family:var(--font-mono);font-size:.78rem;color:var(--t2);overflow-x:auto;white-space:pre;line-height:1.7; }
.api-method  { color:#1A7B46; font-weight:700; }
.api-url     { color:#1D4ED8; }
.api-comment { color:var(--t3); }
</style>

<div class="iot-header">
    <h1>
        🔥 Supervisório
        <span class="status-pill offline" id="statusPill">
            <span class="status-dot"></span>
            <span id="statusText">Aguardando…</span>
        </span>
    </h1>
    <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap">
        <select class="device-select" id="deviceSelect" onchange="changeDevice(this.value)">
            <?php foreach ($devices as $d): ?>
                <option value="<?= htmlspecialchars($d['device_id']) ?>"
                    <?= $d['device_id'] === $deviceId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['device_id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="last-seen-txt" id="lastSeen">—</span>
    </div>
</div>

<!-- Banner: id="bannerSub" adicionado para atualização via JS -->
<div class="alarm-banner normal" id="alarmBanner">
    <span class="banner-icon" id="bannerIcon">✅</span>
    <span class="banner-text">
        <div id="bannerTitle">Sistema Normal</div>
        <div class="banner-sub" id="bannerSub">Nenhuma ocorrência detectada</div>
    </span>
    <span class="banner-temp" id="bannerTemp">— °C</span>
</div>

<div class="iot-grid">
    <div class="iot-indicator-card">
        <div class="indicator-ring temp-indicator" data-level="ok" id="tempIndicator">
            <svg viewBox="0 0 100 100"><circle class="track" cx="50" cy="50" r="45"/><circle class="fill" cx="50" cy="50" r="45" id="tempFill"/></svg>
            <div class="indicator-icon" id="tempIcon">🌡️</div>
        </div>
        <div class="indicator-label">Temperatura</div>
        <div class="indicator-sublabel" id="tempValue">Aguardando…</div>
    </div>

    <div class="iot-indicator-card">
        <div class="indicator-ring btn-indicator" data-state="0" id="btnIndicator">
            <svg viewBox="0 0 100 100"><circle class="track" cx="50" cy="50" r="45"/><circle class="fill" cx="50" cy="50" r="45"/></svg>
            <div class="indicator-icon" id="btnIcon">🚨</div>
        </div>
        <div class="indicator-label">Botão Emergência</div>
        <div class="indicator-sublabel" id="btnState">Aguardando…</div>
    </div>

    <div class="iot-indicator-card" id="alarmCard">
        <div class="indicator-ring alarm-indicator" data-state="0" id="alarmIndicator">
            <svg viewBox="0 0 100 100"><circle class="track" cx="50" cy="50" r="45"/><circle class="fill" cx="50" cy="50" r="45"/></svg>
            <div class="indicator-icon" id="alarmIcon">🔕</div>
        </div>
        <div class="indicator-label">Alarme</div>
        <div class="indicator-sublabel" id="alarmState">Aguardando…</div>
    </div>

    <div class="iot-indicator-card" id="sprCard">
        <div class="indicator-ring spr-indicator" data-state="0" id="sprIndicator">
            <svg viewBox="0 0 100 100"><circle class="track" cx="50" cy="50" r="45"/><circle class="fill" cx="50" cy="50" r="45"/></svg>
            <div class="indicator-icon" id="sprIcon">💧</div>
        </div>
        <div class="indicator-label">Sprinklers</div>
        <div class="indicator-sublabel" id="sprState">Aguardando…</div>
    </div>
</div>

<div class="control-card">
    <h3>⚙️ Controles do Supervisório</h3>
    <div class="control-row">
        <button class="btn-reset" onclick="sendCommand('reset', 1)">✅ Resetar para Normal</button>
        <button class="btn-fire"  onclick="sendCommand('alarm', 1)">🔥 Forçar Alarme</button>
    </div>
    <div class="cmd-status" id="cmdStatus">Nenhum comando enviado ainda</div>
</div>

<div class="event-log">
    <h3>📋 Log de Eventos</h3>
    <div class="log-list" id="logList">
        <div class="log-item" style="color:var(--t3)">Aguardando eventos…</div>
    </div>
</div>

<script>
const API_BASE = '<?= APP_BASE ?>/api';
let deviceId   = '<?= htmlspecialchars($deviceId) ?>';

let prev = { online: null, button: -1, led: -1, temperature: -1, alarm: -1, sprinkler: -1 };
let logItems = [];

async function poll() {
    try {
        const res = await fetch(API_BASE + '/device.php?device_id=' + deviceId);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        updateUI(data);
    } catch (e) {
        if (prev.online !== false) { setBadge(false); prev.online = false; }
    }
}

function setBadge(online) {
    var pill = document.getElementById('statusPill');
    var txt  = document.getElementById('statusText');
    pill.classList.remove('online', 'offline');
    pill.classList.add(online ? 'online' : 'offline');
    txt.textContent = online ? 'Online' : 'Offline';
}

function updateUI(data) {
    var online      = data.online;
    var button      = data.button;
    var temperature = data.temperature;
    var alarm       = data.alarm;
    var sprinkler   = data.sprinkler;
    var secsAgo     = data.secs_ago;

    // Badge
    if (prev.online !== online) {
        setBadge(online);
        if (prev.online !== null)
            addLog(online ? '✔ ESP32 conectado' : '✘ ESP32 desconectado', online ? '#1A7B46' : '#C8391C');
        prev.online = online;
    }

    // Último visto
    if (data.last_seen) {
        var d = new Date(data.last_seen.replace(' ', 'T'));
        document.getElementById('lastSeen').textContent =
            'Visto: ' + d.toLocaleTimeString('pt-BR') + ' (' + secsAgo + 's)';
    }

    // Banner — título, subtítulo e cor
    if (prev.alarm !== alarm) {
        var banner = document.getElementById('alarmBanner');
        banner.className = 'alarm-banner ' + (alarm ? 'fire' : 'normal');
        document.getElementById('bannerIcon').textContent  = alarm ? '🔥' : '✅';
        document.getElementById('bannerTitle').textContent = alarm ? 'RISCO DE INCÊNDIO' : 'Sistema Normal';
        // Subtítulo atualizado corretamente
        document.getElementById('bannerSub').textContent   = alarm
            ? 'Alarme ativo — acione os controles abaixo'
            : 'Nenhuma ocorrência detectada';
    }
    document.getElementById('bannerTemp').textContent = temperature + ' °C';

    // Temperatura
    if (prev.temperature !== temperature) {
        var fill   = document.getElementById('tempFill');
        var offset = 283 - (Math.min(Math.max(temperature, 0), 100) / 100) * 283;
        fill.style.strokeDashoffset = offset;
        var level = temperature >= 60 ? 'danger' : temperature >= 40 ? 'warning' : 'ok';
        document.getElementById('tempIndicator').dataset.level = level;
        document.getElementById('tempValue').textContent = temperature + ' °C';

        if (prev.temperature !== -1) {
            if (temperature >= 60 && prev.temperature < 60)
                addLog('🌡️ Temperatura crítica: ' + temperature + ' °C', '#C8391C');
            else if (temperature >= 40 && prev.temperature < 40)
                addLog('♨️ Temperatura elevada: ' + temperature + ' °C', '#B86A0A');
            else if (temperature < 40 && prev.temperature >= 40)
                addLog('🌡️ Temperatura normalizada: ' + temperature + ' °C', '#1A7B46');
        }
    }

    // Botão emergência
    if (prev.button !== button) {
        document.getElementById('btnIndicator').dataset.state = button;
        document.getElementById('btnState').textContent = button ? '🔴 Pressionado' : '⚫ Solto';
        document.getElementById('btnIcon').textContent  = button ? '🚨' : '🔕';
        if (prev.button !== -1 && button) addLog('🚨 Botão de emergência pressionado!', '#C8391C');
    }

    // Alarme
    if (prev.alarm !== alarm) {
        var alarmInd  = document.getElementById('alarmIndicator');
        var alarmCard = document.getElementById('alarmCard');
        alarmInd.dataset.state = alarm;
        alarmCard.classList.toggle('on-fire', !!alarm);
        document.getElementById('alarmState').textContent = alarm ? '🔴 Ativo' : '⚫ Inativo';
        document.getElementById('alarmIcon').textContent  = alarm ? '🔔' : '🔕';
        if (prev.alarm !== -1)
            addLog(alarm ? '🔥 ALARME ATIVADO!' : '✅ Sistema normalizado', alarm ? '#C8391C' : '#1A7B46');
    }

    // Sprinklers
    if (prev.sprinkler !== sprinkler) {
        var sprInd  = document.getElementById('sprIndicator');
        var sprCard = document.getElementById('sprCard');
        sprInd.dataset.state = sprinkler;
        sprCard.classList.toggle('on-fire', !!sprinkler);
        document.getElementById('sprState').textContent = sprinkler ? '💧 Ativo' : '⚫ Inativo';
        document.getElementById('sprIcon').textContent  = sprinkler ? '💦' : '💧';
        if (prev.sprinkler !== -1)
            addLog(sprinkler ? '💧 Sprinklers acionados' : '💧 Sprinklers desligados', sprinkler ? '#1D4ED8' : '#9C958C');
    }

    prev = { online, button, temperature, alarm, sprinkler, led: data.led };
}

async function sendCommand(command, value) {
    var statusEl = document.getElementById('cmdStatus');
    statusEl.textContent = 'Enviando…';
    try {
        var res  = await fetch(API_BASE + '/command.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ device_id: deviceId, command: command, value: value }),
        });
        var data = await res.json();
        if (data.status === 'queued') {
            var lbl = command === 'reset' ? 'Reset para Normal' : 'Forçar Alarme';
            statusEl.textContent = '✔ Comando "' + lbl + '" enfileirado';
            addLog(command === 'reset'
                ? '✅ Supervisório: reset enviado (supressão de 60s no ESP32)'
                : '🔥 Supervisório: alarme forçado',
                command === 'reset' ? '#1A7B46' : '#C8391C');
        } else {
            statusEl.textContent = 'Erro: ' + (data.error || 'desconhecido');
        }
    } catch (e) {
        statusEl.textContent = '✘ Falha na conexão';
    }
}

function addLog(message, color) {
    var now = new Date().toLocaleTimeString('pt-BR');
    logItems.unshift({ message: message, color: color || '#9C958C', time: now });
    if (logItems.length > 40) logItems.pop();
    var html = '';
    for (var i = 0; i < logItems.length; i++) {
        var item = logItems[i];
        html += '<div class="log-item"><span class="log-dot" style="background:' + item.color + '"></span>'
              + item.message + '<span class="log-time">' + item.time + '</span></div>';
    }
    document.getElementById('logList').innerHTML = html;
}

function changeDevice(id) {
    deviceId = id;
    prev = { online: null, button: -1, led: -1, temperature: -1, alarm: -1, sprinkler: -1 };
    addLog('Dispositivo: ' + id, '#1D4ED8');
    poll();
    var url = new URL(window.location.href);
    url.searchParams.set('device', id);
    window.history.replaceState({}, '', url.toString());
}

poll();
setInterval(poll, 500);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>