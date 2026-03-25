<?php
require_once 'includes/db.php';
require_once 'includes/layout.php';

$db = getDB();

// Load equipe for edit
$editId = intval($_GET['edit'] ?? 0);
$editEquipe = null;
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM equipes WHERE id=?");
    $stmt->execute([$editId]);
    $editEquipe = $stmt->fetch(PDO::FETCH_ASSOC);
}

// All equipes
$equipes = $db->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM funcionarios f WHERE f.equipe_id = e.id AND f.status='Ativo') as total_ativos
    FROM equipes e ORDER BY e.nome
")->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Equipes', 'equipes');
?>

<div class="page-header">
    <h2><?= $editEquipe ? 'Editar Equipe' : 'Cadastro de Equipes' ?></h2>
</div>

<!-- FORM -->
<div class="form-section">
    <div class="form-section-title"><?= $editEquipe ? '✎ Editando Equipe' : '+ Nova Equipe' ?></div>
    <form method="POST" action="actions.php">
        <input type="hidden" name="action" value="save_equipe">
        <?php if ($editEquipe): ?>
        <input type="hidden" name="id" value="<?= $editEquipe['id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Nome da Equipe *</label>
                <input class="form-control" type="text" name="nome" value="<?= htmlspecialchars($editEquipe['nome'] ?? '') ?>" placeholder="Ex: Equipe Alpha" required>
            </div>
            <div class="form-group">
                <label class="form-label">Líder *</label>
                <input class="form-control" type="text" name="lider" value="<?= htmlspecialchars($editEquipe['lider'] ?? '') ?>" placeholder="Nome do líder" required>
            </div>
            <div class="form-group">
                <label class="form-label">Gerente *</label>
                <input class="form-control" type="text" name="gerente" value="<?= htmlspecialchars($editEquipe['gerente'] ?? '') ?>" placeholder="Nome do gerente" required>
            </div>
        </div>
        <div class="form-actions" style="margin-top:20px">
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <?= $editEquipe ? 'Atualizar Equipe' : 'Salvar Equipe' ?>
            </button>
            <?php if ($editEquipe): ?>
            <a href="equipes.php" class="btn btn-ghost">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- TABLE -->
<div class="table-card">
    <div class="table-card-header">
        <div class="table-card-title">Equipes Cadastradas (<?= count($equipes) ?>)</div>
        <input class="form-control" id="eqSearch" placeholder="Buscar..." style="width:200px" oninput="filterTable('eqSearch','eqTable')">
    </div>
    <div class="table-wrap">
        <table class="data-table" id="eqTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome da Equipe</th>
                    <th>Líder</th>
                    <th>Gerente</th>
                    <th>Ativos</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipes)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <h3>Nenhuma equipe cadastrada</h3>
                            <p>Use o formulário acima para adicionar equipes.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($equipes as $eq): ?>
                <tr>
                    <td style="color:var(--text3)"><?= $eq['id'] ?></td>
                    <td style="color:var(--text);font-weight:600"><?= htmlspecialchars($eq['nome']) ?></td>
                    <td><?= htmlspecialchars($eq['lider']) ?></td>
                    <td><?= htmlspecialchars($eq['gerente']) ?></td>
                    <td>
                        <span class="badge badge-<?= $eq['total_ativos'] > 0 ? 'green' : 'gray' ?>">
                            <?= $eq['total_ativos'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="equipes.php?edit=<?= $eq['id'] ?>" class="btn btn-ghost btn-sm">✎ Editar</a>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('actions.php?action=delete_equipe&id=<?= $eq['id'] ?>', 'Excluir a equipe &quot;<?= htmlspecialchars(addslashes($eq['nome'])) ?>&quot;?')">✕ Excluir</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderFooter(); ?>
