# ADR-005 — Seleção de Tenant via Sessão (Fluxo Web)

**Status:** Aceito
**Data:** 2026-08-12

---

## Contexto

ADR-004 definiu que o `TenantContext` é request-scoped e que o `TenantResolver`
valida existência, estado ativo e membership, mas deixou explicitamente em
aberto o mecanismo de seleção do tenant pelo cliente.

Para o fluxo web autenticado com Inertia, é necessário definir como o tenant
ativo é persistido entre requisições.

---

## Decisão

Para o fluxo web autenticado com Inertia, o tenant ativo é armazenado na
**sessão do usuário**:

- Após login, o sistema consulta as memberships ativas do usuário.
- Se o usuário pertence a **exatamente 1 tenant ativo**, o tenant é
  selecionado automaticamente e armazenado na sessão.
- Se o usuário pertence a **0 tenants ativos**, a sessão não contém
  `tenant_id`. O usuário está autenticado mas sem acesso a operações
  tenant-aware.
- Se o usuário pertence a **múltiplos tenants ativos**, a sessão não
  contém `tenant_id` até que o usuário realize a seleção.
- Em cada requisição tenant-aware, o `ResolveTenant` middleware lê o
  `tenant_id` da sessão e valida via `TenantResolver`.

---

## Consequências

**Positivas:**
- Compatível com o modelo request-scoped do `TenantContext` (ADR-004).
- Simples: usa a sessão que o Laravel já gerencia.
- Não exige alterações no frontend para cada requisição (tenant viaja
  na sessão, não no body ou header).

**Negativas / trade-offs:**
- Restrita ao fluxo web com sessão. Não serve para APIs stateless.
- Mudança de tenant requer atualização explícita da sessão.

---

## Fora de escopo desta ADR

- Mecanismos de seleção de tenant para clientes API (header, JWT claim etc.).
- UI de seleção de tenant para múltiplos tenants.
- Múltiplos tenants simultâneos na mesma sessão.
