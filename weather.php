<?php
// ============================================================
// weather.php — Previsão do tempo via CPTEC/INPE
// ============================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAuth();

// ── Mapa de siglas → descrição + emoji + severidade ────────
const TEMPO = [
    'cl'  => ['Céu Claro',                          '☀️',  0],
    'ps'  => ['Predomínio de Sol',                  '🌤️', 0],
    'pn'  => ['Parcialmente Nublado',               '⛅',  0],
    'vn'  => ['Variação de Nebulosidade',           '🌥️', 0],
    'n'   => ['Nublado',                            '☁️',  0],
    'e'   => ['Encoberto',                          '☁️',  0],
    'nv'  => ['Nevoeiro',                           '🌫️', 1],
    'cv'  => ['Chuvisco',                           '🌦️', 1],
    'c'   => ['Chuva',                              '🌧️', 1],
    'ch'  => ['Chuvoso',                            '🌧️', 1],
    'ct'  => ['Chuva à Tarde',                      '🌦️', 1],
    'cm'  => ['Chuva pela Manhã',                   '🌦️', 1],
    'cn'  => ['Chuva à Noite',                      '🌦️', 1],
    'ci'  => ['Chuvas Isoladas',                    '🌧️', 1],
    'ec'  => ['Encoberto com Chuvas Isoladas',      '🌧️', 1],
    'pp'  => ['Poss. de Pancadas de Chuva',         '🌦️', 1],
    'pc'  => ['Pancadas de Chuva',                  '🌧️', 1],
    'pt'  => ['Pancadas de Chuva à Tarde',          '🌦️', 1],
    'pm'  => ['Pancadas de Chuva pela Manhã',       '🌦️', 1],
    'pnt' => ['Pancadas de Chuva à Noite',          '🌦️', 1],
    'np'  => ['Nublado e Pancadas de Chuva',        '🌧️', 1],
    'npt' => ['Nublado c/ Pancadas à Tarde',        '🌧️', 1],
    'npn' => ['Nublado c/ Pancadas à Noite',        '🌧️', 1],
    'npm' => ['Nublado c/ Pancadas pela Manhã',     '🌧️', 1],
    'npp' => ['Nublado c/ Poss. de Chuva',          '🌦️', 1],
    'nct' => ['Nublado c/ Poss. de Chuva à Tarde',  '🌦️', 1],
    'ncn' => ['Nublado c/ Poss. de Chuva à Noite',  '🌦️', 1],
    'ncm' => ['Nublado c/ Poss. de Chuva de Manhã', '🌦️', 1],
    'psc' => ['Possibilidade de Chuva',             '🌦️', 1],
    'pcm' => ['Poss. de Chuva pela Manhã',          '🌦️', 1],
    'pct' => ['Poss. de Chuva à Tarde',             '🌦️', 1],
    'pcn' => ['Poss. de Chuva à Noite',             '🌦️', 1],
    'ppt' => ['Poss. de Panc. de Chuva à Tarde',    '🌦️', 1],
    'ppm' => ['Poss. de Panc. de Chuva de Manhã',   '🌦️', 1],
    'ppn' => ['Poss. de Panc. de Chuva à Noite',    '🌦️', 1],
    'in'  => ['Instável',                           '⛈️',  2],
    't'   => ['Tempestade',                         '⛈️',  2],
    'g'   => ['Geada',                              '🌨️', 2],
    'ne'  => ['Neve',                               '❄️',  2],
    'f'   => ['Friagem',                            '🌬️', 1],
    'nd'  => ['Não Definido',                       '❓',  0],
];

function tempoInfo(string $sigla): array {
    return TEMPO[strtolower($sigla)] ?? ['Condição desconhecida', '❓', 0];
}

function iuvLabel(float $iuv): array {
    return match(true) {
        $iuv <= 2  => ['Baixo',        'var(--accent)'],
        $iuv <= 5  => ['Moderado',     'var(--yellow)'],
        $iuv <= 7  => ['Alto',         'var(--yellow)'],
        $iuv <= 10 => ['Muito Alto',   'var(--red)'],
        default    => ['Extremo',      'var(--red)'],
    };
}

function fetchXml(string $url): ?SimpleXMLElement {
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($raw);
    return $xml ?: null;
}

// ── Lógica principal ────────────────────────────────────────
$cidadeBusca  = trim($_GET['city'] ?? 'Catanduva');
$erros        = [];
$cidades      = [];
$cidadeSel    = null;
$previsao     = [];
$alertas      = [];
$atualizacao  = '';

// 1) Buscar cidades
$xmlCidades = fetchXml('http://servicos.cptec.inpe.br/XML/listaCidades?city=' . urlencode($cidadeBusca));

if (!$xmlCidades) {
    $erros[] = 'Não foi possível conectar à API do CPTEC/INPE. Tente novamente.';
} elseif (!isset($xmlCidades->cidade)) {
    $erros[] = "Nenhuma cidade encontrada para \"" . htmlspecialchars($cidadeBusca) . "\".";
} else {
    foreach ($xmlCidades->cidade as $c) {
        $cidades[] = [
            'id'   => (string) $c->id,
            'nome' => (string) $c->nome,
            'uf'   => (string) $c->uf,
        ];
    }

    // Seleciona cidade: via ?id= ou pega a primeira da lista
    $cidadeId = isset($_GET['id']) ? (int)$_GET['id'] : (int)$cidades[0]['id'];
    foreach ($cidades as $c) {
        if ((int)$c['id'] === $cidadeId) { $cidadeSel = $c; break; }
    }
    if (!$cidadeSel) $cidadeSel = $cidades[0];

    // 2) Buscar previsão 7 dias
    $xmlPrev = fetchXml("http://servicos.cptec.inpe.br/XML/cidade/7dias/{$cidadeSel['id']}/previsao.xml");

    if (!$xmlPrev) {
        $erros[] = 'Não foi possível obter a previsão. Tente novamente em instantes.';
    } else {
        $atualizacao = (string) $xmlPrev->atualizacao;
        foreach ($xmlPrev->previsao as $p) {
            $sigla  = (string) $p->tempo;
            $info   = tempoInfo($sigla);
            $dia    = (string) $p->dia;
            $max    = (string) $p->maxima;
            $min    = (string) $p->minima;
            $iuv    = (float)  $p->iuv;

            $previsao[] = [
                'dia'    => $dia,
                'sigla'  => $sigla,
                'desc'   => $info[0],
                'emoji'  => $info[1],
                'alerta' => $info[2],
                'maxima' => $max,
                'minima' => $min,
                'iuv'    => $iuv,
            ];

            // Gera alertas para condições severas
            if ($info[2] >= 2) {
                $dataFmt = date('d/m', strtotime($dia));
                $alertas[] = [
                    'dia'   => $dataFmt,
                    'desc'  => $info[0],
                    'emoji' => $info[1],
                ];
            }
        }
    }
}

// Formata a data de hoje para destacar o primeiro dia
$hoje = date('Y-m-d');

$pageTitle  = 'Previsão do Tempo';
$activeMenu = 'weather';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Alertas meteorológicos -->
<?php if (!empty($alertas)): ?>
<div class="alert alert-danger" style="align-items:flex-start;flex-direction:column;gap:.5rem;margin-bottom:1.5rem">
    <div style="display:flex;align-items:center;gap:.5rem;font-weight:600">
        ⚠️ Alerta meteorológico para <?= htmlspecialchars($cidadeSel['nome'] ?? '') ?>
    </div>
    <?php foreach ($alertas as $a): ?>
        <div style="font-size:.875rem">
            <?= $a['emoji'] ?> <strong><?= $a['dia'] ?>:</strong> <?= htmlspecialchars($a['desc']) ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Header da página + busca -->
<div class="page-header" style="flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
    <div>
        <h2>
            Previsão do Tempo
            <?php if ($cidadeSel): ?>
                <span style="color:var(--t3);font-weight:400;font-size:.95rem">
                    — <?= htmlspecialchars($cidadeSel['nome']) ?> / <?= htmlspecialchars($cidadeSel['uf']) ?>
                </span>
            <?php endif; ?>
        </h2>
        <?php if ($atualizacao): ?>
            <p>Atualizado em <?= date('d/m/Y', strtotime($atualizacao)) ?> · Fonte: CPTEC/INPE</p>
        <?php endif; ?>
    </div>

    <!-- Formulário de busca -->
    <form method="GET" action="">
        <div class="toolbar">
            <input type="text" name="city" class="search-input"
                   value="<?= htmlspecialchars($cidadeBusca) ?>"
                   placeholder="Buscar cidade..." style="width:200px">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
        </div>
    </form>
</div>

<!-- Erros -->
<?php if (!empty($erros)): ?>
    <div class="alert alert-warning">
        <span>⚠</span>
        <div><?= implode('<br>', array_map('htmlspecialchars', $erros)) ?></div>
    </div>
<?php endif; ?>

<!-- Se encontrou múltiplas cidades, mostra lista de seleção -->
<?php if (count($cidades) > 1): ?>
<div class="card" style="margin-bottom:1.5rem;padding:1.2rem 1.5rem">
    <div style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;
                color:var(--t3);margin-bottom:.8rem">
        <?= count($cidades) ?> cidades encontradas — selecione uma:
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
        <?php foreach ($cidades as $c): ?>
            <?php $isActive = ($c['id'] === $cidadeSel['id']); ?>
            <a href="?city=<?= urlencode($cidadeBusca) ?>&id=<?= $c['id'] ?>"
               class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= htmlspecialchars($c['nome']) ?> / <?= htmlspecialchars($c['uf']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Cards de previsão 7 dias -->
<?php if (!empty($previsao)): ?>

<!-- Destaque: dia de hoje (primeiro card grande) -->
<?php $hoje_prev = $previsao[0]; ?>
<div class="card" style="margin-bottom:1.5rem;padding:2rem;position:relative;overflow:hidden">
    <div style="position:absolute;right:1.5rem;top:50%;transform:translateY(-50%);
                font-size:6rem;opacity:.07;pointer-events:none;line-height:1">
        <?= $hoje_prev['emoji'] ?>
    </div>
    <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;
                color:var(--t3);margin-bottom:.5rem">
        Hoje · <?= date('d \d\e F', strtotime($hoje_prev['dia'])) ?>
    </div>
    <div style="font-size:3rem;font-family:'Sora',sans-serif;font-weight:700;color:var(--t1);
                line-height:1;margin-bottom:.3rem">
        <?= $hoje_prev['maxima'] ?>°<span style="font-size:1.8rem;color:var(--t2);font-weight:400">C</span>
    </div>
    <div style="font-size:1rem;color:var(--t2);margin-bottom:1rem">
        <?= $hoje_prev['emoji'] ?> <?= htmlspecialchars($hoje_prev['desc']) ?>
        <span style="color:var(--t3);font-size:.9rem">· Mín <?= $hoje_prev['minima'] ?>°C</span>
    </div>
    <?php if ($hoje_prev['iuv'] > 0): ?>
        <?php [$iuvLabel, $iuvColor] = iuvLabel($hoje_prev['iuv']); ?>
        <div style="display:inline-flex;align-items:center;gap:.5rem;
                    background:var(--border-soft);border-radius:20px;padding:.3rem .9rem;font-size:.8rem">
            <span style="color:var(--t3)">Índice UV:</span>
            <span style="font-weight:700;color:<?= $iuvColor ?>"><?= $hoje_prev['iuv'] ?> — <?= $iuvLabel ?></span>
        </div>
    <?php endif; ?>
    <?php if ($hoje_prev['alerta'] >= 2): ?>
        <div style="margin-top:.8rem">
            <span class="badge" style="background:var(--red-dim);color:var(--red);font-size:.75rem">
                ⚠ Condição severa
            </span>
        </div>
    <?php endif; ?>
</div>

<!-- Próximos dias -->
<div style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;
            color:var(--t3);margin-bottom:.8rem">
    Próximos dias
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.8rem;margin-bottom:2rem">
    <?php foreach (array_slice($previsao, 1) as $p): ?>
    <?php [$iuvLabel, $iuvColor] = iuvLabel($p['iuv']); ?>
    <div class="card" style="padding:1.1rem 1rem;text-align:center;
                              <?= $p['alerta'] >= 2 ? 'border-color:var(--red-dim)' : '' ?>;
                              transition:transform var(--transition),border-color var(--transition)"
         onmouseover="this.style.transform='translateY(-3px)'"
         onmouseout="this.style.transform=''">

        <div style="font-size:.75rem;font-weight:600;color:var(--t3);text-transform:uppercase;
                    letter-spacing:.05em;margin-bottom:.5rem">
            <?= date('D', strtotime($p['dia'])) ?><br>
            <span style="font-size:.8rem;text-transform:none;font-weight:400;color:var(--t2)">
                <?= date('d/m', strtotime($p['dia'])) ?>
            </span>
        </div>

        <div style="font-size:1.8rem;line-height:1;margin-bottom:.6rem"><?= $p['emoji'] ?></div>

        <div style="font-family:'Sora',sans-serif;font-size:1.3rem;font-weight:700;color:var(--t1)">
            <?= $p['maxima'] ?>°
        </div>
        <div style="font-size:.8rem;color:var(--t3)">mín <?= $p['minima'] ?>°</div>

        <div style="font-size:.72rem;color:var(--t2);margin-top:.5rem;line-height:1.3">
            <?= htmlspecialchars($p['desc']) ?>
        </div>

        <?php if ($p['iuv'] > 0): ?>
        <div style="margin-top:.6rem;font-size:.7rem;color:<?= $iuvColor ?>;font-weight:600">
            UV <?= $p['iuv'] ?>
        </div>
        <?php endif; ?>

        <?php if ($p['alerta'] >= 2): ?>
        <div style="margin-top:.5rem">
            <span style="font-size:.65rem;background:var(--red-dim);color:var(--red);
                         padding:.1rem .4rem;border-radius:4px;font-weight:600">⚠ ALERTA</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Legenda de condições -->
<div class="card" style="padding:1.2rem 1.5rem">
    <div style="font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;
                color:var(--t3);margin-bottom:.8rem">Legenda das condições</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.4rem">
        <?php
        $legendaGrupos = [
            ['cl','ps','pn','vn','n','e'],
            ['cv','c','ch','ci','ec','pp','pc'],
            ['t','in','g','ne','f','nv'],
        ];
        foreach ($legendaGrupos as $grupo):
            foreach ($grupo as $sigla):
                if (!isset(TEMPO[$sigla])) continue;
                [$desc, $emoji, $sev] = TEMPO[$sigla];
                $cor = match($sev) { 2 => 'var(--red)', 1 => 'var(--yellow)', default => 'var(--t3)' };
        ?>
            <div style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:var(--t2)">
                <span><?= $emoji ?></span>
                <span style="color:<?= $cor ?>;font-weight:<?= $sev > 0 ? '500' : '400' ?>">
                    <?= strtoupper($sigla) ?>
                </span>
                <span style="color:var(--t3)">— <?= htmlspecialchars($desc) ?></span>
            </div>
        <?php endforeach; endforeach; ?>
    </div>
</div>

<?php elseif (empty($erros)): ?>
    <div class="card" style="text-align:center;padding:3rem;color:var(--t3)">
        <div style="font-size:2.5rem;margin-bottom:.8rem">🌤️</div>
        <p>Nenhuma previsão disponível para esta cidade.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>