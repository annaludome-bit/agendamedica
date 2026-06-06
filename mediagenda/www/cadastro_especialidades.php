<?php
/* ============================================================
   cadastro_especialidades.php - CRUD Completo de Especialidades
============================================================ */
// Inicia a sessão para capturar as mensagens do SweetAlert
session_start();

// Conexão com o banco de dados (ajuste o caminho se necessário)
require_once 'conexao.php'; 

// DADOS DO OPERADOR LOGADO (Placeholder)
$operadorNome  = "Administrador";
$operadorEmail = "admin@clinica.com";

// ============================================================
// PROCESSAMENTO DE AÇÕES (POST) - CREATE, UPDATE, DELETE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';

    try {
        if ($acao === 'novo' && !empty($nome)) {
            // C: CREATE
            $stmt = mysqli_prepare($conexao_bd, "INSERT INTO especialidades (nome) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $nome);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Salvo!';
            $_SESSION['swal_text']  = 'Especialidade cadastrada com sucesso.';
            $_SESSION['swal_icon']  = 'success';

        } elseif ($acao === 'editar' && $id > 0 && !empty($nome)) {
            // U: UPDATE
            $stmt = mysqli_prepare($conexao_bd, "UPDATE especialidades SET nome = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $nome, $id);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Atualizado!';
            $_SESSION['swal_text']  = 'Especialidade atualizada com sucesso.';
            $_SESSION['swal_icon']  = 'success';

        } elseif ($acao === 'excluir' && $id > 0) {
            // D: DELETE
            $stmt = mysqli_prepare($conexao_bd, "DELETE FROM especialidades WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['swal_title'] = 'Excluído!';
            $_SESSION['swal_text']  = 'Especialidade removida do sistema.';
            $_SESSION['swal_icon']  = 'success';
        }
    } catch (mysqli_sql_exception $e) {
        // Tratamento de erros do Banco de Dados
        $_SESSION['swal_title'] = 'Oops...';
        $_SESSION['swal_icon']  = 'error';
        
        if ($e->getCode() == 1062) { // 1062 = Entrada duplicada
            $_SESSION['swal_text'] = 'Já existe uma especialidade cadastrada com este nome.';
        } elseif ($e->getCode() == 1451) { // 1451 = Violação de chave estrangeira
            $_SESSION['swal_text'] = 'Não é possível excluir: existem médicos ou consultas vinculadas a esta especialidade.';
        } else {
            $_SESSION['swal_text'] = 'Erro no banco de dados: ' . $e->getMessage();
        }
    }
    
    // Evita o reenvio do formulário ao atualizar a página
    header("Location: cadastro_especialidades.php");
    exit;
}

// ============================================================
// R: READ - FILTROS E BUSCA
// ============================================================
$filtroNome = trim(isset($_GET['nome']) ? $_GET['nome'] : '');
$especialidades = array();

// Monta a query dinamicamente baseada no filtro
$sql = "SELECT id, nome, created_at FROM especialidades WHERE 1=1";
$params = array();
$types = "";

if ($filtroNome !== '') {
    $sql .= " AND nome LIKE ?";
    $params[] = "%{$filtroNome}%";
    $types .= "s";
}
$sql .= " ORDER BY nome ASC";

// Executa a busca
$stmt = mysqli_prepare($conexao_bd, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $especialidades[] = $row;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediAgenda - Cadastro de Especialidades</title>

    <link rel="icon" type="image/x-icon" href="img/favicon.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --azul-primario: #0d6efd;
            --azul-escuro:   #084298;
            --azul-claro:    #e7f1ff;
            --cinza-fundo:   #f5f7fa;
            --cinza-borda:   #e3e6ea;
            --texto-escuro:  #1f2d3d;
            --sidebar-larg:  250px;
        }

        body {
            background-color: var(--cinza-fundo);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: var(--texto-escuro);
            overflow-x: hidden;
        }

        /* Navbar e Sidebar */
        .navbar-topo { background: linear-gradient(90deg, var(--azul-primario) 0%, var(--azul-escuro) 100%); height: 60px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-topo .navbar-brand { color: #fff; font-weight: 600; font-size: 1.25rem; }
        .btn-sanduiche { background: transparent; border: none; color: #fff; font-size: 1.3rem; padding: 6px 12px; border-radius: 6px; }
        .operador-toggle { background: transparent; border: none; color: #fff; display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 30px; }
        
        .sidebar { position: fixed; top: 60px; left: 0; width: var(--sidebar-larg); height: calc(100vh - 60px); background: #fff; border-right: 1px solid var(--cinza-borda); padding: 20px 0; transition: transform 0.3s ease; z-index: 1020; overflow-y: auto; }
        .sidebar .nav-link { color: var(--texto-escuro); padding: 12px 20px; border-left: 3px solid transparent; display: flex; align-items: center; gap: 12px; }
        .sidebar .nav-link i { width: 22px; color: var(--azul-primario); font-size: 1.05rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.ativo { background: var(--azul-claro); border-left-color: var(--azul-primario); color: var(--azul-escuro); font-weight: 600;}
        
        /* Conteúdo Principal */
        .conteudo-principal { margin-top: 60px; margin-left: var(--sidebar-larg); padding: 25px; transition: margin-left 0.3s ease; min-height: calc(100vh - 60px); }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .page-header h2 { font-size: 1.4rem; font-weight: 700; color: var(--azul-escuro); margin: 0; display: flex; align-items: center; gap: 10px; }
        .card-pagina { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid var(--cinza-borda); padding: 20px 24px; margin-bottom: 20px; }
        .card-titulo { font-weight: 600; font-size: 0.95rem; color: var(--azul-escuro); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        
        /* Tabela Simplificada */
        .tabela-dados { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
        .tabela-dados thead th { background: var(--azul-claro); color: var(--azul-escuro); font-weight: 600; padding: 10px 14px; border-bottom: 2px solid var(--cinza-borda); }
        .tabela-dados tbody td { padding: 10px 14px; border-bottom: 1px solid var(--cinza-borda); vertical-align: middle; }
        .tabela-dados tbody tr:hover { background: #f8fbff; }
        
        .modal-form .modal-header { background: var(--azul-primario); color: #fff; }
        .modal-form .modal-header .btn-close { filter: invert(1); }
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
                <i class="fa-solid fa-circle-user" style="font-size: 1.6rem;"></i>
                <span class="d-none d-md-inline"><?php echo htmlspecialchars($operadorNome) ?></span>
            </button>
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
                <a class="nav-link" href="cadastro_medicos.php"><i class="fa-solid fa-user-doctor"></i> Cadastro de Médicos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link ativo" href="cadastro_especialidades.php"><i class="fa-solid fa-list-check"></i> Cadastro de Especialidades</a>
            </li>
        </ul>
    </aside>

    <main class="conteudo-principal" id="conteudoPrincipal">

        <div class="page-header">
            <h2><i class="fa-solid fa-list-check text-primary"></i> Cadastro de Especialidades</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFormEspecialidade" onclick="abrirModalNovo()">
                <i class="fa-solid fa-plus me-1"></i> Nova Especialidade
            </button>
        </div>

        <div class="card-pagina">
            <div class="card-titulo"><i class="fa-solid fa-magnifying-glass text-primary"></i> Buscar Especialidade</div>
            <form method="GET" action="cadastro_especialidades.php">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="filtroNome" class="form-label mb-1" style="font-size: 0.88rem; font-weight: 500;">Nome da Especialidade</label>
                        <input type="text" class="form-control form-control-sm" id="filtroNome" name="nome" value="<?php echo htmlspecialchars($filtroNome) ?>">
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary btn-sm me-1"><i class="fa-solid fa-magnifying-glass me-1"></i> Filtrar</button>
                        <a href="cadastro_especialidades.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-xmark me-1"></i> Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-pagina">
            <div class="card-titulo d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-table-list text-primary"></i> Especialidades Cadastradas</span>
                <span class="text-muted" style="font-size:0.82rem; font-weight:400;">
                    <?php echo count($especialidades); ?> registro(s) encontrado(s)
                </span>
            </div>

            <div class="table-responsive">
                <table class="tabela-dados">
                    <thead>
                        <tr>
                            <th width="10%">ID</th>
                            <th>Nome da Especialidade</th>
                            <th width="20%" class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($especialidades)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Nenhuma especialidade encontrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($especialidades as $esp): ?>
                            <tr>
                                <td class="text-muted">#<?php echo $esp['id'] ?></td>
                                <td><strong><?php echo htmlspecialchars($esp['nome']) ?></strong></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2 btn-editar" title="Editar"
                                            onclick="abrirModalEditar(<?php echo $esp['id']; ?>, '<?php echo htmlspecialchars(addslashes($esp['nome'])); ?>')">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger py-0 px-2 btn-excluir" title="Excluir"
                                            onclick="confirmarExclusao(<?php echo $esp['id']; ?>, '<?php echo htmlspecialchars(addslashes($esp['nome'])); ?>')">
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

    <div class="modal fade modal-form" id="modalFormEspecialidade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFormTitulo"><i class="fa-solid fa-list-check me-2"></i>Nova Especialidade</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="cadastro_especialidades.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" id="formAcao" value="novo">
                        <input type="hidden" name="id" id="formId" value="">
                        
                        <div class="mb-3">
                            <label for="formNome" class="form-label" style="font-weight: 500;">Nome da Especialidade <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="formNome" name="nome" required>
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

    <form id="formExcluir" action="cadastro_especialidades.php" method="POST" style="display: none;">
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" id="inputIdExcluir">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Lógica de abertura do Modal (Novo/Editar)
        var modalForm = new bootstrap.Modal(document.getElementById('modalFormEspecialidade'));

        function abrirModalNovo() {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-plus me-2"></i>Nova Especialidade';
            document.getElementById('formAcao').value = 'novo';
            document.getElementById('formId').value = '';
            document.getElementById('formNome').value = '';
        }

        function abrirModalEditar(id, nome) {
            document.getElementById('modalFormTitulo').innerHTML = '<i class="fa-solid fa-pen me-2"></i>Editar Especialidade';
            document.getElementById('formAcao').value = 'editar';
            document.getElementById('formId').value = id;
            document.getElementById('formNome').value = nome;
            modalForm.show();
        }

        // Lógica de Exclusão usando SweetAlert
        function confirmarExclusao(id, nome) {
            Swal.fire({
                title: 'Atenção!',
                html: 'Deseja realmente excluir a especialidade <strong>' + nome + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Preenche o form invisível e faz o POST pro PHP
                    document.getElementById('inputIdExcluir').value = id;
                    document.getElementById('formExcluir').submit();
                }
            });
        }
    </script>

    <?php
    // GATILHO PARA EXIBIR MENSAGENS VINDAS DO PHP (Sucesso ou Erro) via SweetAlert
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
        // Limpa as variáveis da sessão para não exibir novamente ao dar F5
        unset($_SESSION['swal_title'], $_SESSION['swal_text'], $_SESSION['swal_icon']);
    }
    ?>

</body>
</html>