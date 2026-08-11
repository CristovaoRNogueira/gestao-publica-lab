# Ambiente de desenvolvimento

O Docker Compose é o ambiente canônico deste projeto. PHP, Composer, Node.js,
pnpm, PostgreSQL e Valkey não precisam estar instalados no host.

## Pré-requisitos do host

- Git;
- Docker Desktop em execução;
- VS Code com a extensão Dev Containers, para usar o ambiente integrado.

No Windows, use Docker Desktop com o backend WSL2 e mantenha o repositório no
filesystem Linux do WSL para melhor desempenho. No macOS Apple Silicon, Docker
Desktop utiliza as imagens multi-arquitetura nativas.

## Iniciar o ambiente

1. Crie o arquivo local de variáveis do Docker:

   ```sh
   cp docker/.env.example docker/.env
   ```

2. Defina uma senha local em `POSTGRES_PASSWORD` dentro de `docker/.env` e
   repita o mesmo valor em `DB_PASSWORD`. O arquivo é ignorado pelo Git e o
   segundo valor é injetado somente no workspace Laravel.

3. Inicie e construa os serviços:

   ```sh
   docker compose up --build -d
   ```

4. Prepare as dependências e o ambiente Laravel, se ainda não abriu o projeto no
   Dev Container:

   ```sh
   docker compose exec workspace composer install --no-interaction
   docker compose exec workspace sh -lc 'if [ ! -f .env ]; then cp .env.example .env && php artisan key:generate --force; fi'
   docker compose exec workspace php artisan migrate
   ```

5. Acesse a aplicação em <http://localhost:8080>.

PostgreSQL e Valkey não são publicados no host. A aplicação os acessa pelos
nomes internos `postgres` e `valkey`.

## VS Code Dev Container

Depois de criar `docker/.env`, abra o repositório no VS Code e escolha
**Dev Containers: Reopen in Container**. O VS Code se conecta ao serviço
`workspace`, que contém PHP 8.5, Composer 2, Node.js 24 e pnpm 11.

O comando pós-criação executa `composer install`. Ele só executa
`pnpm install --frozen-lockfile` quando `pnpm-lock.yaml` existir; enquanto o
lockfile não existe, ele apenas confirma a disponibilidade do pnpm.

## Comandos de desenvolvimento

Execute ferramentas dentro do workspace:

```sh
docker compose exec workspace php artisan about
docker compose exec workspace composer install
docker compose exec workspace pnpm --version
docker compose exec workspace php artisan test
```

Não use `npm install`. O package manager oficial é pnpm.

## Parar e remover dados

Para parar os serviços sem apagar dados:

```sh
docker compose down
```

Para remover deliberadamente todos os dados persistidos, incluindo PostgreSQL,
Valkey, `vendor`, `node_modules` e o store pnpm:

```sh
docker compose down --volumes
```

Essa operação é destrutiva para os dados locais dos volumes.
