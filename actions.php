<?php
require_once 'includes/db.php';

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';

function redirect($url, $msg = '', $type = 'success', $isError = false) {
    $sep = strpos($url, '?') !== false ? '&' : '?';
    if ($msg) {
        $key = $isError ? 'error' : 'msg';
        $url .= $sep . $key . '=' . urlencode($msg) . '&type=' . $type;
    }
    header('Location: ' . $url);
    exit;
}

try {
    switch ($action) {

        // ── SETTINGS ──────────────────────────────────────────────────
        case 'update_meta':
            $key = $_POST['key'];
            $value = intval($_POST['value']);
            $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
               ->execute([$key, $value]);
            redirect('index.php', 'Meta atualizada com sucesso!');
            break;

        // ── EQUIPES ───────────────────────────────────────────────────
        case 'save_equipe':
            $id = $_POST['id'] ?? null;
            $nome = trim($_POST['nome']);
            $lider = trim($_POST['lider']);
            $gerente = trim($_POST['gerente']);

            if (empty($nome) || empty($lider) || empty($gerente)) {
                redirect('equipes.php', 'Preencha todos os campos!', 'error', true);
            }

            if ($id) {
                $db->prepare("UPDATE equipes SET nome=?, lider=?, gerente=? WHERE id=?")
                   ->execute([$nome, $lider, $gerente, $id]);
                $msg = "Equipe atualizada!";
            } else {
                $db->prepare("INSERT INTO equipes (nome, lider, gerente) VALUES (?, ?, ?)")
                   ->execute([$nome, $lider, $gerente]);
                $msg = "Equipe cadastrada!";
            }
            redirect('equipes.php', $msg);
            break;

        case 'delete_equipe':
            $id = intval($_GET['id'] ?? 0);
            // Check if equipe has funcionarios
            $count = $db->prepare("SELECT COUNT(*) FROM funcionarios WHERE equipe_id=?");
            $count->execute([$id]);
            if ($count->fetchColumn() > 0) {
                redirect('equipes.php', 'Não é possível excluir equipe com funcionários!', 'error', true);
            }
            $db->prepare("DELETE FROM equipes WHERE id=?")->execute([$id]);
            redirect('equipes.php', 'Equipe excluída!');
            break;

        // ── FUNCIONARIOS ──────────────────────────────────────────────
        case 'save_funcionario':
            $id = $_POST['id'] ?? null;
            $data = [
                'nome'            => trim($_POST['nome'] ?? ''),
                'gui'             => trim($_POST['gui'] ?? ''),
                'equipe_id'       => $_POST['equipe_id'] ?: null,
                'lider'           => trim($_POST['lider'] ?? ''),
                'gerente'         => trim($_POST['gerente'] ?? ''),
                'funcao_ey'       => trim($_POST['funcao_ey'] ?? ''),
                'nivel_ey'        => trim($_POST['nivel_ey'] ?? ''),
                'funcao_bb'       => trim($_POST['funcao_bb'] ?? ''),
                'nivel_bb'        => trim($_POST['nivel_bb'] ?? ''),
                'inicio_ey'       => $_POST['inicio_ey'] ?: null,
                'inicio_bb'       => $_POST['inicio_bb'] ?: null,
                'fim_bb'          => $_POST['fim_bb'] ?: null,
                'fim_ey'          => $_POST['fim_ey'] ?: null,
                'motivo'          => trim($_POST['motivo'] ?? ''),
                'status'          => $_POST['status'] ?? 'Ativo',
                'desalocado'      => $_POST['desalocado'] ?? 'Não',
            ];

            if (empty($data['nome'])) {
                redirect('funcionarios.php', 'Nome é obrigatório!', 'error', true);
            }

            if ($id) {
                $sql = "UPDATE funcionarios SET nome=:nome, gui=:gui, equipe_id=:equipe_id, lider=:lider, gerente=:gerente,
                        funcao_ey=:funcao_ey, nivel_ey=:nivel_ey, funcao_bb=:funcao_bb, nivel_bb=:nivel_bb,
                        inicio_ey=:inicio_ey, inicio_bb=:inicio_bb, fim_bb=:fim_bb, fim_ey=:fim_ey,
                        motivo=:motivo, status=:status, desalocado=:desalocado WHERE id=:id";
                $data['id'] = $id;
                $db->prepare($sql)->execute($data);
                $msg = "Funcionário atualizado!";
            } else {
                $sql = "INSERT INTO funcionarios (nome, gui, equipe_id, lider, gerente, funcao_ey, nivel_ey,
                        funcao_bb, nivel_bb, inicio_ey, inicio_bb, fim_bb, fim_ey, motivo, status, desalocado)
                        VALUES (:nome, :gui, :equipe_id, :lider, :gerente, :funcao_ey, :nivel_ey,
                        :funcao_bb, :nivel_bb, :inicio_ey, :inicio_bb, :fim_bb, :fim_ey, :motivo, :status, :desalocado)";
                $db->prepare($sql)->execute($data);
                $msg = "Funcionário cadastrado!";
            }
            redirect('funcionarios.php', $msg);
            break;

        case 'delete_funcionario':
            $id = intval($_GET['id'] ?? 0);
            $db->prepare("DELETE FROM funcionarios WHERE id=?")->execute([$id]);
            redirect('funcionarios.php', 'Funcionário excluído!');
            break;

        case 'desalocar':
            $id = intval($_POST['id']);
            $data = $_POST['data'];
            $db->prepare("UPDATE funcionarios SET desalocado='Sim', data_desalocacao=? WHERE id=?")
               ->execute([$data, $id]);
            redirect('funcionarios.php', 'Funcionário desalocado!');
            break;

        case 'desligar':
            $id = intval($_POST['id']);
            $data = $_POST['data'];
            $motivo = trim($_POST['motivo'] ?? '');
            $db->prepare("UPDATE funcionarios SET status='Inativo', data_desligamento=?, motivo=?, fim_ey=? WHERE id=?")
               ->execute([$data, $motivo, $data, $id]);
            redirect('funcionarios.php', 'Funcionário desligado!');
            break;

        default:
            redirect('index.php');
    }
} catch (Exception $e) {
    redirect($redirect, 'Erro: ' . $e->getMessage(), 'error', true);
}
