# ADR-002 — Multi-tenancy como Requisito Estrutural

**Status:** Aceito
**Data:** 2026-08-12

---

## Contexto

O Gestão Pública Lab atende múltiplas organizações públicas (tenants). Cada
organização opera de forma isolada: seus dados, configurações e usuários não
podem ser acessados por outras organizações.

O isolamento entre tenants é um requisito de segurança e conformidade, não
um detalhe de implementação que pode ser adicionado posteriormente.

---

## Decisão

Multi-tenancy é adotado como **requisito estrutural** do projeto:

- **Tenant representa a organização** cliente do sistema.
- Todo tenant possui um estado **ativo** ou **inativo**. Tenants inativos
  não têm acesso operacional ao sistema.
- O isolamento entre tenants é **obrigatório**: dados de tenants distintos
  nunca podem ser misturados ou expostos entre si.
- A **validação do tenant é responsabilidade do backend**. O frontend não
  pode ser a única barreira de isolamento.
- Toda operação com dados pertencentes a um tenant deve ocorrer dentro
  do contexto de um tenant previamente validado.

---

## Consequências

**Positivas:**
- Segurança e conformidade de dados garantidas por design.
- Cada organização opera em ambiente logicamente isolado.
- A validação centralizada no backend reduz superfície de ataque.

**Negativas / trade-offs:**
- Toda query e operação de dados precisa considerar o escopo do tenant.
- Funcionalidades "globais" (ex.: administração do sistema) exigem tratamento
  especial fora do contexto de tenant comum.

---

## Fora de escopo desta ADR

- Estratégia definitiva de seleção e resolução do tenant ativo
  (sessão, subdomínio, URL, header, JWT etc.) — ver ADR-004.
- Estratégia de isolamento de dados no banco (schemas separados,
  campo `tenant_id` por tabela, bancos distintos etc.).
- Modelo de billing ou limites por tenant.
