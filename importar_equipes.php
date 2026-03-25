<?php
require_once 'includes/db.php';
require_once 'includes/layout.php';

$db = getDB();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xlsx'])) {
    $file = $_FILES['xlsx']['tmp_name'];
    $ext  = strtolower(pathinfo($_FILES['xlsx']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx', 'csv'])) {
        $result = ['error' => 'Formato inválido. Envie .xlsx ou .csv'];
    } else {
        try {
            $rows = [];

            if ($ext === 'csv') {
                $handle = fopen($file, 'r');
                $headers = fgetcsv($handle, 0, ';');
                if (!$headers) $headers = [];
                $headers = array_map('trim', $headers);
                while (($row = fgetcsv($handle, 0, ';')) !== false) {
                    if (count($row) === count($headers)) {
                        $rows[] = array_combine($headers, $row);
                    }
                }
                fclose($handle);
            } else {
                // Read xlsx using ZIP + XML (Same logic as importar.php)
                $zip = new ZipArchive();
                if ($zip->open($file) !== true) throw new Exception('Não foi possível abrir o arquivo xlsx.');

                $sharedStrings = [];
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($ssXml) {
                    $ssDoc = new SimpleXMLElement($ssXml);
                    foreach ($ssDoc->si as $si) {
                        $text = '';
                        if (isset($si->t)) { $text = (string)$si->t; }
                        else { foreach ($si->r as $r) { if (isset($r->t)) $text .= (string)$r->t; } }
                        $sharedStrings[] = $text;
                    }
                }

                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if (!$sheetXml) throw new Exception('Não foi possível ler a planilha.');

                $sheetDoc = new SimpleXMLElement($sheetXml);
                $grid = [];
                foreach ($sheetDoc->sheetData->row as $row) {
                    $rowIdx = (int)$row['r'] - 1;
                    foreach ($row->c as $cell) {
                        $ref = (string)$cell['r'];
                        preg_match('/([A-Z]+)(\d+)/', $ref, $m);
                        $col = 0;
                        foreach (str_split($m[1]) as $ch) $col = $col * 26 + (ord($ch) - 64);
                        $col--;

                        $t = (string)$cell['t'];
                        $v = isset($cell->v) ? (string)$cell->v : '';
                        $val = ($t === 's') ? ($sharedStrings[(int)$v] ?? '') : $v;
                        $grid[$rowIdx][$col] = $val;
                    }
                }
                $zip->close();

                if (empty($grid)) throw new Exception('Planilha vazia.');

                $headers = $grid[0] ?? [];
                $maxCol  = max(array_keys($headers) ?: [0]);
                for ($i = 0; $i <= $maxCol; $i++) if (!isset($headers[$i])) $headers[$i] = "col_$i";

                for ($i = 1; isset($grid[$i]); $i++) {
                    $rowArr = [];
                    foreach ($headers as $ci => $hdr) $rowArr[trim($hdr)] = trim($grid[$i][$ci] ?? '');
                    if (array_filter($rowArr)) $rows[] = $rowArr;
                }
            }

            // Map columns
            $colMap = [
                'nome'    => ['nome equipe', 'nome da equipe', 'equipe', 'team'],
                'lider'   => ['lider', 'líder', 'lider equipe', 'líder equipe'],
                'gerente' => ['gerente', 'gerente ey', 'gerente bb'],
            ];

            function findCol($headers, $candidates) {
                foreach ($candidates as $c) {
                    foreach ($headers as $h) {
                        if (mb_strtolower(trim($h)) === mb_strtolower($c)) return $h;
                    }
                }
                return null;
            }

            $headerKeys = array_keys($rows[0] ?? []);
            $mapped = [];
            foreach ($colMap as $field => $candidates) {
                $mapped[$field] = findCol($headerKeys, $candidates);
            }

            if (!$mapped['nome']) throw new Exception('Coluna "Nome Equipe" não encontrada no arquivo.');

            $imported = 0; $updated = 0; $skipped = 0;

            $db->beginTransaction();
            foreach ($rows as $row) {
                $nome = trim($row[$mapped['nome']] ?? '');
                if (!$nome) { $skipped++; continue; }

                $lider   = $mapped['lider']   ? trim($row[$mapped['lider']]   ?? '') : '';
                $gerente = $mapped['gerente'] ? trim($row[$mapped['gerente']] ?? '') : '';

                // Check if exists
                $stmt = $db->prepare("SELECT id FROM equipes WHERE LOWER(nome) = LOWER(?)");
                $stmt->execute([$nome]);
                $id = $stmt->fetchColumn();

                if ($id) {
                    $upd = $db->prepare("UPDATE equipes SET lider=?, gerente=? WHERE id=?");
                    $upd->execute([$lider, $gerente, $id]);
                    $updated++;
                } else {
                    $ins = $db->prepare("INSERT INTO equipes (nome, lider, gerente) VALUES (?,?,?)");
                    $ins->execute([$nome, $lider, $gerente]);
                    $imported++;
                }
            }
            $db->commit();

            $result = ['success' => true, 'imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $result = ['error' => $e->getMessage()];
        }
    }
}

renderHeader('Importar Equipes', 'equipes');
?>

<div class="page-header">
    <h2>Importar Equipes via Excel / CSV</h2>
    <div style="display:flex; gap:10px">
        <a href="importar.php" class="btn btn-ghost">👤 Importar Funcionários</a>
        <a href="equipes.php" class="btn btn-ghost">← Voltar</a>
    </div>
</div>

<?php if ($result): ?>
<div class="form-section" style="border-color: <?= isset($result['error']) ? 'var(--danger)' : 'var(--success)' ?>20; margin-bottom:20px">
    <?php if (isset($result['error'])): ?>
    <div style="color:var(--danger); display:flex; gap:10px; align-items:center">
        <span style="font-size:20px">⚠</span>
        <div>
            <div style="font-weight:600">Erro na importação</div>
            <div style="color:var(--text2); margin-top:4px"><?= htmlspecialchars($result['error']) ?></div>
        </div>
    </div>
    <?php else: ?>
    <div style="color:var(--success); display:flex; gap:10px; align-items:center">
        <span style="font-size:20px">✓</span>
        <div>
            <div style="font-weight:600">Importação concluída!</div>
            <div style="color:var(--text2); margin-top:4px">
                <strong style="color:var(--success)"><?= $result['imported'] ?></strong> novas equipes criadas.
                <br>
                <strong style="color:var(--info)"><?= $result['updated'] ?></strong> equipes atualizadas.
                <?php if ($result['skipped']): ?>
                <br><strong style="color:var(--warning)"><?= $result['skipped'] ?></strong> linhas ignoradas.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="form-section">
    <div class="form-section-title">📂 Upload do Arquivo (Equipes)</div>
    <form method="POST" enctype="multipart/form-data">
        <div style="display:flex; flex-direction:column; gap:20px">
            <div id="drop-zone" style="
                border: 2px dashed var(--border2);
                border-radius: var(--radius-lg);
                padding: 40px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s;
            " onclick="document.getElementById('file-input').click()"
               ondragover="event.preventDefault(); this.style.borderColor='var(--accent)'"
               ondragleave="this.style.borderColor='var(--border2)'"
               ondrop="handleDrop(event)">
                <div style="font-size:36px; margin-bottom:12px">👥</div>
                <div style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; color:var(--text); margin-bottom:6px">
                    Arraste o arquivo de equipes aqui ou clique para selecionar
                </div>
                <div style="color:var(--text3); font-size:12px">Suporta .xlsx e .csv</div>
                <div id="file-name" style="margin-top:12px; color:var(--accent); font-weight:600; font-size:13px"></div>
            </div>

            <input type="file" id="file-input" name="xlsx" accept=".xlsx,.csv" style="display:none" onchange="showFileName(this)">

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" id="import-btn" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Importar Equipes
                </button>
            </div>
        </div>
    </form>
</div>

<div class="form-section">
    <div class="form-section-title">📋 Colunas esperadas</div>
    <p style="color:var(--text2); font-size:13px; margin-bottom:16px">
        Modelagem recomendada:
    </p>
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap:8px">
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:10px 12px">
            <div style="font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:var(--accent)">Nome Equipe</div>
            <div style="color:var(--text3); font-size:11px">Nome identificador da equipe (obrigatório)</div>
        </div>
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:10px 12px">
            <div style="font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:var(--accent)">Lider</div>
            <div style="color:var(--text3); font-size:11px">Nome do líder da equipe</div>
        </div>
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:10px 12px">
            <div style="font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:var(--accent)">Gerente</div>
            <div style="color:var(--text3); font-size:11px">Nome do gerente responsável</div>
        </div>
    </div>
</div>

<script>
function showFileName(input) {
    const name = input.files[0]?.name || '';
    document.getElementById('file-name').textContent = name ? '✓ ' + name : '';
    document.getElementById('import-btn').disabled = !name;
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').style.borderColor = 'var(--border2)';
    const file = e.dataTransfer.files[0];
    if (file) {
        const input = document.getElementById('file-input');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showFileName(input);
    }
}
</script>

<?php renderFooter(); ?>
