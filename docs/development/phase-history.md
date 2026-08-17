# Memória Técnica das Fases (Phase History)

Este documento registra de maneira concisa as decisões técnicas arquiteturais tomadas durante as implementações. O intuito é que estas informações substituam a dependência de "históricos de chat" entre IAs parceiras no desenvolvimento do projeto.

---

## Fase 1 — Fundação Base e Multi-Tenancy

### Implementado
- Configuração do arcabouço Laravel em formato Modular Monolith.
- Estrutura base de identificação: tabela de `users`, `tenants` e pilar `memberships`.

### Decisões Relevantes
- Abandono de abordagens baseadas em Microserviços (ADR-001) e adoção explícita do isolamento lógico nos esquemas de banco (ADR-002).

### Referências
- ADR-001, ADR-002, ADR-004

---

## Fase 2A — Identidade Global de Usuário

### Implementado
- Cadastro público inicial e fluxo nativo de Password Broker para envio do link de recuperação.

### Decisões Relevantes
- O User é estritamente apartado do Tenant em nível de Identity (a tabela de usuário não armazena `tenant_id` - ADR-003). Isto abriu caminho para futuras conexões de Múltiplos Tenants.

### Fora de escopo
- Lógicas que obrigassem o usuário recém-cadastrado a fundar uma nova organização automaticamente.

---

## Fase 2B — Convites e Onboarding

### Implementado
- Sistema assíncrono de convites baseados no endereço de e-mail e rotas para registro através de invite.
- O membro entra no Tenant invariavelmente restrito ao status `pending`.

### Validação
- O fluxo de onboarding passou a interceptar usuários existentes no banco, confirmando se seu e-mail global coincidia.
- O `OrganizationScope` foi rascunhado para suportar os limites de lotação e barrar convites maliciosos para filiais indevidas.

### Referências
- PR #27, PR #31, PR #32

---

## Fase 2C — Aprovação e Rejeição de Memberships

### Implementado
- Fluxo gerencial autorizando que os status deixem de ser `pending` para virarem `active` ou `rejected`.

### Segurança
- **Database Lock:** Operações de mutação de state da `Membership` são atreladas a transações com `lockForUpdate()`. Uma tentativa de alterar concomitantemente um membership recém-alterado retorna de imediato ou falha com segurança, mitigando race conditions em endpoints clicados sucessivamente.
- **Role Authority (Semente):** Iniciada a trava lógica onde administradores não podem atuar verticalmente contra seus superiores ou equivalentes dotados de maiores Capabilities.

### Referências
- PR #33

---

## Fase 2D — Adição Manual Segura

### Implementado
- Intervenção administrativa onde se pula a etapa de convite direto. User novo nasce como identidade passiva; Membership é criado em status `active`; acesso efetivo ao sistema depende da definição inicial de senha.
- Rotas visuais do Dashboard tratadas perante o status negativo (`pending`/`rejected`) escondendo painéis restritos (Sidebar inteligente).

### Segurança e Testes
- **First Time Pass Setup:** Descobriu-se, mediante erro, que criar um token via Password Broker de modo solto resultava em e-mails contendo tokens fantasma se a transação do commit global falhasse. Foi então corrigido: token é montado dentro do `DB::transaction()` mas o disparo (Notification) percorrido imperativamente pelo `DB::afterCommit()`.

### Limitações e Descobertas
- **Vulnerabilidade de Escopo Ancorada:** O `OrganizationScope` (que já existia e foi utilizado nas fases 2B, 2C e 2D, com regras progressivamente ampliadas) possuía, conforme identificado durante a homologação da 2D, a interpretação perigosa de `organization_unit_id = NULL` como escopo global automático. O ADR-008 formalizou a nova decisão; a mudança de código para retirar o "magic null" ainda está pendente e será implementada na Fase 2E.

### Fora de Escopo
- Mudanças sistêmicas na hierarquia de banco da `OrganizationUnit` (Pendente Fase 2E).

### Referências
- PR #34
