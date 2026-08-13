# ADR-001 — Arquitetura Modular Monolith

**Status:** Aceito
**Data:** 2026-08-12

---

## Contexto

O Gestão Pública Lab requer uma base de código organizada, com separação clara
de responsabilidades, que permita evolução independente de domínios sem a
complexidade operacional de múltiplos serviços distribuídos.

A equipe de desenvolvimento é pequena e o produto está em fase inicial.
A adição de microserviços neste estágio introduziria overhead de rede,
deployments independentes e complexidade de consistência desnecessários.

---

## Decisão

O projeto adota a arquitetura **Modular Monolith**:

- Uma única aplicação Laravel.
- Módulos internos com responsabilidades claras e fronteiras bem definidas.
- Microserviços **não** serão utilizados.

---

## Consequências

**Positivas:**
- Complexidade operacional reduzida (um único deploy, um único banco de dados).
- Onboarding mais simples para novos agentes e colaboradores.
- Refatorações e movimentações de código facilitadas dentro do mesmo processo.
- Testes de integração mais diretos.

**Negativas / trade-offs:**
- Escalabilidade horizontal de partes específicas do sistema requer extração
  futura de serviços, se necessário.
- Disciplina de fronteiras entre módulos deve ser mantida manualmente.

---

## Fora de escopo desta ADR

- Estratégia de extração futura para microserviços ou serviços separados.
- Organização interna de diretórios de cada módulo.
- Comunicação entre módulos via eventos, filas ou contratos formais.
