# Roadmap e Estado Oficial do Projeto

Este documento representa a bússola funcional do Gestão Pública Lab. Aqui são catalogadas as Fases de evolução, seu status, o que de fato compõe a entrega e o que, por deliberação, foi deixado de fora do escopo.

## Legenda de Status
- ✅ Implementado (Ativo na branch `main`)
- 🚧 Em desenvolvimento (Em branch de feature ou homologação)
- 📋 Planejado (Aprovado arquiteturalmente, código não finalizado)
- ⏸ Fora de escopo / Aguardando decisão

---

## Fases do Projeto

### ✅ Fase 1 — Fundação Inicial
- **Objetivo:** Estabelecer a arquitetura básica do monolito e o isolamento rígido de multi-tenancy.
- **Entregas Principais:**
  - Modelagem da trindade base: `User`, `Tenant`, `Membership`.
  - Injeção e isolamento de rotas via `TenantResolver` e `TenantContext`.
- **Fora de Escopo:** Telas de interface administrativas ou gestão visual de dados.
- **Documentação Relacionada:** ADR-001, ADR-002, ADR-004.

### ✅ Fase 2A — Identidade Global e Registro
- **Objetivo:** Permitir que o User exista de maneira atômica e independente de uma Organização, consolidando a "Identidade Global".
- **Entregas Principais:**
  - Telas e rotas de Registro Global.
  - Implementação completa do Password Broker nativo para recuperação de senha sem acoplamento a Tenant.
- **Fora de Escopo:** Concessão automática de Organização/Tenant logo após a criação da conta.
- **PRs/Docs:** PR #31, ADR-003.

### ✅ Fase 2B — Convites e Onboarding
- **Objetivo:** Criar o fluxo orgânico de ingresso (onboarding) de pessoas físicas em um Tenant.
- **Entregas Principais:**
  - Model e serviços para `TenantInvitation`. Disparo de e-mail integrado.
  - Fluxo de verificação no aceite, gerando a relação inicial do membro na organização com status estrito de `pending` (Pending Approval).
  - Validação preliminar de segurança via `OrganizationScope`.
- **Fora de Escopo:** Aceitação imediata sem crivo gerencial; envio em massa.
- **PRs:** PR #27, #31, #32.

### ✅ Fase 2C — Aprovação e Rejeição
- **Objetivo:** Implementar o fluxo de governança sobre as pendências de admissão de membros (Aprovação ou Recusa).
- **Entregas Principais:**
  - Transições da máquina de estados do `Membership`: `pending` → `active` ou `rejected`.
  - Trava transacional de banco de dados (`lockForUpdate`) mitigando race conditions.
  - `MembershipPolicy` robusta cruzada com a `Role Authority` para impedir que cargos rasos administrem suas chefias ou pares inviáveis.
- **Fora de Escopo:** Exclusão sumária do banco em caso de recusa (mantém-se o histórico como `rejected`).
- **PRs:** PR #33.

### ✅ Fase 2D — Adição Manual Segura
- **Objetivo:** Habilitar administradores locais a criarem diretamente a "identidade passiva" e injetar o usuário na Organização.
- **Entregas Principais:**
  - Inclusão manual vinculando `User` (existente ou novo gerado silenciosamente).
  - Configuração fina do Password Broker injetando o link via `AccountActivationNotification` de modo `afterCommit`.
  - Membro nasce como `active`, acessando a interface apenas após cadastrar senha (validação E2E).
  - Revisão visual e padronização da terminologia (Termos como "Tenant" expurgados da interface).
- **Fora de Escopo:** Gestão refinada da árvore organizacional hierárquica.
- **PRs:** PR #34.

### 📋 Fase 2E — Gestão Genérica e Hierárquica de Unidades Organizacionais
- **Objetivo:** Suportar hierarquias profundas e ilimitadas em `OrganizationUnit` definindo o raio de ação administrativo.
- **Estado Atual:** Apenas as balizas arquiteturais teóricas foram oficializadas (Decidido, mas ainda não Implementado).
- **Decisões Registradas:**
  - Árvore genérica de `OrganizationUnit`.
  - `parent_id` como mecanismo exclusivo de hierarquia.
  - `Membership` vinculado a unidade.
  - Refinamento do `OrganizationScope`.
  - Escopo global explícito (não atrelado a `NULL`).
  - Criação/edição/movimentação/exclusão de unidades.
  - Sidebar dinâmica pela árvore autorizada.
  - Controle de autoridade por escopo restrito.
- **Implementação e Entregas Previstas:** TBD. Nenhum código alterado.
- **Documentação Relacionada:** PR #35 (Docs), ADR-008, ADR-009.

---

## Lacunas de Documentação Conhecidas
- **State Machine de Membership:** As transições permitidas (ex: inativação após active, e reactivação) estão programadas no Serviço de Status, mas carecem de um Diagrama ou Documento de Estado consolidado exclusivo em `docs/`.
- **Exaustividade no Password Broker:** O uso atípico de token de reset nativo como ferramenta de "First Setup" (Identidade Passiva) não tem documento de ADR justificando a subversão arquitetural do framework.
