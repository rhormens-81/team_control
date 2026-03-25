<?php
require_once 'includes/db.php';
require_once 'includes/layout.php';

$db = getDB();

// Load funcionario for edit
$editId = intval($_GET['edit'] ?? 0);
$editFunc = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id=?");
    $stmt->execute([$editId]);
    $editFunc = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Load equipes
$equipes = $db->query("SELECT id, nome, lider, gerente FROM equipes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Unique lideres and gerentes from equipes
$lideres  = array_unique(array_column($equipes, 'lider'));
$gerentes = array_unique(array_column($equipes, 'gerente'));
sort($lideres);
sort($gerentes);

// All funcionarios with equipe name
$filtroStatus = $_GET['status'] ?? '';
$filtroEq     = intval($_GET['equipe'] ?? 0);

$sql = "SELECT f.*, e.nome as equipe_nome FROM funcionarios f LEFT JOIN equipes e ON e.id = f.equipe_id WHERE 1=1";
$params = [];
if ($filtroStatus) { $sql .= " AND f.status=?"; $params[] = $filtroStatus; }
if ($filtroEq)     { $sql .= " AND f.equipe_id=?"; $params[] = $filtroEq; }
$sql .= " ORDER BY e.nome, f.nome";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Options
$nivelEY  = ['Staff 1', 'Staff 2', 'Staff 3', 'Senior 3', 'Senior 4', 'Senior 5', 'Senior 6', 'Senior 7', 'Gerente', 'Analista'];
$nivelBB  = ['Junior', 'Pleno', 'Senior'];
$funcaoEY = ['Auditoria', 'Consultoria', 'Fiscal', 'Trabalhista', 'TAS', 'Advisory', 'Jurídico', 'Tecnologia', 'Outro'];

renderHeader('Funcionários', 'funcionarios');
?>

<div class="page-header">
    <h2><?= $editFunc ? 'Editar Funcionário' : 'Cadastro de Funcionário' ?></h2>
    <?php if (!$editFunc): ?>
    <button class="btn btn-primary" onclick="document.getElementById('form-section').scrollIntoView({behavior:'smooth'})">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Novo Funcionário
    </button>
    <?php endif; ?>
</div>

<!-- FORM SECTION -->
<div class="form-section" id="form-section">
    <div class="form-section-title"><?= $editFunc ? '✎ Editando: ' . htmlspecialchars($editFunc['nome']) : '+ Novo Funcionário' ?></div>
    <form method="POST" action="actions.php">
        <input type="hidden" name="action" value="save_funcionario">
        <?php if ($editFunc): ?>
        <input type="hidden" name="id" value="<?= $editFunc['id'] ?>">
        <?php endif; ?>

        <!-- Identificação -->
        <div style="margin-bottom:20px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:14px">Identificação</div>
            <div class="form-grid">
                <div class="form-group" style="grid-column: span 2">
                    <label class="form-label">Nome Completo *</label>
                    <input class="form-control" type="text" name="nome" value="<?= htmlspecialchars($editFunc['nome'] ?? '') ?>" placeholder="Nome completo do funcionário" required>
                </div>
                <div class="form-group">
                    <label class="form-label">GUI (Nº Contrato)</label>
                    <input class="form-control" type="text" name="gui" value="<?= htmlspecialchars($editFunc['gui'] ?? '') ?>" placeholder="Ex: 12345">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value="Ativo" <?= ($editFunc['status'] ?? 'Ativo') === 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="Inativo" <?= ($editFunc['status'] ?? '') === 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">alocado</label>
                    <select class="form-control" name="alocado">
                        <option value="Não" <?= ($editFunc['alocado'] ?? 'Não') === 'Não' ? 'selected' : '' ?>>Não</option>
                        <option value="Sim" <?= ($editFunc['alocado'] ?? '') === 'Sim' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Vinculação -->
        <div style="border-top:1px solid var(--border);padding-top:20px;margin-bottom:20px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:14px">Vinculação</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Equipe</label>
                    <select class="form-control" name="equipe_id" id="sel-equipe" onchange="autoFillLiderGerente(this)">
                        <option value="">— Selecione —</option>
                        <?php foreach ($equipes as $eq): ?>
                        <option value="<?= $eq['id'] ?>"
                            data-lider="<?= htmlspecialchars($eq['lider']) ?>"
                            data-gerente="<?= htmlspecialchars($eq['gerente']) ?>"
                            <?= ($editFunc['equipe_id'] ?? '') == $eq['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($eq['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Líder</label>
                    <input class="form-control" type="text" name="lider" id="inp-lider"
                           value="<?= htmlspecialchars($editFunc['lider'] ?? '') ?>"
                           placeholder="Preenchido automaticamente pela equipe">
                </div>
                <div class="form-group">
                    <label class="form-label">Gerente</label>
                    <input class="form-control" type="text" name="gerente" id="inp-gerente"
                           value="<?= htmlspecialchars($editFunc['gerente'] ?? '') ?>"
                           placeholder="Preenchido automaticamente pela equipe">
                </div>
            </div>
        </div>

        <!-- EY -->
        <div style="border-top:1px solid var(--border);padding-top:20px;margin-bottom:20px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:14px">EY</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Função na EY</label>
                    <input class="form-control" type="text" name="funcao_ey" value="<?= htmlspecialchars($editFunc['funcao_ey'] ?? '') ?>" list="funcao-ey-list" placeholder="Ex: Auditoria">
                    <datalist id="funcao-ey-list">
                        <?php foreach ($funcaoEY as $f): ?>
                        <option value="<?= htmlspecialchars($f) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label">Nível EY</label>
                    <select class="form-control" name="nivel_ey">
                        <option value="">— Selecione —</option>
                        <?php foreach ($nivelEY as $n): ?>
                        <option value="<?= $n ?>" <?= ($editFunc['nivel_ey'] ?? '') === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Início EY</label>
                    <input class="form-control" type="date" name="inicio_ey" value="<?= $editFunc['inicio_ey'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Fim EY</label>
                    <input class="form-control" type="date" name="fim_ey" value="<?= $editFunc['fim_ey'] ?? '' ?>">
                </div>
            </div>
        </div>

        <!-- BB -->
        <div style="border-top:1px solid var(--border);padding-top:20px;margin-bottom:20px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--info);margin-bottom:14px">BB (Banco do Brasil)</div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Função no BB</label>
                    <input class="form-control" type="text" name="funcao_bb" value="<?= htmlspecialchars($editFunc['funcao_bb'] ?? '') ?>" placeholder="Função no BB">
                </div>
                <div class="form-group">
                    <label class="form-label">Nível no BB</label>
                    <select class="form-control" name="nivel_bb">
                        <option value="">— Selecione —</option>
                        <?php foreach ($nivelBB as $n): ?>
                        <option value="<?= $n ?>" <?= ($editFunc['nivel_bb'] ?? '') === $n ? 'selected' : '' ?>><?= $n ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Início BB</label>
                    <input class="form-control" type="date" name="inicio_bb" value="<?= $editFunc['inicio_bb'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Fim BB</label>
                    <input class="form-control" type="date" name="fim_bb" value="<?= $editFunc['fim_bb'] ?? '' ?>">
                </div>
            </div>
        </div>

        <!-- Extras -->
        <div style="border-top:1px solid var(--border);padding-top:20px;margin-bottom:24px">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:14px">Complemento</div>
            <div class="form-group">
                <label class="form-label">Motivo</label>
                <textarea class="form-control" name="motivo" rows="2" placeholder="Motivo de desligamento, desalocação, etc."><?= htmlspecialchars($editFunc['motivo'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $editFunc ? 'Atualizar' : 'Salvar Funcionário' ?>
            </button>
            <?php if ($editFunc): ?>
            <a href="funcionarios.php" class="btn btn-ghost">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- FILTERS -->
<div class="search-bar">
    <input class="form-control search-input" id="fSearch" placeholder="Buscar funcionário..." oninput="filterTable('fSearch','fTable')">
    <a href="funcionarios.php<?= $filtroStatus !== 'Ativo' ? '?status=Ativo' : '' ?>" class="pill <?= $filtroStatus === 'Ativo' ? 'active' : '' ?>">Ativos</a>
    <a href="funcionarios.php<?= $filtroStatus !== 'Inativo' ? '?status=Inativo' : '' ?>" class="pill <?= $filtroStatus === 'Inativo' ? 'active' : '' ?>">Inativos</a>
    <a href="funcionarios.php" class="pill <?= !$filtroStatus ? 'active' : '' ?>">Todos</a>
    <?php if ($equipes): ?>
    <select class="form-control" style="width:auto" onchange="window.location='funcionarios.php?equipe='+this.value+'&status=<?= $filtroStatus ?>'">
        <option value="">Todas as Equipes</option>
        <?php foreach ($equipes as $eq): ?>
        <option value="<?= $eq['id'] ?>" <?= $filtroEq === $eq['id'] ? 'selected' : '' ?>><?= htmlspecialchars($eq['nome']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
</div>

<!-- TABLE -->
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title">Funcionários (<?= count($funcionarios) ?>)</div>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="fTable">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>GUI</th>
                    <th>Equipe</th>
                    <th>Nível EY</th>
                    <th>Nível BB</th>
                    <th>Início BB</th>
                    <th>Fim BB</th>
                    <th>Status</th>
                    <th>alocado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funcionarios)): ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <h3>Nenhum funcionário encontrado</h3>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($funcionarios as $f): ?>
                <tr>
                    <td style="color:var(--text);font-weight:500">
                        <?= htmlspecialchars($f['nome']) ?>
                        <?php if ($f['funcao_ey']): ?>
                        <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($f['funcao_ey']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['gui'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['equipe_nome'] ?? '—') ?></td>
                    <td>
                        <?php if ($f['nivel_ey']): ?>
                        <span class="badge badge-blue"><?= htmlspecialchars($f['nivel_ey']) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['nivel_bb']): ?>
                        <span class="badge badge-yellow"><?= htmlspecialchars($f['nivel_bb']) ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="color:var(--text3);font-size:12px">
                        <?= $f['inicio_bb'] ? date('d/m/Y', strtotime($f['inicio_bb'])) : '—' ?>
                    </td>
                    <td style="color:var(--text3);font-size:12px">
                        <?= $f['fim_bb'] ? date('d/m/Y', strtotime($f['fim_bb'])) : '—' ?>
                    </td>
                    <td>
                        <span class="badge <?= $f['status'] === 'Ativo' ? 'badge-green' : 'badge-red' ?>">
                            <?= $f['status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $f['alocado'] === 'Sim' ? 'badge-yellow' : 'badge-gray' ?>">
                            <?= $f['alocado'] ?>
                        </span>
                        <?php if ($f['data_desalocacao']): ?>
                        <div style="font-size:10px;color:var(--text3)"><?= date('d/m/Y', strtotime($f['data_desalocacao'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="funcionarios.php?edit=<?= $f['id'] ?>" class="btn btn-ghost btn-sm">✎</a>
                            <?php if ($f['alocado'] === 'Sim'): ?>
                            <button class="btn btn-warning btn-sm" onclick="modalDesalocar(<?= $f['id'] ?>)" title="Desalocar">⊖</button>
                            <?php endif; ?>
                            <?php if ($f['status'] === 'Ativo'): ?>
                            <button class="btn btn-danger btn-sm" onclick="modalDesligar(<?= $f['id'] ?>)" title="Desligar">⊗</button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('actions.php?action=delete_funcionario&id=<?= $f['id'] ?>', 'Excluir &quot;<?= htmlspecialchars(addslashes($f['nome'])) ?>&quot; permanentemente?')" title="Excluir">✕</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
function autoFillLiderGerente(sel) {
    const opt = sel.options[sel.selectedIndex];
    const lider   = opt.dataset.lider   || '';
    const gerente = opt.dataset.gerente || '';
    document.getElementById('inp-lider').value   = lider;
    document.getElementById('inp-gerente').value = gerente;
}
</script>

<?php renderFooter(); ?>
