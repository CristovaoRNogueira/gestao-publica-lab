# AGENTS.md

## Projeto

Laboratório para avaliação de agentes de desenvolvimento.

O objetivo é comparar diferentes agentes executando a mesma tarefa
em condições equivalentes.

Este repositório NÃO é ainda o projeto de produção.
É exclusivamente um ambiente de teste.

## Objetivo do teste

Comparar agentes de desenvolvimento implementando a mesma
especificação técnica.

Os agentes avaliados inicialmente são:

- OpenAI Codex
- Google Antigravity

Ambos devem receber a mesma especificação e trabalhar em branches
independentes.

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

## Git

- A branch `main` deve permanecer intacta durante os testes.
- Cada agente deve trabalhar em sua própria branch.
- Agentes não devem criar commits automaticamente.
- Agentes não devem fazer push automaticamente.
- Não realizar merge automaticamente.

## Branches

Codex:

`test/codex`

Antigravity:

`test/antigravity`

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