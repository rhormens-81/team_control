<?php
require_once 'includes/db.php';
require_once 'includes/layout.php';

$db = getDB();
$equipes = $db->query("SELECT id, nome FROM equipes ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$equipesMap = [];
foreach ($equipes as $e) $equipesMap[mb_strtolower(trim($e['nome']))] = $e['id'];

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
                // Read xlsx without external library using ZIP + XML
                $zip = new ZipArchive();
                if ($zip->open($file) !== true) throw new Exception('Não foi possível abrir o arquivo xlsx.');

                // Read shared strings
                $sharedStrings = [];
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($ssXml) {
                    $ssDoc = new SimpleXMLElement($ssXml);
                    foreach ($ssDoc->si as $si) {
                        $text = '';
                        if (isset($si->t)) {
                            $text = (string)$si->t;
                        } else {
                            foreach ($si->r as $r) {
                                if (isset($r->t)) $text .= (string)$r->t;
                            }
                        }
                        $sharedStrings[] = $text;
                    }
                }

                // Read styles to identify date columns
                $dateStyleIdx = [];
                $stylesXml = $zip->getFromName('xl/styles.xml');
                if ($stylesXml) {
                    $stylesDoc = new SimpleXMLElement($stylesXml);
                    $dateFormats = [14,15,16,17,18,19,20,21,22,27,28,29,30,31,32,33,34,35,36,45,46,47,50,51,52,53,54,55,56,57,58];
                    if (isset($stylesDoc->cellXfs->xf)) {
                        foreach ($stylesDoc->cellXfs->xf as $xf) {
                            $numFmtId = (int)$xf['numFmtId'];
                            $dateStyleIdx[] = in_array($numFmtId, $dateFormats) || ($numFmtId >= 164);
                        }
                    }
                }

                // Read first sheet
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                if (!$sheetXml) {
                    // Try sheet index from workbook
                    $wbXml = $zip->getFromName('xl/workbook.xml');
                    if ($wbXml) {
                        $wbDoc = new SimpleXMLElement($wbXml);
                        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    }
                }

                if (!$sheetXml) throw new Exception('Não foi possível ler a planilha.');

                $sheetDoc = new SimpleXMLElement($sheetXml);
                $sheetDoc->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

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
                        $s = isset($cell['s']) ? (int)$cell['s'] : -1;
                        $v = isset($cell->v) ? (string)$cell->v : '';

                        if ($t === 's') {
                            $val = $sharedStrings[(int)$v] ?? '';
                        } elseif ($t === 'b') {
                            $val = $v ? 'TRUE' : 'FALSE';
                        } elseif ($v !== '' && $s >= 0 && isset($dateStyleIdx[$s]) && $dateStyleIdx[$s]) {
                            // Convert Excel serial date
                            $ts = ($v - 25569) * 86400;
                            $val = date('d/m/Y', $ts);
                        } else {
                            $val = $v;
                        }
                        $grid[$rowIdx][$col] = $val;
                    }
                }
                $zip->close();

                if (empty($grid)) throw new Exception('Planilha vazia.');

                // First row = headers
                $headers = $grid[0] ?? [];
                $maxCol  = max(array_keys($headers) ?: [0]);
                for ($i = 0; $i <= $maxCol; $i++) {
                    if (!isset($headers[$i])) $headers[$i] = "col_$i";
                }

                for ($i = 1; isset($grid[$i]); $i++) {
                    $rowArr = [];
                    foreach ($headers as $ci => $hdr) {
                        $rowArr[trim($hdr)] = trim($grid[$i][$ci] ?? '');
                    }
                    if (array_filter($rowArr)) $rows[] = $rowArr;
                }
            }

            // Map columns (flexible, case-insensitive)
            $colMap = [
                'nome'       => ['nome completo','nome','name'],
                'gui'        => ['gui'],
                'inicio_ey'  => ['data inicio ey','data início ey','inicio ey','início ey'],
                'inicio_bb'  => ['data inicio bb','data início bb','inicio bb','início bb'],
                'equipe'     => ['equipe','team'],
                'lider'      => ['lider equipe','lider','líder equipe','líder'],
                'gerente'    => ['gerente ey','gerente bb','gerente'],
                'funcao_ey'  => ['função ey','funcao ey','função na ey'],
                'nivel_ey'   => ['cargo','nivel ey','nível ey'],
                'funcao_bb'  => ['função no bb','funcao no bb','função bb'],
                'nivel_bb'   => ['nível bb','nivel bb'],
                'status'     => ['status'],
                'alocado'    => ['tipo recurso alocado','alocado','desalocado'],
                'data_desalocacao' => ['data desalocação','data desalocacao'],
                'motivo'     => ['motivo'],
                'email'      => ['e-mail','email'],
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

            // Parse date helper
            function parseDate($v) {
                if (!$v) return null;
                // dd/mm/yyyy
                if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
                    return "{$m[3]}-{$m[2]}-{$m[1]}";
                }
                // yyyy-mm-dd
                if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $v)) return $v;
                return null;
            }

            $imported = 0; $skipped = 0; $errors = [];

            $db->beginTransaction();
            foreach ($rows as $i => $row) {
                $nome = $mapped['nome'] ? trim($row[$mapped['nome']] ?? '') : '';
                if (!$nome) { $skipped++; continue; }

                // Resolve equipe_id
                $equipeNome = $mapped['equipe'] ? trim($row[$mapped['equipe']] ?? '') : '';
                $equipeId   = $equipesMap[mb_strtolower($equipeNome)] ?? null;

                // Auto-create equipe if not found
                if ($equipeNome && !$equipeId) {
                    $liderNome   = $mapped['lider']   ? trim($row[$mapped['lider']]   ?? '') : '';
                    $gerenteNome = $mapped['gerente'] ? trim($row[$mapped['gerente']] ?? '') : '';
                    $db->prepare("INSERT INTO equipes (nome, lider, gerente) VALUES (?,?,?)")
                       ->execute([$equipeNome, $liderNome, $gerenteNome]);
                    $equipeId = $db->lastInsertId();
                    $equipesMap[mb_strtolower($equipeNome)] = $equipeId;
                }

                $statusRaw = $mapped['status'] ? trim($row[$mapped['status']] ?? '') : 'Ativo';
                $status    = in_array($statusRaw, ['Ativo','Inativo']) ? $statusRaw : 'Ativo';

                $desalRaw = $mapped['alocado'] ? strtolower(trim($row[$mapped['alocado']] ?? '')) : '';
                $alocado = ($desalRaw === 'sim' || $desalRaw === 'alocado' || $desalRaw === 'não alocado') ? 'Sim' : 'Não';

                $data = [
                    'nome'           => $nome,
                    'gui'            => $mapped['gui']      ? trim($row[$mapped['gui']]      ?? '') : '',
                    'equipe_id'      => $equipeId,
                    'lider'          => $mapped['lider']    ? trim($row[$mapped['lider']]    ?? '') : '',
                    'gerente'        => $mapped['gerente']  ? trim($row[$mapped['gerente']]  ?? '') : '',
                    'funcao_ey'      => $mapped['funcao_ey']? trim($row[$mapped['funcao_ey']]?? '') : '',
                    'nivel_ey'       => $mapped['nivel_ey'] ? trim($row[$mapped['nivel_ey']] ?? '') : '',
                    'funcao_bb'      => $mapped['funcao_bb']? trim($row[$mapped['funcao_bb']]?? '') : '',
                    'nivel_bb'       => $mapped['nivel_bb'] ? trim($row[$mapped['nivel_bb']] ?? '') : '',
                    'inicio_ey'      => parseDate($mapped['inicio_ey'] ? $row[$mapped['inicio_ey']] ?? '' : ''),
                    'inicio_bb'      => parseDate($mapped['inicio_bb'] ? $row[$mapped['inicio_bb']] ?? '' : ''),
                    'fim_bb'         => null,
                    'fim_ey'         => null,
                    'motivo'         => $mapped['motivo']   ? trim($row[$mapped['motivo']]   ?? '') : '',
                    'status'         => $status,
                    'alocado'     => $alocado,
                    'data_desalocacao'=> parseDate($mapped['data_desalocacao'] ? $row[$mapped['data_desalocacao']] ?? '' : ''),
                ];

                // Check if GUI already exists (update) or insert
                $gui = $data['gui'];
                $existing = null;
                if ($gui) {
                    $chk = $db->prepare("SELECT id FROM funcionarios WHERE gui=?");
                    $chk->execute([$gui]);
                    $existing = $chk->fetchColumn();
                }

                if ($existing) {
                    $sql = "UPDATE funcionarios SET nome=:nome, equipe_id=:equipe_id, lider=:lider, gerente=:gerente,
                            funcao_ey=:funcao_ey, nivel_ey=:nivel_ey, funcao_bb=:funcao_bb, nivel_bb=:nivel_bb,
                            inicio_ey=:inicio_ey, inicio_bb=:inicio_bb, fim_bb=:fim_bb, fim_ey=:fim_ey,
                            motivo=:motivo, status=:status, alocado=:alocado, data_desalocacao=:data_desalocacao
                            WHERE id=:id";
                    $data['id'] = $existing;
                    unset($data['gui']);
                    $db->prepare($sql)->execute($data);
                } else {
                    $sql = "INSERT INTO funcionarios (nome, gui, equipe_id, lider, gerente, funcao_ey, nivel_ey,
                            funcao_bb, nivel_bb, inicio_ey, inicio_bb, fim_bb, fim_ey, motivo, status, alocado, data_desalocacao)
                            VALUES (:nome,:gui,:equipe_id,:lider,:gerente,:funcao_ey,:nivel_ey,
                            :funcao_bb,:nivel_bb,:inicio_ey,:inicio_bb,:fim_bb,:fim_ey,:motivo,:status,:alocado,:data_desalocacao)";
                    $db->prepare($sql)->execute($data);
                }
                $imported++;
            }
            $db->commit();

            $result = ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $result = ['error' => $e->getMessage()];
        }
    }
}

renderHeader('Importar Excel', 'funcionarios');
?>

<div class="page-header">
    <h2>Importar Funcionários via Excel / CSV</h2>
    <div style="display:flex; gap:10px">
        <a href="importar_equipes.php" class="btn btn-ghost">📂 Importar Equipes</a>
        <a href="funcionarios.php" class="btn btn-ghost">← Voltar</a>
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
                <strong style="color:var(--success)"><?= $result['imported'] ?></strong> registros importados/atualizados.
                <?php if ($result['skipped']): ?>
                <strong style="color:var(--warning)"><?= $result['skipped'] ?></strong> linhas ignoradas (sem nome).
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- UPLOAD FORM -->
<div class="form-section">
    <div class="form-section-title">📂 Upload do Arquivo</div>
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
                <div style="font-size:36px; margin-bottom:12px">📊</div>
                <div style="font-family:'Syne',sans-serif; font-size:15px; font-weight:700; color:var(--text); margin-bottom:6px">
                    Arraste o arquivo aqui ou clique para selecionar
                </div>
                <div style="color:var(--text3); font-size:12px">Suporta .xlsx e .csv</div>
                <div id="file-name" style="margin-top:12px; color:var(--accent); font-weight:600; font-size:13px"></div>
            </div>

            <input type="file" id="file-input" name="xlsx" accept=".xlsx,.csv" style="display:none" onchange="showFileName(this)">

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" id="import-btn" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Importar Agora
                </button>
            </div>
        </div>
    </form>
</div>

<!-- COLUMN REFERENCE -->
<div class="form-section">
    <div class="form-section-title">📋 Colunas reconhecidas automaticamente</div>
    <p style="color:var(--text2); font-size:13px; margin-bottom:16px">
        O sistema detecta as colunas pelo nome do cabeçalho (primeira linha). Não precisa estar em ordem exata.
        Funcionários com mesmo <strong style="color:var(--accent)">GUI</strong> serão atualizados automaticamente.
        Equipes não cadastradas serão criadas automaticamente.
    </p>
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap:8px">
        <?php
        $cols = [
            'Nome Completo'      => 'Nome do funcionário (obrigatório)',
            'GUI'                => 'Número de contrato — chave de atualização',
            'Data Inicio EY'     => 'Data de início na EY (dd/mm/aaaa)',
            'Data Inicio BB'     => 'Data de início no BB (dd/mm/aaaa)',
            'Equipe'             => 'Nome da equipe (criada se não existir)',
            'Lider Equipe'       => 'Nome do líder',
            'Gerente EY'         => 'Nome do gerente',
            'Função EY'          => 'Função na EY',
            'Cargo'              => 'Nível EY (Staff 1, Senior 3, etc.)',
            'Função no BB'       => 'Função no Banco do Brasil',
            'Nível BB'           => 'Junior / Pleno / Senior',
            'Status'             => 'Ativo ou Inativo',
            'Alocado'            => 'Sim ou Não',
            'Data Desalocação'   => 'Data de desalocação (se houver)',
            'Motivo'             => 'Motivo de desligamento/desalocação',
        ];
        foreach ($cols as $col => $desc): ?>
        <div style="background:var(--bg3); border:1px solid var(--border); border-radius:8px; padding:10px 12px; display:flex; flex-direction:column; gap:3px">
            <div style="font-family:'Syne',sans-serif; font-size:12px; font-weight:700; color:var(--accent)"><?= $col ?></div>
            <div style="color:var(--text3); font-size:11px"><?= $desc ?></div>
        </div>
        <?php endforeach; ?>
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
