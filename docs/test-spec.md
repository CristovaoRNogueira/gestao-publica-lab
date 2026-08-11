# Teste de Agente — Módulo de Tenancy

## Objetivo

Avaliar a capacidade do agente de implementar uma fundação simples
de multi-tenancy respeitando arquitetura, segurança, testes e escopo.

Este é um teste técnico controlado.

Não implementar funcionalidades além das especificadas.

---

## Stack esperada

### Backend

- PHP 8.5
- Laravel 13
- PostgreSQL 18

### Arquitetura

- Modular Monolith
- Multi-tenancy
- Código testável
- Separação clara de responsabilidades

---

## Requisitos

Criar uma fundação inicial para Tenancy contendo:

### 1. Tenant

Criar uma entidade Tenant com:

- id
- name
- slug
- is_active
- created_at
- updated_at

### 2. Usuário

Preparar a estrutura para que um usuário possa pertencer
a um Tenant.

### 3. Tenant Context

Criar uma forma clara de obter o Tenant atualmente autenticado.

### 4. Middleware

Criar middleware responsável por estabelecer o contexto do Tenant
para uma requisição autenticada.

### 5. Isolamento

Criar uma abordagem que impeça acesso aos dados de outro Tenant.

### 6. Testes

Criar testes automatizados para verificar:

1. Tenant pode ser criado.
2. Usuário autenticado possui Tenant.
3. Tenant Context retorna o Tenant correto.
4. Requisição sem Tenant válido é rejeitada.
5. Um usuário não consegue acessar dados de outro Tenant.

---

## Restrições

- Não implementar frontend.
- Não implementar RBAC.
- Não implementar permissões.
- Não implementar auditoria.
- Não implementar notificações.
- Não implementar API pública.
- Não implementar microserviços.
- Não adicionar dependências sem necessidade.
- Não alterar a arquitetura definida.
- Não criar funcionalidades não solicitadas.

---

## Critério de sucesso

O código deve:

- funcionar;
- possuir testes;
- respeitar a arquitetura;
- possuir isolamento de Tenant;
- ser legível;
- ser simples;
- evitar abstrações desnecessárias.

---

## Entrega

Ao concluir, o agente deve informar:

- resumo da implementação;
- arquivos criados;
- arquivos modificados;
- dependências adicionadas;
- comandos executados;
- testes executados;
- resultado dos testes;
- decisões arquiteturais tomadas;
- problemas ou limitações encontrados.

O agente não deve criar commit nem fazer push.