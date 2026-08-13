# ADR-004 — TenantContext e Resolução do Tenant Ativo

**Status:** Aceito
**Data:** 2026-08-12

---

## Contexto

Em rotas e operações tenant-aware, o sistema precisa saber qual tenant está
ativo para aplicar o isolamento correto e garantir que operações de dados
ocorram dentro do escopo adequado.

É necessário definir como esse contexto é representado e quando ele é
estabelecido, independentemente do mecanismo pelo qual o tenant é
identificado na requisição.

---

## Decisão

O tenant ativo de uma requisição é representado pelo **`TenantContext`**:

- `TenantContext` é **request-scoped**: existe durante o ciclo de vida de
  uma única requisição e não é compartilhado entre requisições.
- O contexto é estabelecido **após validação do tenant**, não antes.
- A validação é responsabilidade do **`TenantResolver`**, que verifica:
  1. O tenant **existe** no sistema.
  2. O tenant está **ativo**.
  3. O usuário autenticado possui **membership** naquele tenant
     (conforme ADR-003).
- Operações e rotas tenant-aware que não passem na validação do `TenantResolver`
  não terão `TenantContext` estabelecido e não prosseguem para operações de dados.

---

## Consequências

**Positivas:**
- O contexto de tenant é sempre validado antes de qualquer operação.
- A separação entre "identificar o tenant" e "validar e estabelecer o
  contexto" permite evoluir cada parte independentemente.
- O escopo request-scoped garante que não há vazamento de contexto
  entre requisições concorrentes.

**Negativas / trade-offs:**
- Toda operação que precise do tenant ativo deve acessar o `TenantContext`,
  que deve ser disponibilizado por injeção de dependência ou service container.
- O `TenantResolver` precisa ser executado antes das operações de domínio,
  tipicamente via middleware.

---

## Fora de escopo desta ADR

- O **mecanismo de seleção do tenant** pelo cliente: sessão, subdomínio,
  parâmetro de URL, header HTTP, claim JWT ou qualquer outra forma. Essa
  decisão será registrada em ADR separada quando adotada.
- Estratégia de cache do resultado da resolução.
- Comportamento do `TenantContext` em jobs assíncronos ou filas.
- Múltiplos tenants simultâneos em uma mesma requisição.
