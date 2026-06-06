# 🩺 Sistema MediAgenda

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-003545?style=for-the-badge&logo=mariadb&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap_5-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

> **Status do Projeto:** Concluído e Entregue ✔️

## 📖 Sobre a Aplicação
O **MediAgenda** é um sistema web desenvolvido em PHP para gerenciamento e agendamento de consultas médicas. Este projeto representa a conclusão e evolução da aplicação base construída nas aulas de Programação 2, expandindo o software com novos módulos administrativos e garantindo a integridade referencial dos dados através de um banco de dados relacional.

---

## 🎯 Entregáveis e Critérios de Avaliação

O projeto foi estruturado para atender a **100% dos requisitos** solicitados na especificação da atividade:

- [x] **CRUD Completo de Especialidades:** Desenvolvido do zero. Permite criar, ler, atualizar e excluir. Possui bloqueio no banco (Constraint) para impedir a exclusão de especialidades atreladas a médicos existentes.
- [x] **CRUD Completo de Médicos:** Totalmente funcional e integrado à tabela de especialidades. Implementa exclusão lógica (*Soft Delete* / Inativação) para preservar o histórico de consultas já agendadas no sistema.
- [x] **Ajuste da Navegação do Sistema:** Refatoração da sidebar e dos hiperlinks em todas as páginas (`principal.php`, agendas, médicos e especialidades), garantindo navegação fluida sem *dead links*.
- [x] **Integração Real com Banco de Dados:** Filtros dinâmicos nas páginas de listagem e formulários consumindo dados reais das views e tabelas (`SELECT`, `INSERT`, `UPDATE`, `DELETE`).
- [x] **Organização do Código e Padrão Visual:** Manutenção estrita da identidade visual solicitada, reaproveitamento de componentes do Bootstrap 5 e alertas customizados via SweetAlert2.
- [x] **Uso Correto do Git/GitHub:** Versionamento colaborativo com commits de todos os integrantes do grupo.

---

## 💻 Tecnologias Utilizadas

| Tecnologia | Finalidade |
| :--- | :--- |
| **PHP 8.x** | Back-end e processamento lógico |
| **MySQL / MariaDB** | Banco de Dados relacional (Views e Constraints) |
| **HTML5 / CSS3** | Estruturação e estilização base |
| **JavaScript / AJAX** | Interatividade e manipulação do DOM |
| **Bootstrap 5** | Framework de UI e responsividade |
| **SweetAlert2** | Modais e alertas modernos ao usuário |
| **Font Awesome 6** | Iconografia do sistema |

---

## ⚙️ Instruções de Instalação e Execução

Para rodar o projeto localmente, siga o passo a passo abaixo:

### 1. Preparando o Ambiente
Certifique-se de ter um servidor web local instalado (como **XAMPP** ou **Laragon**) rodando com Apache e MySQL/MariaDB (Porta padrão 3306).

### 2. Configurando o Diretório
Clone este repositório ou cole a pasta extraída diretamente no diretório raiz do seu servidor:
- *Exemplo no XAMPP:* `C:\xampp\htdocs\mediagenda`

### 3. Configurando o Banco de Dados
1. Abra o painel do `phpMyAdmin` (geralmente em `http://localhost/phpmyadmin`).
2. Clique na aba **Importar**.
3. Selecione o arquivo `script.sql` (localizado na raiz deste projeto) e clique em Executar.
   *Nota: O script criará automaticamente o banco de dados `labdbprog2`, junto com todas as tabelas, relacionamentos e dados iniciais para teste.*

### 4. Testando a Conexão
O arquivo `conexao.php` já vem configurado de fábrica para o padrão local:
- **Host:** `localhost`
- **Usuário:** `root`
- **Senha:** *(vazio)*

### 5. Acesso ao Sistema
Abra seu navegador e acesse a URL:
`http://localhost/mediagenda/login.php`

**Credenciais de teste:**
- **Usuário:** `aluno`
- **Senha:** `123456`

---

## 👨‍💻 Integrantes do Grupo

Desenvolvido colaborativamente por:

* **Mateus Carvalho Rodrigues da Silva**
* **Anna Luiza Silva Dome**

---
*Projeto acadêmico desenvolvido para a disciplina de Programação 2.*
