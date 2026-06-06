<?php
session_start();
require_once("conexao.php");

if(!isset($_SESSION['cod_usuario'])){
    header("Location: login.php");
    exit;
}
$cod_usuario = $_SESSION['cod_usuario'];
$nomeUsuario = "";
$emailUsuario = "";
$sql = "SELECT * FROM usuario WHERE cod_usuario = '$cod_usuario'";
$result = mysqli_query($conexao_bd,$sql);

if($consulta = mysqli_fetch_assoc($result)){
    $nomeUsuario  = $consulta['nome'];
    $emailUsuario = $consulta['email'];
}

$operadorNome  = $nomeUsuario;
$operadorEmail = $emailUsuario;

/* ============================================================
   PROCESSAMENTO DE AÇÕES (POST)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    
    try {
        if ($acao === 'novo') {
            $paciente      = trim($_POST['paciente']);
            $medico_id     = (int)$_POST['medico_id'];
            $data          = $_POST['data'];
            $horario       = $_POST['horario'];
            $status        = $_POST['status'];

            // Busca a especialidade correta atrelada a este médico (Corrige o bug do ID 1)
            $sqlEsp = "SELECT especialidade_id FROM medicos WHERE id = $medico_id";
            $resEsp = mysqli_query($conexao_bd, $sqlEsp);
            $rowEsp = mysqli_fetch_assoc($resEsp);
            $especialidade_id = $rowEsp['especialidade_id'];

            $stmt = mysqli_prepare($conexao_bd, "INSERT INTO agendamentos (paciente, medico_id, especialidade_id, data, horario, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "siisss", $paciente, $medico_id, $especialidade_id, $data, $horario, $status);
            mysqli_stmt_execute($stmt);

        } elseif ($acao === 'editar') {
            $id_agenda     = (int)$_POST['id'];
            $paciente      = trim($_POST['paciente']);
            $medico_id     = (int)$_POST['medico_id'];
            $data          = $_POST['data'];
            $horario       = $_POST['horario'];
            $status        = $_POST['status'];

            // Busca a especialidade correta atrelada a este médico
            $sqlEsp = "SELECT especialidade_id FROM medicos WHERE id = $medico_id";
            $resEsp = mysqli_query($conexao_bd, $sqlEsp);
            $rowEsp = mysqli_fetch_assoc($resEsp);
            $especialidade_id = $rowEsp['especialidade_id'];

            $stmt = mysqli_prepare($conexao_bd, "UPDATE agendamentos SET paciente = ?, medico_id = ?, especialidade_id = ?, data = ?, horario = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "siisssi", $paciente, $medico_id, $especialidade_id, $data, $horario, $status, $id_agenda);
            mysqli_stmt_execute($stmt);

        } elseif ($acao === 'cancelar') {
            // Se vier via AJAX (seu script atual), a exclusão é feita, mas aqui mantemos o POST tradicional caso precise
            $id_agenda = (int)$_POST['id'];
            $stmt = mysqli_prepare($conexao_bd, "DELETE FROM agendamentos WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id_agenda);
            mysqli_stmt_execute($stmt);
            
            // Retorna JSON se for requisição AJAX
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['sucesso' => true]);
                exit;
            }
        }
    } catch (Exception $e) {
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
            exit;
        }
    }
}

/* ============================================================
   MÉDICOS DISPONÍVEIS (Puxando do Banco - Apenas Ativos)
============================================================ */
$medicos = [];
$sqlMedicos = "SELECT m.id, m.nome, m.especialidade_id, e.nome AS especialidade_nome 
               FROM medicos m 
               JOIN especialidades e ON m.especialidade_id = e.id 
               WHERE m.status = 'Ativo' 
               ORDER BY m.nome ASC";
$resMedicos = mysqli_query($conexao_bd, $sqlMedicos);
while ($row = mysqli_fetch_assoc($resMedicos)) {
    $medicos[] = $row;
}

/* ============================================================
   FILTROS E BUSCA (Refatorado para query no banco)
============================================================ */
$filtroPaciente = trim(isset($_GET['paciente']) ? $_GET['paciente'] : '');
$filtroMedico   = trim(isset($_GET['medico'])   ? $_GET['medico']   : '');
$filtroStatus   = trim(isset($_GET['status'])   ? $_GET['status']   : '');
$filtroDataIni  = trim(isset($_GET['data_ini']) ? $_GET['data_ini'] : '');
$filtroDataFim  = trim(isset($_GET['data_fim']) ? $_GET['data_fim'] : '');

$sqlAgendamentos = "SELECT a.id, a.data, a.horario, a.paciente, a.status, m.nome AS medico, e.nome AS especialidade, a.medico_id 
                    FROM agendamentos a 
                    JOIN medicos m ON a.medico_id = m.id 
                    JOIN especialidades e ON a.especialidade_id = e.id 
                    WHERE 1=1";

if ($filtroPaciente !== '') { $sqlAgendamentos .= " AND a.paciente LIKE '%" . mysqli_real_escape_string($conexao_bd, $filtroPaciente) . "%'"; }
if ($filtroMedico !== '')   { $sqlAgendamentos .= " AND m.nome = '" . mysqli_real_escape_string($conexao_bd, $filtroMedico) . "'"; }
if ($filtroStatus !== '')   { $sqlAgendamentos .= " AND a.status = '" . mysqli_real_escape_string($conexao_bd, $filtroStatus) . "'"; }
if ($filtroDataIni !== '')  { $sqlAgendamentos .= " AND a.data >= '" . mysqli_real_escape_string($conexao_bd, $filtroDataIni) . "'"; }
if ($filtroDataFim !== '')  { $sqlAgendamentos .= " AND a.data <= '" . mysqli_real_escape_string($conexao_bd, $filtroDataFim) . "'"; }

$sqlAgendamentos .= " ORDER BY a.data DESC, a.horario DESC";

$agendamentos = [];
$resultAg = mysqli_query($conexao_bd, $sqlAgendamentos);
while ($row = mysqli_fetch_assoc($resultAg)) {
    $agendamentos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Agendas</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root { --azul-primario: #0d6efd; --azul-escuro: #084298; --azul-claro: #e7f1ff; --cinza-fundo: #f5f7fa; --cinza-borda: #e3e6ea; --texto-escuro: #1f2d3d; --sidebar-larg: 250px; }
        body { background-color: var(--cinza-fundo); font-family: 'Segoe UI', Tahoma, sans-serif; color: var(--texto-escuro); overflow-x: hidden; }
        .navbar-topo { background: linear-gradient(90deg, var(--azul-primario) 0%, var(--azul-escuro) 100%); height: 60px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-topo .navbar-brand { color: #fff; font-weight: 600; font-size: 1.25rem; }
        .btn-sanduiche { background: transparent; border: none; color: #fff; font-size: 1.3rem; padding: 6px 12px; border-radius: 6px; transition: background 0.2s; }
        .operador-toggle { background: transparent; border: none; color: #fff; display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 30px; }
        .sidebar { position: fixed; top: 60px; left: 0; width: var(--sidebar-larg); height: calc(100vh - 60px); background: #fff; border-right: 1px solid var(--cinza-borda); padding: 20px 0; transition: transform 0.3s ease; z-index: 1020; overflow-y: auto; }
        .sidebar .nav-link { color: var(--texto-escuro); padding: 12px 20px; border-left: 3px solid transparent; display: flex; align-items: center; gap: 12px; }
        .sidebar .nav-link i { width: 22px; color: var(--azul-primario); font-size: 1.05rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.ativo { background: var(--azul-claro); border-left-color: var(--azul-primario); color: var(--azul-escuro); font-weight: 600; }
        .conteudo-principal { margin-top: 60px; margin-left: var(--sidebar-larg); padding: 25px; transition: margin-left 0.3s ease; min-height: calc(100vh - 60px); }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .page-header h2 { font-size: 1.4rem; font-weight: 700; color: var(--azul-escuro); margin: 0; display: flex; align-items: center; gap: 10px; }
        .card-pagina { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid var(--cinza-borda); padding: 20px 24px; margin-bottom: 20px; }
        .card-titulo { font-weight: 600; font-size: 0.95rem; color: var(--azul-escuro); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .tabela-agendamentos { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
        .tabela-agendamentos thead th { background: var(--azul-claro); color: var(--azul-escuro); font-weight: 600; padding: 10px 14px; border-bottom: 2px solid var(--cinza-borda); white-space: nowrap; }
        .tabela-agendamentos tbody td { padding: 10px 14px; border-bottom: 1px solid var(--cinza-borda); vertical-align: middle; }
        .tabela-agendamentos tbody tr:hover { background: #f8fbff; }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-confirmado { background: #d1e7dd; color: #0a3622; }
        .badge-pendente { background: #fff3cd; color: #664d03; }
        .badge-cancelado { background: #f8d7da; color: #58151c; }
        .modal-form .modal-header { background: var(--azul-primario); color: #fff; }
        .modal-form .modal-header .btn-close { filter: invert(1); }
        .modal-form label { font-weight: 500; font-size: 0.88rem; margin-bottom: 4px; }
    </style>
</head>
<body>

    <nav class="navbar-topo d-flex align-items-center justify-content-between px-3">
        <div class="d-flex align-items-center gap-2">
            <button class="btn-sanduiche" id="btnSanduiche"><i class="fa-solid fa-bars"></i></button>
            <a class="navbar-brand mb-0 d-flex align-items-center" href="principal.php">
                <i class="fa-solid fa-stethoscope me-2"></i><span>MediAgenda</span>
            </a>
        </div>
        <div class="dropdown">
            <button class="operador-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-circle-user"></i>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($operadorNome) ?></span>
                <i class="fa-solid fa-chevron-down" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-operador">
                <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2"></i><?php echo htmlspecialchars($operadorNome) ?></a></li>
                <li><a class="dropdown-item" href="#"><i class="fa-solid fa-envelope me-2"></i><?php echo htmlspecialchars($operadorEmail) ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sair</a></li>
            </ul>
        </div>
    </nav>

    <aside class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="principal.php"><i class="fa-solid fa-calendar-days"></i> Calendário</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_agendas.php"><i class="fa-solid fa-calendar-plus"></i> Agendamentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_especialidades.php"><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</a>
            </li>
        </ul>
    </aside>

    <main class="conteudo-principal" id="conteudoPrincipal">

        <div class="page-header">
            <h2><i class="fa-solid fa-calendar-days text-primary"></i> Cadastro de Agendas</h2>
            <button class="btn btn-primary" onclick="abrirModalNovo()">
                <i class="fa-solid fa-plus me-1"></i> Novo Agendamento
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass text-primary"></i> Filtros</div>
            <form method="GET" action="cadastro_agendas.php">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="filtroPaciente">Paciente</label>
                        <input type="text" class="form-control form-control-sm" name="paciente" placeholder="Nome do paciente" value="<?php echo htmlspecialchars($filtroPaciente) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filtroMedico">Médico</label>
                        <select class="form-select form-select-sm" name="medico">
                            <option value="">Todos</option>
                            <?php foreach ($medicos as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['nome']) ?>" <?php echo ($filtroMedico === $m['nome']) ? 'selected' : '' ?>>
                                    <?php echo htmlspecialchars($m['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtroStatus">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Todos</option>
                            <option value="Confirmado" <?php echo ($filtroStatus === 'Confirmado') ? 'selected' : '' ?>>Confirmado</option>
                            <option value="Pendente"   <?php echo ($filtroStatus === 'Pendente')   ? 'selected' : '' ?>>Pendente</option>
                            <option value="Cancelado"  <?php echo ($filtroStatus === 'Cancelado')  ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtroDataIni">Data inicial</label>
                        <input type="date" class="form-control form-control-sm" name="data_ini" value="<?php echo htmlspecialchars($filtroDataIni) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="filtroDataFim">Data final</label>
                        <input type="date" class="form-control form-control-sm" name="data_fim" value="<?php echo htmlspecialchars($filtroDataFim) ?>">
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar</button>
                    <a href="cadastro_agendas.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-xmark me-1"></i> Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list text-primary"></i> Agendamentos</span>
                <span class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($agendamentos) ?> registro(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-agendamentos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Especialidade</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agendamentos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4"><i class="fa-solid fa-calendar-xmark me-2"></i>Nenhum agendamento encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($agendamentos as $ag):
                                $dataFormatada = date('d/m/Y', strtotime($ag['data']));
                                $horaFormatada = date('H:i', strtotime($ag['horario']));
                                if ($ag['status'] === 'Confirmado') { $classeBadge = 'badge-confirmado'; }
                                elseif ($ag['status'] === 'Pendente') { $classeBadge = 'badge-pendente'; }
                                else { $classeBadge = 'badge-cancelado'; }
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $ag['id'] ?></td>
                                <td><?php echo $dataFormatada ?></td>
                                <td><?php echo $horaFormatada ?></td>
                                <td><strong><?php echo htmlspecialchars($ag['paciente']) ?></strong></td>
                                <td><?php echo htmlspecialchars($ag['medico']) ?></td>
                                <td><?php echo htmlspecialchars($ag['especialidade']) ?></td>
                                <td><span class="badge-status <?php echo $classeBadge ?>"><?php echo htmlspecialchars($ag['status']) ?></span></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" title="Editar"
                                        onclick="abrirModalEditar(
                                            <?php echo $ag['id']; ?>, 
                                            '<?php echo htmlspecialchars(addslashes($ag['paciente'])); ?>',
                                            <?php echo $ag['medico_id']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($ag['especialidade'])); ?>',
                                            '<?php echo $ag['data']; ?>',
                                            '<?php echo $horaFormatada; ?>',
                                            '<?php echo htmlspecialchars(addslashes($ag['status'])); ?>'
                                        )">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Cancelar Agendamento"
                                        onclick="confirmarExclusao(<?php echo $ag['id']; ?>, '<?php echo htmlspecialchars(addslashes($ag['paciente'])); ?>')">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div class="modal fade modal-form" id="modalFormAgenda" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo"><i class="fa-solid fa-calendar-plus me-2"></i>Novo Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="cadastro_agendas.php" method="POST"> 
                    <div class="modal-body">
                        <input type="hidden" name="acao" id="formAcao" value="novo">
                        <input type="hidden" name="id" id="formId" value="">

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="formPaciente">Paciente <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formPaciente" name="paciente" placeholder="Nome completo do paciente" required>
                            </div>
                            <div class="col-md-6">
                                <label for="formMedico">Médico <span class="text-danger">*</span></label>
                                <select class="form-select" id="formMedico" name="medico_id" required onchange="preencherEspecialidade()">
                                    <option value="">Selecione o Médico...</option>
                                    <?php foreach ($medicos as $m): ?>
                                        <option value="<?php echo $m['id'] ?>" data-esp-nome="<?php echo htmlspecialchars($m['especialidade_nome']) ?>">
                                            <?php echo htmlspecialchars($m['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="formEspecialidade">Especialidade</label>
                                <input type="text" class="form-control bg-light" id="formEspecialidade" placeholder="Auto-preenchido" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="formData">Data <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="formData" name="data" required>
                            </div>
                            <div class="col-md-6">
                                <label for="formHorario">Horário <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="formHorario" name="horario" required>
                            </div>
                            <div class="col-12">
                                <label for="formStatus">Status</label>
                                <select class="form-select" id="formStatus" name="status">
                                    <option value="Pendente">Pendente</option>
                                    <option value="Confirmado">Confirmado</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        var modalForm = new bootstrap.Modal(document.getElementById('modalFormAgenda'));

        function abrirModalNovo() {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-calendar-plus me-2"></i>Novo Agendamento';
            document.getElementById('formAcao').value = 'novo';
            document.getElementById('formId').value = '';
            document.getElementById('formPaciente').value = '';
            document.getElementById('formMedico').value = '';
            document.getElementById('formEspecialidade').value = '';
            document.getElementById('formData').value = '';
            document.getElementById('formHorario').value = '';
            document.getElementById('formStatus').value = 'Pendente';
            modalForm.show();
        }

        function abrirModalEditar(id, paciente, medico_id, especialidade, data, horario, status) {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-pen me-2"></i>Editar Agendamento';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = id;
            document.getElementById('formPaciente').value = paciente;
            document.getElementById('formMedico').value = medico_id;
            document.getElementById('formEspecialidade').value = especialidade;
            document.getElementById('formData').value = data;
            document.getElementById('formHorario').value = horario;
            document.getElementById('formStatus').value = status;
            modalForm.show();
        }

        // BÔNUS: Função que preenche a especialidade automaticamente (Resolve o TODO do professor)
        function preencherEspecialidade() {
            var selectMedico = document.getElementById('formMedico');
            var optionSelecionada = selectMedico.options[selectMedico.selectedIndex];
            var especialidade = optionSelecionada.getAttribute('data-esp-nome');
            
            if(especialidade) {
                document.getElementById('formEspecialidade').value = especialidade;
            } else {
                document.getElementById('formEspecialidade').value = '';
            }
        }

        function confirmarExclusao(id, paciente) {
            Swal.fire({
                title: 'Cancelar agendamento?',
                html: 'Deseja excluir a consulta de <strong>' + paciente + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, cancelar',
                cancelButtonText: 'Voltar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "cadastro_agendas.php",
                        type: "POST",
                        data: { id: id, acao: "cancelar" },
                        success: function() {
                            window.location.href = "cadastro_agendas.php";
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>