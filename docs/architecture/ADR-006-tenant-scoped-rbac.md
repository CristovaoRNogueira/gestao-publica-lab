# ADR-006: Tenant-Scoped Role-Based Access Control (RBAC) com Laravel Policies

## 1. Contexto
O SGPC (Sistema Gestão Pública Conectada) atende múltiplas prefeituras/organizações (Tenants) em uma arquitetura *Modular Monolith*. Atualmente, a identidade é global (um `User` acessa a plataforma) e a autoridade inicial de acesso é gerenciada por uma `Membership` (que vincula o User ao Tenant de forma explícita). O `TenantResolver` e o `TenantContext` garantem o isolamento absoluto de dados. No entanto, uma vez autenticado e resolvido em um Tenant, o usuário possui acesso irrestrito às operações daquele domínio, pois não há granularidade de controle.

## 2. Problema
Cada prefeitura possui sua própria estrutura organizacional, nomenclaturas de cargos e delegações de autoridade.

*   **Problema 1:** Um modelo de *Roles* globais engessaria o sistema, impedindo que a "Prefeitura A" crie um papel específico (ex: "Coordenador de Licitações") sem afetar a "Prefeitura B".
*   **Problema 2:** Permitir que as prefeituras criem *Permissions* dinâmicas geraria um sistema caótico e impossível de validar no código-fonte, exigindo motores de avaliação (DSL) complexos.
*   **Problema 3:** Precisamos distinguir "quem pode acessar a plataforma administrativa do SaaS" (Platform Authorization) de "quem pode operar uma prefeitura" (Tenant Authorization), sem vazar dados ou privilégios entre os dois mundos.
*   A solução não deve recorrer a motores ABAC complexos, herança de papéis ou pacotes genéricos que fujam do padrão idiomático do Laravel, respeitando o princípio de evitar complexidade prematura.

## 3. Decisão
Adotar um modelo híbrido de RBAC (*Role-Based Access Control*) fortemente integrado às *Laravel Policies*:

*   **Roles** serão *Tenant-Owned* (criadas e gerenciadas por cada prefeitura).
*   **Permissions** serão *Globais* e *Hardcoded* (capacidades fixas do sistema, mapeadas no código, como `processos.tramitar`).
*   **Associação:** Um usuário recebe papéis através da sua `Membership` no tenant (`membership_role`), garantindo isolamento total.
*   **Policies:** As Laravel Policies constituem o ponto de decisão final de autorização para operações sobre recursos de domínio, compondo a capacidade técnica (`Membership.hasPermission()`), o isolamento de dados (`Resource.tenant_id`) e regras contextuais de autorização.

## 4. Modelo Conceitual
O modelo de autorização opera em três camadas:

1.  **Identidade & Lotação:** `User` global conecta-se a uma prefeitura através de uma `Membership` ativa.
2.  **Capacidade (RBAC):** A `Membership` recebe *N* `Roles` locais. Cada `Role` agrupa *N* `Permissions` globais. A capacidade da membership é a união de todas as permissões dos seus papéis.
3.  **Autoridade (Policy):** A porta de entrada do domínio. A Policy pergunta: "A `Membership` injetada no `TenantContext` possui a *Permission* X?", "O Recurso alvo pertence ao *Tenant* do `TenantContext`?" e "Existem impedimentos contextuais para esta ação?".

## 5. Modelo de Dados
As seguintes estruturas suportarão o modelo:

*   `permissions`: Tabela global de catálogo de capacidades (`id`, `name`, `slug` único).
*   `roles`: Tabela com posse local (`id`, `tenant_id`, `name`, `slug`). Unicidade garantida por `(tenant_id, slug)`.
*   `membership_role`: Tabela pivot associando `membership_id` e `role_id` (N:N).
*   `role_permission`: Tabela pivot associando `role_id` e `permission_id` (N:N).

## 6. Fluxo de Autorização
1.  O middleware `ResolveTenant` processa o *Request* e invoca o `TenantResolver`.
2.  O Resolver carrega o `Tenant` e a `Membership` associada, validando se ambos estão ativos.
3.  `Tenant` e `Membership` são consolidados atomicamente no `TenantContext`.
4.  O Controller invoca `$this->authorize('update', $recurso)`.
5.  A Policy correspondente (ex: `SecretariaPolicy`) avalia a combinação de:
    *   **Capability:** `$this->context->getMembership()->hasPermission('secretarias.update')`
    *   **Ownership:** `$recurso->tenant_id === $this->context->getTenant()->id`
6.  Se ambas as condições forem satisfeitas, a ação de domínio é executada.

## 7. Segurança
*   **Cross-Tenant Safety:** A relação `membership_role` (ao invés de `role_user`) torna impossível que um papel da "Prefeitura A" seja atribuído à presença do usuário na "Prefeitura B". A query de avaliação parte estritamente do `TenantContext->getMembership()`.
*   **Bypass de Frontend:** O `tenant_id` e o contexto de autorização nunca são derivados do payload. IDs de recursos, roles ou memberships recebidos em requests são apenas identificadores de entrada e devem ser validados pelo backend contra o `TenantContext` e as regras de autorização aplicáveis.

## 8. Consequências Positivas
*   Autonomia máxima garantida aos municípios para organizarem seus departamentos e nomenclaturas de cargos.
*   As capacidades do software (`Permissions`) permanecem rigorosamente tipadas, previsíveis e rastreáveis na base de código.
*   A delegação de autoridade mantém o código idiomático do *framework* (`$this->authorize()`), facilitando a legibilidade, manutenção e a execução de testes automatizados.

## 9. Consequências Negativas
*   As Policies ganharão complexidade de avaliação se comparadas ao modelo simples atual, exigindo *Eager Loading* eficiente das *roles/permissions* no momento da resolução do Tenant para mitigar problemas de desempenho (N+1 queries).

## 10. Fora de Escopo
Não serão implementados nesta iteração ou por este modelo arquitetural:

*   Motores de regra dinâmicos (ABAC).
*   Controles granulares a nível de coluna ou registro individual (ACL genérica).
*   Herança de papéis (Role A herdar permissões de Role B).
*   Linguagens de domínio específico (DSL) para configurações de permissões dinâmicas.
*   **Platform Admin:** A distinção de uma autoridade administrativa global que opera toda a plataforma SaaS fica de fora desta decisão, sendo o foco restrito ao escopo do Tenant.
*   **Provisionamento Automático:** A criação de mecanismos específicos (como Seeders/Listeners obrigatórios) para a injeção inicial de Roles está descartada. Os papéis padrão poderão ser provisionados posteriormente por um mecanismo a ser definido oportunamente.

## 11. Impacto nos Futuros Módulos
*   **Secretaria e Setor:** Serão os primeiros consumidores literais do RBAC (ex: `secretarias.view`, `setores.create`). As rotas estarão expostas a bloqueios *403 Forbidden* automáticos caso a *Membership* não detenha o papel adequado.
*   **Servidor / Colaborador (RH):** Ficará clara a distinção conceitual onde a `Membership` controla o *Acesso de Sistema*, enquanto a futura entidade `Servidor` controlará dados de negócio (matrícula, lotação departamental), evitando acoplamento indevido.
*   **Processos, Workflow e Documentos:** As Policies atuarão como a barreira combinada de acesso (Permission + Ownership + Contextual Rules). Contudo, **as regras próprias do ciclo de vida do processo ou do workflow permanecerão restritas ao domínio/engine de workflow**. A Policy não absorverá lógicas de transição de estado, mantendo seu foco apenas em responder se o ator tem autorização para tentar a operação no recurso atual.
