# ADR-009 — Camadas de Administração SaaS e de Organização

**Status:** Aceito
**Data:** 2026-08-17

---

## Contexto

À medida que consolidamos as permissões de escopo no sistema, torna-se necessário diferenciar claramente quem detém o controle do produto e sistema como um todo (SaaS) daqueles que controlam e operam uma Organização (Tenant) isoladamente ou de maneira restrita.

---

## Decisão

O sistema consolida três camadas principais de administração:

### 1. SaaS Admin
A autoridade da plataforma inteira.
É representado pela infraestrutura `PlatformRole` já existente (Model `PlatformRole` e relações atreladas diretamente ao `User`).

- SaaS Admin **não é** um `Membership` comum de um Tenant.
- SaaS Admin não depende do `TenantContext` para fundamentar sua autoridade global.
- A representação de sua autoridade não deve ser feita através da criação de "Memberships" artificiais nas organizações.
- Nesta fase da arquitetura, **não será implementado** suporte a impersonation (encarnar usuários ou assumir sessões artificiais em nome de Tenants).

### 2. Super Admin da Organização
A autoridade máxima restrita aos limites de UMA Organização (Tenant).

É representado exclusivamente pela composição de:
- `User` + `Membership` na Organização
- `Role` + `Permissions` contendo permissões administrativas plenas
- Capability específica de escopo global: `organization.scope.global`

**Regras estritas:**
- Não deve ser criada e nem deve-se depender de uma Role com nome obrigatório de "SuperAdmin". A autorização avalia *capabilities*, nunca strings nominais das Roles.

### 3. Administrador de Unidade
Administrador cuja autoridade (mesmo se contiver permissões de edição ou criação) está sempre limitadas pelo alcance vertical de sua `OrganizationUnit` de origem e das unidades descendentes.

### 4. Membro
Usuários padrão cuja autoridade de atuação baseia-se unicamente no cruzamento e consolidação de:
- `Membership`
- `Role` e `Permissions`
- `OrganizationScope`

---

## Separação Estrita (Identity & Logic)

Para evitar vazamento de privilégios ou arquiteturas imprecisas, documenta-se e oficializa-se formalmente as seguintes fronteiras de tipos no código:

`PlatformRole` ≠ `Membership` ≠ `OrganizationUnit` ≠ `Role`

- `PlatformRole` define capacidades do `User` no SaaS.
- `Membership` é o ticket de entrada em um Tenant.
- `OrganizationUnit` dita o raio geográfico/espacial da autoridade do `Membership`.
- `Role` (Tenant Role) define capacidades operacionais/verbais do `Membership` num Tenant.

---

## Documentos relacionados
- ADR-003-global-user-tenant-membership.md
- ADR-006-tenant-scoped-rbac.md
- ADR-008-organizational-hierarchy-and-scope.md
