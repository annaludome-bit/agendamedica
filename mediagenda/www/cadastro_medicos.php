<?php
session_start();
require_once("conexao.php");

// Verificação de segurança (Login)
if(!isset($_SESSION['cod_usuario'])){
    header("Location: login.php");
    exit;
}

// Resgatar dados do usuário logado
$cod_usuario = $_SESSION['cod_usuario'];
$nomeUsuario = "";
$emailUsuario = "";
$sqlUsuario = "SELECT * FROM usuario WHERE cod_usuario = '$cod_usuario'";
$resUsuario = mysqli_query($conexao_bd, $sqlUsuario);
if($consulta = mysqli_fetch_assoc($resUsuario)){
    $nomeUsuario  = $consulta['nome'];
    $emailUsuario = $consulta['email'];
}

$operadorNome  = $nomeUsuario;
$operadorEmail = $emailUsuario;

/* ============================================================
   PROCESSAMENTO DE AÇÕES (POST) - CREATE, UPDATE, DELETE (LÓGICO)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $crm  = isset($_POST['crm']) ? trim($_POST['crm']) : '';
    $especialidade_id = isset($_POST['especialidade']) ? (int)$_POST['especialidade'] : 0;
    $telefone = isset($_POST['telefone']) ? trim($_POST['telefone']) : '';
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $status   = isset($_POST['status']) ? $_POST['status'] : 'Ativo';

    try {
        if ($acao === 'novo' && !empty($nome) && !empty($crm) && $especialidade_id > 0) {
            // INSERT
            $stmt = mysqli_prepare($conexao_bd, "INSERT INTO medicos (nome, crm, especialidade_id, telefone, email, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssisss", $nome, $crm, $especialidade_id, $telefone, $email, $status);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Salvo!';
            $_SESSION['swal_text']  = 'Médico cadastrado com sucesso.';
            $_SESSION['swal_icon']  = 'success';

        } elseif ($acao === 'editar' && $id > 0 && !empty($nome)) {
            // UPDATE
            $stmt = mysqli_prepare($conexao_bd, "UPDATE medicos SET nome = ?, crm = ?, especialidade_id = ?, telefone = ?, email = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssisssi", $nome, $crm, $especialidade_id, $telefone, $email, $status, $id);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Atualizado!';
            $_SESSION['swal_text']  = 'Dados do médico atualizados.';
            $_SESSION['swal_icon']  = 'success';

        } elseif ($acao === 'excluir' && $id > 0) {
            // SOFT DELETE (Inativação)
            $stmt = mysqli_prepare($conexao_bd, "UPDATE medicos SET status = 'Inativo' WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Inativado!';
            $_SESSION['swal_text']  = 'O médico foi inativado no sistema.';
            $_SESSION['swal_icon']  = 'info';
        }
    } catch (mysqli_sql_exception $e) {
        $_SESSION['swal_title'] = 'Oops...';
        $_SESSION['swal_icon']  = 'error';
        if ($e->getCode() == 1062) {
            $_SESSION['swal_text'] = 'Já existe um médico cadastrado com este CRM.';
        } else {
            $_SESSION['swal_text'] = 'Erro no banco de dados: ' . $e->getMessage();
        }
    }
    
    header("Location: cadastro_medicos.php");
    exit;
}

/* ============================================================
   BUSCAR ESPECIALIDADES (Para popular os selects)
============================================================ */
$especialidades = [];
$resEsp = mysqli_query($conexao_bd, "SELECT id, nome FROM especialidades ORDER BY nome ASC");
while ($row = mysqli_fetch_assoc($resEsp)) {
    $especialidades[] = $row;
}

/* ============================================================
   FILTROS E BUSCA DOS MÉDICOS
============================================================ */
$filtroNome          = trim(isset($_GET['nome']) ? $_GET['nome'] : '');
$filtroEspecialidade = isset($_GET['especialidade']) ? (int)$_GET['especialidade'] : 0;
$filtroStatus        = trim(isset($_GET['status']) ? $_GET['status'] : '');

$sqlMedicos = "SELECT m.*, e.nome AS especialidade_nome 
               FROM medicos m 
               JOIN especialidades e ON m.especialidade_id = e.id 
               WHERE 1=1";
$params = [];
$types = "";

if ($filtroNome !== '') {
    $sqlMedicos .= " AND m.nome LIKE ?";
    $params[] = "%{$filtroNome}%";
    $types .= "s";
}
if ($filtroEspecialidade > 0) {
    $sqlMedicos .= " AND m.especialidade_id = ?";
    $params[] = $filtroEspecialidade;
    $types .= "i";
}
if ($filtroStatus !== '') {
    $sqlMedicos .= " AND m.status = ?";
    $params[] = $filtroStatus;
    $types .= "s";
}
$sqlMedicos .= " ORDER BY m.nome ASC";

$stmtMedicos = mysqli_prepare($conexao_bd, $sqlMedicos);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmtMedicos, $types, ...$params);
}
mysqli_stmt_execute($stmtMedicos);
$resultMedicos = mysqli_stmt_get_result($stmtMedicos);

$medicos = [];
while ($row = mysqli_fetch_assoc($resultMedicos)) {
    $medicos[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Médicos</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root { --azul-primario: #0d6efd; --azul-escuro: #084298; --azul-claro: #e7f1ff; --cinza-fundo: #f5f7fa; --cinza-borda: #e3e6ea; --texto-escuro: #1f2d3d; --sidebar-larg: 250px; }
        body { background-color: var(--cinza-fundo); font-family: 'Segoe UI', Tahoma, sans-serif; color: var(--texto-escuro); overflow-x: hidden; }
        .navbar-topo { background: linear-gradient(90deg, var(--azul-primario) 0%, var(--azul-escuro) 100%); height: 60px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-topo .navbar-brand { color: #fff; font-weight: 600; font-size: 1.25rem; }
        .btn-sanduiche { background: transparent; border: none; color: #fff; font-size: 1.3rem; padding: 6px 12px; border-radius: 6px; }
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
        .tabela-medicos { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
        .tabela-medicos thead th { background: var(--azul-claro); color: var(--azul-escuro); font-weight: 600; padding: 10px 14px; border-bottom: 2px solid var(--cinza-borda); white-space: nowrap; }
        .tabela-medicos tbody td { padding: 10px 14px; border-bottom: 1px solid var(--cinza-borda); vertical-align: middle; }
        .tabela-medicos tbody tr:hover { background: #f8fbff; }
        .badge-status { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
        .badge-ativo { background: #d1e7dd; color: #0a3622; }
        .badge-inativo { background: #f8d7da; color: #58151c; }
        .avatar-medico { width: 34px; height: 34px; border-radius: 50%; background: var(--azul-claro); color: var(--azul-primario); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.82rem; margin-right: 8px; flex-shrink: 0; }
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
                <a class="nav-link" href="cadastro_agendas.php"><i class="fa-solid fa-calendar-plus"></i> Agendamentos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cadastro_especialidades.php"><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</a>
            </li>
        </ul>
    </aside>

    <main class="conteudo-principal" id="conteudoPrincipal">

        <div class="page-header">
            <h2><i class="fa-solid fa-user-doctor text-primary"></i> Cadastro de Médicos</h2>
            <button class="btn btn-primary" onclick="abrirModalNovo()">
                <i class="fa-solid fa-plus me-1"></i> Novo Médico
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass text-primary"></i> Filtros</div>
            <form method="GET" action="cadastro_medicos.php">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="filtroNome">Nome</label>
                        <input type="text" class="form-control form-control-sm" name="nome" placeholder="Nome do médico" value="<?php echo htmlspecialchars($filtroNome) ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="filtroEspecialidade">Especialidade</label>
                        <select class="form-select form-select-sm" name="especialidade">
                            <option value="0">Todas</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?php echo $esp['id']; ?>" <?php echo ($filtroEspecialidade == $esp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($esp['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="filtroStatus">Status</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Todos</option>
                            <option value="Ativo"   <?php echo ($filtroStatus === 'Ativo')   ? 'selected' : ''; ?>>Ativo</option>
                            <option value="Inativo" <?php echo ($filtroStatus === 'Inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar</button>
                    <a href="cadastro_medicos.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-xmark me-1"></i> Limpar</a>
                </div>
            </form>
        </div>

        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list text-primary"></i> Médicos Cadastrados</span>
                <span class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($medicos); ?> registro(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-medicos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CRM</th>
                            <th>Especialidade</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicos)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4"><i class="fa-solid fa-user-xmark me-2"></i>Nenhum médico encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medicos as $med): 
                                // Gera iniciais
                                $partes = explode(' ', str_replace(['Dr. ', 'Dra. '], '', $med['nome']));
                                $iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1));
                                if(isset($partes[1])) $iniciais .= mb_strtoupper(mb_substr($partes[1], 0, 1));
                                
                                $badgeClass = ($med['status'] === 'Ativo') ? 'badge-ativo' : 'badge-inativo';
                            ?>
                            <tr>
                                <td class="text-muted"><?php echo $med['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar-medico"><?php echo $iniciais ?></span>
                                        <strong><?php echo htmlspecialchars($med['nome']) ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($med['crm']) ?></td>
                                <td><?php echo htmlspecialchars($med['especialidade_nome']) ?></td>
                                <td><?php echo htmlspecialchars($med['telefone']) ?></td>
                                <td><?php echo htmlspecialchars($med['email']) ?></td>
                                <td><span class="badge-status <?php echo $badgeClass ?>"><?php echo htmlspecialchars($med['status']) ?></span></td>
                                <td class="text-center" style="white-space:nowrap;">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2" title="Editar"
                                        onclick="abrirModalEditar(
                                            <?php echo $med['id']; ?>, 
                                            '<?php echo htmlspecialchars(addslashes($med['nome'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($med['crm'])); ?>',
                                            <?php echo $med['especialidade_id']; ?>,
                                            '<?php echo htmlspecialchars(addslashes($med['telefone'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($med['email'])); ?>',
                                            '<?php echo htmlspecialchars(addslashes($med['status'])); ?>'
                                        )">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2" title="Inativar"
                                        onclick="confirmarExclusao(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars(addslashes($med['nome'])); ?>')">
                                        <i class="fa-solid fa-trash"></i>
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

    <div class="modal fade modal-form" id="modalFormMedico" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo"><i class="fa-solid fa-user-plus me-2"></i>Novo Médico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="cadastro_medicos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" id="formAcao" value="novo">
                        <input type="hidden" name="id" id="formId" value="">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="formNome">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formNome" name="nome" placeholder="Ex: Dr. Carlos Lima" required>
                            </div>
                            <div class="col-md-4">
                                <label for="formCrm">CRM <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formCrm" name="crm" placeholder="Ex: CRM/SP 12345" required>
                            </div>
                            <div class="col-md-6">
                                <label for="formEspecialidade">Especialidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="formEspecialidade" name="especialidade" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($especialidades as $esp): ?>
                                        <option value="<?php echo $esp['id']; ?>"><?php echo htmlspecialchars($esp['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="formTelefone">Telefone</label>
                                <input type="text" class="form-control" id="formTelefone" name="telefone" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-8">
                                <label for="formEmail">E-mail</label>
                                <input type="email" class="form-control" id="formEmail" name="email" placeholder="medico@clinica.com">
                            </div>
                            <div class="col-md-4">
                                <label for="formStatus">Status</label>
                                <select class="form-select" id="formStatus" name="status">
                                    <option value="Ativo">Ativo</option>
                                    <option value="Inativo">Inativo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="formExcluir" action="cadastro_medicos.php" method="POST" style="display: none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="inputIdExcluir">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        var modalForm = new bootstrap.Modal(document.getElementById('modalFormMedico'));

        function abrirModalNovo() {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-user-plus me-2"></i>Novo Médico';
            document.getElementById('formAcao').value = 'novo';
            document.getElementById('formId').value = '';
            document.getElementById('formNome').value = '';
            document.getElementById('formCrm').value = '';
            document.getElementById('formEspecialidade').value = '';
            document.getElementById('formTelefone').value = '';
            document.getElementById('formEmail').value = '';
            document.getElementById('formStatus').value = 'Ativo';
            modalForm.show();
        }

        function abrirModalEditar(id, nome, crm, espId, tel, email, status) {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-pen me-2"></i>Editar Médico';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = id;
            document.getElementById('formNome').value = nome;
            document.getElementById('formCrm').value = crm;
            document.getElementById('formEspecialidade').value = espId;
            document.getElementById('formTelefone').value = tel;
            document.getElementById('formEmail').value = email;
            document.getElementById('formStatus').value = status;
            modalForm.show();
        }

        function confirmarExclusao(id, nome) {
            Swal.fire({
                title: 'Atenção!',
                html: 'Deseja inativar o cadastro do(a) <strong>' + nome + '</strong>?<br><small class="text-muted">Isso não apagará as consultas já realizadas.</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, inativar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('inputIdExcluir').value = id;
                    document.getElementById('formExcluir').submit();
                }
            });
        }
    </script>

    <?php
    if (isset($_SESSION['swal_title'])) {
        echo "<script>
            Swal.fire({
                title: '" . addslashes($_SESSION['swal_title']) . "',
                text: '" . addslashes($_SESSION['swal_text']) . "',
                icon: '" . addslashes($_SESSION['swal_icon']) . "',
                confirmButtonColor: '#0d6efd',
                timer: 3500
            });
        </script>";
        unset($_SESSION['swal_title'], $_SESSION['swal_text'], $_SESSION['swal_icon']);
    }
    ?>

</body>
</html>