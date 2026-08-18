# DB Manager

Gerenciador web de conexoes de banco de dados: conecta em multiplos tipos de
banco (MySQL/MariaDB, PostgreSQL, SQLite e SQL Server), lista databases,
tabelas, permite navegar/filtrar/ordenar registros, editar estrutura de
dados (inserir, editar, remover linhas) e executar SQL livre.

As credenciais de conexao ficam **apenas na sessao do navegador** (nao sao
gravadas em disco nem em banco proprio). Ao fechar a sessao ou fazer logout
do PHP, as conexoes cadastradas somem.

## Requisitos

- PHP 8.1+
- Composer
- Extensoes PDO conforme os bancos que voce for usar:
  - `pdo_mysql` para MySQL/MariaDB
  - `pdo_pgsql` para PostgreSQL
  - `pdo_sqlite` para SQLite
  - `pdo_sqlsrv` (driver da Microsoft) para SQL Server

## Instalacao

```bash
composer install
```

Nao ha dependencias externas (o projeto usa apenas PDO nativo do PHP);
o `composer install` apenas gera o autoload PSR-4.

## Executando em desenvolvimento

```bash
php -S localhost:8000 -t public public/router.php
```

Acesse http://localhost:8000

Em producao, aponte o document root do Apache/Nginx para a pasta `public/`
(ha um `public/.htaccess` pronto para Apache com `mod_rewrite`).

## Deploy com Docker Compose (Dokploy, etc.)

Este e o caminho recomendado quando voce precisa de **suporte a SQL Server**,
que o Nixpacks nao consegue prover (veja a secao seguinte). O repositorio ja
inclui:

- `Dockerfile` — imagem unica baseada em `php:8.3-fpm-bookworm` com Nginx +
  PHP-FPM (via `supervisord`) e as extensoes `pdo_mysql`, `pdo_pgsql`,
  `pdo_sqlite` e `pdo_sqlsrv` (essa ultima com o driver ODBC 18 da
  Microsoft instalado no build).
- `docker-compose.yml` — sobe o servico `app` na porta `8080`, com um
  volume nomeado (`sqlite-data`) para persistir os arquivos criados em
  `storage/sqlite/` entre deploys.

No Dokploy, crie uma aplicacao do tipo **Compose** apontando para este
repositorio (o `docker-compose.yml` esta na raiz). O build pode levar
alguns minutos na primeira vez por causa da instalacao do driver da
Microsoft. Ajuste a porta publicada/dominio pelas configuracoes do Dokploy
normalmente (o container expoe a porta `80` internamente, mapeada para
`8080` no compose).

Para rodar localmente com Docker:

```bash
docker compose up --build
```

Acesse http://localhost:8080

## Deploy com Nixpacks (Railway, etc.)

O projeto ja inclui um `nixpacks.toml` na raiz configurando:

- `NIXPACKS_PHP_ROOT_DIR=/app/public` (o document root e a pasta `public/`,
  nao a raiz do repo).
- `NIXPACKS_PHP_FALLBACK_PATH=/index.php` (equivalente ao `.htaccess`: toda
  rota que nao bate em arquivo estatico cai no front controller).

O provider PHP do Nixpacks instala as extensoes listadas em `require` no
`composer.json` (ja inclui `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`). A
extensao `pdo_sqlsrv` (SQL Server) **nao esta disponivel** nos pacotes do
Nixpacks/nixpkgs — para suportar SQL Server em producao e necessario um
Dockerfile proprio instalando o driver ODBC da Microsoft.

Dois pontos de atencao no Railway/Nixpacks:

- **Sessao em arquivo**: por padrao o PHP guarda sessao em disco local. Isso
  funciona com uma unica instancia, mas nao sobrevive a redeploys/restarts
  e nao funciona corretamente com multiplas replicas (sem sticky session).
- **SQLite**: arquivos criados em `storage/sqlite/` ficam em disco efemero;
  anexe um volume persistente nesse caminho se precisar manter os arquivos
  entre deploys.

## Uso

1. Na tela inicial, cadastre uma conexao informando o tipo de banco, host
   (ou caminho do arquivo, no caso do SQLite), porta, usuario e senha.
2. Escolha o database desejado (para SQLite esse passo e pulado, pois o
   arquivo já é o database).
3. Navegue pelas tabelas: veja dados paginados, ordene por coluna, filtre
   com uma clausula WHERE livre, edite/insira/remova registros ou veja a
   estrutura (colunas, tipos, chave primaria).
4. Use "Executar SQL" para rodar qualquer instrucao diretamente.

### SQLite

Para abrir um arquivo `.sqlite` existente, informe o caminho completo. Para
**criar** um arquivo novo, ele precisa ficar dentro de `storage/sqlite/`
(protecao contra criacao de arquivos em qualquer lugar do disco via o campo
de conexao).

## Seguranca

- Este projeto **nao possui tela de login**. Ele foi desenhado para uso
  local/confiavel (ex: atras de VPN, autenticacao do servidor web via
  Basic Auth, ou apenas em `localhost`). Nao exponha publicamente sem
  proteger o acesso.
- Todas as consultas de dados (SELECT/INSERT/UPDATE/DELETE) usam
  *prepared statements* com parametros ligados; identificadores (nomes de
  tabela/coluna) sao sempre validados contra o schema real e escapados de
  acordo com o dialeto de cada banco.
- O filtro "WHERE" na listagem e a tela "Executar SQL" aceitam SQL livre
  digitado por quem esta usando a ferramenta -- por design, assim como no
  phpMyAdmin/Adminer. Trate o acesso a essa ferramenta como acesso direto
  ao banco.
- Formularios de escrita (criar conexao, inserir/editar/remover linha) sao
  protegidos por token CSRF.

## Estrutura do projeto

```
public/            front controller, assets estaticos, router de dev
src/Http/           Request e Router minimalistas
src/Database/        interface de driver + implementacoes por banco
src/Controllers/     controllers HTTP
src/Support/          CSRF, Flash messages, paginacao, view renderer
templates/            views .phtml
storage/sqlite/       destino permitido para criacao de novos arquivos SQLite
docker/               nginx.conf e supervisord.conf usados pelo Dockerfile
Dockerfile             imagem para deploy via Docker Compose (com SQL Server)
docker-compose.yml     stack para rodar/deployar a imagem acima
nixpacks.toml          config alternativa de deploy via Nixpacks (sem SQL Server)
```

Adicionar suporte a um novo banco = criar uma classe em `src/Database/`
implementando `DriverInterface` (ou estendendo `AbstractDriver`) e
registra-la em `DriverFactory`.
