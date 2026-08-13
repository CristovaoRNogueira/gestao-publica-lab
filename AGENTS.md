# AGENTS.md

## Projeto

Repositório oficial de desenvolvimento do Gestão Pública Lab.

A aplicação está em desenvolvimento ativo e não deve ser tratada como
produção, mas toda a base de código deve seguir padrão de produção.

O desenvolvimento é conduzido colaborativamente por agentes de IA,
sob supervisão do responsável pelo projeto.

## Objetivo

Desenvolver o produto Gestão Pública Lab com qualidade de produção.

Como objetivo secundário, o projeto serve de referência para avaliação
comparativa de agentes de desenvolvimento implementando a mesma
especificação técnica.

Os agentes participantes são:

- OpenAI Codex
- Google Antigravity
- Claude
- Modelos Gemini disponibilizados pelo Antigravity

Cada agente deve receber a especificação da tarefa e trabalhar
em uma branch própria.

## Agentes de desenvolvimento

O projeto é desenvolvido colaborativamente pelos seguintes agentes:

- OpenAI Codex
- Google Antigravity
- Claude
- Modelos Gemini disponibilizados pelo Antigravity

Todos são agentes principais de desenvolvimento.
Nenhum agente possui autoridade superior aos demais.

A autoridade do projeto é definida pelo responsável pelo projeto,
por este `AGENTS.md`, pelas decisões arquiteturais registradas em
`docs/architecture/` e pelas especificações técnicas do projeto.

### Regras de convivência entre agentes

- Todo agente deve ler e seguir integralmente este arquivo (`AGENTS.md`) antes de iniciar qualquer tarefa.
- Nenhum agente pode alterar silenciosamente uma decisão arquitetural.
- Mudanças arquiteturais relevantes devem ser propostas e aprovadas antes da implementação.
- Decisões arquiteturais permanentes devem ser registradas em `docs/architecture/`.
- Agentes diferentes não devem editar simultaneamente o mesmo checkout.
- Cada tarefa deve utilizar uma branch própria, independentemente do agente responsável.
- Commit, push e merge exigem autorização explícita do responsável pelo projeto.
- Docker Compose (`workspace`) continua sendo o ambiente canônico de desenvolvimento.
- `pnpm` continua sendo o package manager oficial para dependências JavaScript/Node.js.

## Regras gerais

- Leia a documentação relevante antes de implementar.
- Não altere arquivos fora do escopo.
- Não adicione dependências sem necessidade.
- Não altere a arquitetura definida sem justificativa.
- Não implemente funcionalidades não solicitadas.
- Código deve ser simples, legível e testável.
- Segurança deve ser tratada no backend.
- Dados de diferentes tenants nunca podem ser misturados.
- Criar testes para comportamentos críticos.
- Executar testes antes de concluir.
- Informar comandos executados.
- Informar testes executados.
- Informar arquivos criados e modificados.
- O agente revisor não deve modificar a implementação durante a revisão,
  salvo autorização explícita.

## Stack do teste

- PHP 8.5
- Laravel 13
- PostgreSQL 18
- Modular Monolith
- Multi-tenancy

## Arquitetura

O projeto deve seguir inicialmente uma arquitetura
Modular Monolith.

Não utilizar microserviços.

Decisões arquiteturais com impacto sobre múltiplos módulos ou
infrastrutura central devem ser documentadas em `docs/architecture/`
antes ou junto da implementação.

## Git

- `main` representa a linha estável do projeto. Desenvolvimento e
  experimentação devem ocorrer em branches de tarefa. Alterações em
  `main` devem ocorrer por Pull Request ou processo explicitamente
  autorizado.
- Cada tarefa deve utilizar uma branch própria, nomeada de acordo com
  a tarefa, independentemente do agente responsável.
- Agentes não devem criar commits automaticamente.
- Agentes não devem fazer push automaticamente.
- Não realizar merge automaticamente.

## Branches

Cada tarefa deve utilizar uma branch própria, nomeada de acordo com
a tarefa, independentemente do agente responsável.

Não existem branches permanentes por agente.

## Critério de qualidade

O objetivo não é produzir a maior quantidade de código.

O objetivo é produzir a solução mais:

1. correta;
2. segura;
3. simples;
4. testável;
5. manutenível;
6. aderente à especificação.

## Regra fundamental

Quando uma decisão arquitetural não estiver especificada,
não criar uma solução complexa por iniciativa própria.

Preferir a solução mais simples que satisfaça os requisitos.

Se uma decisão importante não puder ser tomada com segurança,
interromper a implementação e solicitar orientação.

## Ambiente de desenvolvimento

O projeto utiliza:

- Node.js 24.19.0
- pnpm 11.21.0

O arquivo `.node-version` define a versão oficial do Node.js.

Antes de executar comandos relacionados ao frontend:

1. Verifique a versão do Node com `node -v`.
2. Verifique a versão do pnpm com `pnpm --version`.
3. Se o ambiente estiver utilizando outra versão, não altere ou instale versões automaticamente.
4. Não instalar Node, npm, pnpm ou gerenciadores de versões globalmente.
5. Não modificar `.node-version` sem autorização.

Package manager oficial:

`pnpm`

Nunca usar `npm install` para instalar dependências do projeto quando uma operação equivalente com `pnpm` existir.

## Ambiente canônico

Docker Compose é o ambiente canônico de desenvolvimento. Ferramentas do projeto
devem ser executadas no serviço `workspace`; não depender de PHP, Composer,
Node.js, pnpm, PostgreSQL ou Valkey instalados no host.

Comandos típicos:

```sh
docker compose exec workspace php artisan test
docker compose exec workspace composer install
docker compose exec workspace pnpm --version
```
