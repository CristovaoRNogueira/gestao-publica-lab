# ADR-008 — Hierarquia Organizacional e Escopo de Autoridade

**Status:** Aceito
**Data:** 2026-08-17

---

## Contexto

A aplicação é multi-tenant e cada Organização pode possuir uma árvore arbitrária de Unidades Organizacionais. À medida que o sistema evolui, precisamos de um modelo de representação e autorização que suporte estruturas organizacionais complexas e variadas, sem codificar regras rígidas que dependam da nomenclatura das unidades (ex: Secretaria, Departamento, Setor).

---

## Decisão

### 1. Organização Genérica e Hierárquica
A `OrganizationUnit` é uma entidade GENÉRICA e HIERÁRQUICA.

A estrutura base é:
```text
Organização
└── Unidade Organizacional
    └── Unidade Organizacional filha
        └── ...
```

A relação hierárquica é definida EXCLUSIVAMENTE por `parent_id`.

**Não existem estruturas obrigatórias chamadas:**
- Secretaria
- Departamento
- Setor
- Diretoria
- Coordenação

Esses termos são apenas nomenclaturas que a Organização pode utilizar.

Exemplos válidos de hierarquias:
```text
Organização
└── Secretaria de Administração
    ├── Departamento de RH
    └── Departamento de Compras

Organização
└── Departamento Jurídico

Organização
└── Diretoria Geral
    └── Coordenação de TI
```
Um "Departamento" pode ser filho de uma "Secretaria", filho de outra unidade qualquer, ou filho direto da "Organização".
**Não existe regra estrutural baseada no campo `type`.**

### 2. Membership
O `User` é global. O `Membership` representa o vínculo do `User` com uma Organização.
O `Membership` pode possuir uma lotação específica através do campo `organization_unit_id`.
Desta forma, o membro pertence diretamente a uma unidade e seu escopo de atuação deriva de sua posição nessa árvore, e não de uma categoria fixa (como pertencer a "Secretaria X").

### 3. Scope
A classe `OrganizationScope` define o domínio espacial da autoridade do usuário dentro da Organização.

A regra oficial para autorização espacial é:
- Atuar na própria unidade → **Permitido**
- Atuar em unidade descendente → **Permitido**
- Atuar em unidade pai (parent) → **Proibido**
- Atuar em unidade irmã (sibling) → **Proibido**
- Atuar em outra Organização (cross-tenant) → **Proibido**

### 4. Global Scope
O fato de o campo `organization_unit_id` possuir o valor `NULL` **NÃO** significa automaticamente que o usuário possui escopo global dentro do Tenant.
`NULL` significa apenas ausência de lotação específica.

O escopo global dentro da Organização exige a posse de uma capability explícita:
`organization.scope.global`

Portanto:
- `organization_unit_id = NULL` + `organization.scope.global` = **Escopo global da Organização.**
- `organization_unit_id = NULL` + (sem capability `organization.scope.global`) = **Não possui automaticamente escopo global.**

*Nota: Não utilizar flags no banco como `is_global` como substituto para o sistema de capabilities.*

---

## Consequências

**Positivas:**
- Estrutura imune às mudanças e diferenças nas nomenclaturas organizacionais das prefeituras.
- Centralização da regra hierárquica no `parent_id`.
- Mitigação de vulnerabilidades onde ausência de dados (`null`) gerava superpoderes imprevistos no sistema.

**Compatibilidade:**
- Essa decisão preserva e complementa integralmente os fluxos implementados nas **Fases 2B, 2C e 2D**.

---

## Documentos relacionados
- ADR-002-multi-tenancy.md
- ADR-003-global-user-tenant-membership.md
- ADR-006-tenant-scoped-rbac.md
