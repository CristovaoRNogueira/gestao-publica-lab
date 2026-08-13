# ADR-003 — Identidade Global de Usuário e Membership por Tenant

**Status:** Aceito
**Data:** 2026-08-12

---

## Contexto

Em um sistema multi-tenant, é necessário definir onde reside a identidade
do usuário e como se relaciona com os tenants.

Uma abordagem comum, porém limitante, é armazenar `tenant_id` diretamente
na tabela `users`. Isso vincula o usuário permanentemente a um único tenant
e impede cenários futuros onde um usuário pertença a mais de uma organização.

---

## Decisão

O modelo de identidade e membership adota a seguinte estrutura:

- **`users`** representa a **identidade global** do usuário no sistema.
  Um registro em `users` não pertence a nenhum tenant específico.
- **`tenants`** representa a organização.
- **`tenant_user`** (tabela pivot) representa o **vínculo N:N** entre usuários
  e tenants, ou seja, o membership de um usuário em uma organização.
- Um usuário **pode pertencer a múltiplos tenants** (o modelo suporta essa
  relação desde o início, mesmo que a interface ainda não exponga essa
  funcionalidade).
- **`users.tenant_id` não deve ser usado** como relação definitiva entre
  usuário e tenant. Esse campo, se existente, não representa o vínculo
  canônico de membership.

---

## Consequências

**Positivas:**
- Flexibilidade para usuários pertencerem a múltiplos tenants sem migração
  de schema no futuro.
- Separação clara entre identidade (quem é o usuário) e membership
  (a qual organização pertence).
- Facilita auditoria e controle de acesso por tenant.

**Negativas / trade-offs:**
- Consultas que precisam verificar membership requerem join com `tenant_user`.
- Lógica de membership deve ser considerada em toda operação que envolva
  o usuário dentro de um contexto de tenant.

---

## Fora de escopo desta ADR

- Papéis e permissões do usuário dentro de cada tenant (RBAC).
- Fluxo de convite ou aprovação de membership.
- Interface de gestão de memberships.
- Mecanismo de seleção do tenant ativo durante uma requisição — ver ADR-004.
