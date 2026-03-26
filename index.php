<?php
require_once 'includes/db.php';
require_once 'includes/layout.php';

$db = getDB();

// Settings
$settings = [];
foreach ($db->query("SELECT `key`, value FROM settings") as $row) {
    $settings[$row['key']] = $row['value'];
}
$metaFunc = $settings['meta_funcionarios'] ?? 150;
$metaEq   = $settings['meta_equipes'] ?? 15;

// Stats
$totalFunc  = $db->query("SELECT COUNT(*) FROM funcionarios WHERE status='Ativo'")->fetchColumn();
$totalEq    = $db->query("SELECT COUNT(*) FROM equipes")->fetchColumn();
$totalDesal = $db->query("SELECT COUNT(*) FROM funcionarios WHERE alocado='Sim' AND status='Ativo'")->fetchColumn();
$totalInativ = $db->query("SELECT COUNT(*) FROM funcionarios WHERE status='Inativo'")->fetchColumn();

// Chart: por funcao_ey
$funcaoRows = $db->query("SELECT funcao_ey, COUNT(*) as total FROM funcionarios WHERE status='Ativo' AND funcao_ey != '' GROUP BY funcao_ey ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

// Chart: por nivel_ey
$nivelRows = $db->query("SELECT nivel_ey, COUNT(*) as total FROM funcionarios WHERE status='Ativo' AND nivel_ey != '' GROUP BY nivel_ey ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

// Chart: por equipe
$equipeRows = $db->query("
    SELECT e.nome, COUNT(f.id) as total 
    FROM equipes e 
    LEFT JOIN funcionarios f ON f.equipe_id = e.id AND f.status='Ativo' AND f.alocado='Sim'
    GROUP BY e.id, e.nome 
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Table: funcionários por equipe
$funcByEq = $db->query("
    SELECT f.nome, f.gui, f.funcao_ey, f.nivel_ey, f.funcao_bb, f.nivel_bb, f.alocado, f.status,
           e.nome as equipe_nome, f.lider, f.gerente
    FROM funcionarios f
    LEFT JOIN equipes e ON e.id = f.equipe_id
    WHERE f.status='Ativo'
    ORDER BY e.nome, f.nome
")->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Dashboard', 'dashboard');

$pctFunc = $metaFunc > 0 ? min(100, round($totalFunc / $metaFunc * 100)) : 0;
$pctEq   = $metaEq > 0  ? min(100, round($totalEq / $metaEq * 100)) : 0;

// Build chart data
$funcaoLabels = json_encode(array_column($funcaoRows, 'funcao_ey'));
$funcaoData   = json_encode(array_column($funcaoRows, 'total'));
$nivelLabels  = json_encode(array_column($nivelRows, 'nivel_ey'));
$nivelData    = json_encode(array_column($nivelRows, 'total'));
$eqLabels     = json_encode(array_column($equipeRows, 'nome'));
$eqData       = json_encode(array_column($equipeRows, 'total'));
?>

<!-- CARDS -->
<div class="cards-grid">
    <!-- Funcionários -->
    <div class="card">
        <button class="card-edit-btn" onclick="editMeta('meta_funcionarios', <?= $metaFunc ?>, 'Funcionários')">✎ Meta</button>
        <div class="card-label">Funcionários Ativos</div>
        <div class="card-value"><?= $totalFunc ?></div>
        <div class="card-meta">
            <span>Meta:</span>
            <span class="meta-target"><?= $metaFunc ?></span>
            <span>•</span>
            <span><?= $pctFunc ?>%</span>
        </div>
        <div class="card-progress">
            <div class="card-progress-bar" style="width:<?= $pctFunc ?>%"></div>
        </div>
    </div>

    <!-- Equipes -->
    <div class="card">
        <button class="card-edit-btn" onclick="editMeta('meta_equipes', <?= $metaEq ?>, 'Equipes')">✎ Meta</button>
        <div class="card-label">Total de Equipes</div>
        <div class="card-value"><?= $totalEq ?></div>
        <div class="card-meta">
            <span>Meta:</span>
            <span class="meta-target"><?= $metaEq ?></span>
            <span>•</span>
            <span><?= $pctEq ?>%</span>
        </div>
        <div class="card-progress">
            <div class="card-progress-bar" style="width:<?= $pctEq ?>%"></div>
        </div>
    </div>

    <!-- alocados -->
    <div class="card" style="--accent: var(--warning)">
        <div class="card-label">alocados Ativos</div>
        <div class="card-value" style="color:var(--warning)"><?= $totalDesal ?></div>
        <div class="card-meta" style="margin-top:10px">de <?= $totalFunc ?> ativos</div>
    </div>

    <!-- Inativos -->
    <div class="card" style="--accent: var(--danger)">
        <div class="card-label">Desligados</div>
        <div class="card-value" style="color:var(--danger)"><?= $totalInativ ?></div>
        <div class="card-meta" style="margin-top:10px">total histórico</div>
    </div>
</div>

<!-- CHARTS -->
<div class="charts-grid">
    <!-- Função EY -->
    <div class="chart-card">
        <div class="chart-title">Funcionários por Função na EY</div>
        <div class="chart-wrap">
            <canvas id="chartFuncaoEY"></canvas>
        </div>
    </div>

    <!-- Nível EY -->
    <div class="chart-card">
        <div class="chart-title">Funcionários por Nível EY</div>
        <div class="chart-wrap">
            <canvas id="chartNivelEY"></canvas>
        </div>
    </div>

    <!-- Por Equipe -->
    <div class="chart-card chart-full">
        <div class="chart-title">Funcionários por Equipe</div>
        <div class="chart-wrap" style="height:280px">
            <canvas id="chartEquipe"></canvas>
        </div>
    </div>
</div>

<!-- TABLE: Por Equipe -->
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title">Funcionários por Equipe</div>
        <div style="display:flex;gap:10px;align-items:center">
            <input class="form-control" id="tblSearch" placeholder="Buscar..." style="width:200px" oninput="filterTable('tblSearch','mainTable')">
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="mainTable">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>GUI</th>
                    <th>Equipe</th>
                    <th>Líder</th>
                    <th>Gerente</th>
                    <th>Função EY</th>
                    <th>Nível EY</th>
                    <th>Função BB</th>
                    <th>Nível BB</th>
                    <th>alocado</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentTeam = null;
                foreach ($funcByEq as $f):
                    if ($f['equipe_nome'] !== $currentTeam):
                        $currentTeam = $f['equipe_nome'];
                ?>
                <tr class="team-row-header">
                    <td colspan="10">📌 <?= htmlspecialchars($currentTeam ?: 'Sem Equipe') ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="color:var(--text);font-weight:500"><?= htmlspecialchars($f['nome']) ?></td>
                    <td><?= htmlspecialchars($f['gui']) ?></td>
                    <td><?= htmlspecialchars($f['equipe_nome'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['lider'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['gerente'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['funcao_ey'] ?? '—') ?></td>
                    <td>
                        <?php if ($f['nivel_ey']): ?>
                        <span class="badge badge-blue"><?= htmlspecialchars($f['nivel_ey']) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['funcao_bb'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['nivel_bb'] ?? '—') ?></td>
                    <td>
                        <?php if ($f['alocado'] === 'Sim'): ?>
                        <span class="badge badge-yellow">Sim</span>
                        <?php else: ?>
                        <span class="badge badge-green">Não</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($funcByEq)): ?>
                <tr><td colspan="10" class="empty-state">Nenhum funcionário ativo cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const COLORS = ['#ffe600', '#333333', '#999999', '#cccccc'];

function makeChart(id, type, labels, data, opts = {}) {
    const ctx = document.getElementById(id)?.getContext('2d');
    if (!ctx) return;
    return new Chart(ctx, {
        type,
        plugins: [ChartDataLabels],
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: COLORS,
                borderColor: '#0a0c10',
                borderWidth: 2,
                borderRadius: type === 'bar' ? 6 : 0,
                ...opts.dataset
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: '#8b90a0', font: { size: 12 } } },
                datalabels: {
                    color: (context) => {
                        const bg = context.dataset.backgroundColor;
                        const c = Array.isArray(bg) ? bg[context.dataIndex] : bg;
                        return (c === '#ffe600' || c === '#cccccc') ? '#000' : '#fff';
                    },
                    font: { weight: 'bold', size: 11 },
                    anchor: type === 'bar' ? 'end' : 'center',
                    align: type === 'bar' ? 'top' : 'center',
                    offset: 4,
                    display: (context) => context.dataset.data[context.dataIndex] > 0
                }
            },
            scales: type === 'bar' ? {
                x: { ticks: { color: '#8b90a0' }, grid: { color: '#1a1d25' } },
                y: { 
                    ticks: { color: '#8b90a0', stepSize: 1 }, 
                    grid: { color: '#1a1d25' },
                    suggestedMax: Math.max(...data, 0) + 1
                }
            } : undefined,
            ...opts.chartOptions
        }
    });
}

makeChart('chartFuncaoEY', 'bar', <?= $funcaoLabels ?>, <?= $funcaoData ?>, {
    chartOptions: { plugins: { legend: { display: false } } }
});
makeChart('chartNivelEY', 'doughnut', <?= $nivelLabels ?>, <?= $nivelData ?>);
makeChart('chartEquipe', 'bar', <?= $eqLabels ?>, <?= $eqData ?>, {
    dataset: { backgroundColor: '#ffe600', borderColor: '#0a0c10', borderWidth: 2 },
    chartOptions: { plugins: { legend: { display: false } } }
});
</script>

<?php renderFooter(); ?>
