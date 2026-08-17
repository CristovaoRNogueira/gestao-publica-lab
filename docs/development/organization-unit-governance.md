# Governança de Unidades Organizacionais e Autoridade (Guia Prático)

Este documento atua como o guia canônico de implementação e manutenção das regras de estrutura organizacional, controle de escopo e hierarquias da plataforma Gestão Pública Lab. Ele visa orientar desenvolvedores, agentes de IA e futuras atualizações estruturais.

---

## REGRAS QUE NÃO PODEM SER QUEBRADAS

A arquitetura atual possui travas baseadas nos seguintes princípios fundamentais:

1. **Nunca assumir que existe "Secretaria".** Nomes de unidades são arbitrários.
2. **Nunca assumir que Departamento depende de Secretaria.** Qualquer unidade pode possuir qualquer pai na árvore.
3. **Nunca usar o nome de uma Role para autorização.** Não use `if ($role->name === 'Admin')`. Valide _capabilities_ e permissões.
4. **Nunca tratar `NULL` de `organization_unit_id` como privilégio por si só.** `NULL` não é chave mestra.
5. **Nunca confiar somente no frontend.** UI é para UX e apresentação. Regras de segurança nunca devem residir exclusivamente lá.
6. **Toda autorização deve ser validada no backend.** Em controllers, policies e services.
7. **Cross-tenant sempre deve falhar.** Um membro da Org A nunca deve conseguir acessar, ler ou mutar dados da Org B. Validação imperativa de isolamento.
8. **Admin local não pode subir ou atravessar a árvore.** Uma tentativa de atuar fora do seu escopo (parent, sibling ou unidades órfãs relativas a ele) deve retornar HTTP 403 Forbidden.
9. **SaaS Admin não é Membership.** O SaaS Admin não é um usuário privilegiado num Tenant, mas uma entidade independente superior gerida pelas tabelas _Platform_.
10. **Não implementar impersonation sem ADR próprio.** Assumir as sessões de outros usuários é um risco grave sem especificação arquitetural anterior.
11. **Membership pertence a uma `OrganizationUnit` quando aplicável.** É o *onde*, atrelando o vínculo ao escopo.
12. **Sidebar deve refletir dinamicamente a árvore autorizada.** Baseie a visibilidade de menus nas reais capacidades do atuante, omitindo o que não é lícito.
13. **Novas unidades devem aparecer sem alteração de código frontend.** A plataforma é Data-Driven. O layout se adapta à árvore vinda da API.
14. **A árvore é definida por `parent_id`.** Ele é o único indicador estrutural de quem herda e pertence a quem.
15. **Type não define hierarquia.** O campo tipo é meramente textual ou de catálogo. Não confie a árvore de permissões a este atributo.

---

## EXEMPLOS

**✅ Exemplo Válido:**
Um usuário que atua no RH (`parent_id = Secretaria da Administração`) cria uma Subcoordenação indicando o RH como nó-pai (`parent_id`). Ele o faz usando uma Role cujas capabilities contenham a permissão de criação de estruturas organizacionais, enquanto o seu Escopo Organizacional (`OrganizationScope`) atesta que RH é filho da própria Unidade ou ela mesma.

**❌ Exemplo Inválido (Violação):**
O mesmo usuário que atua no RH tenta editar o nome da Secretaria da Administração.
*Motivo da falha:* A Secretaria da Administração é o `parent` do RH. A regra "Admin local não pode subir a árvore" impede essa ação, resultando em _403 Forbidden_.

**❌ Exemplo Inválido (Violação de Role):**
O código autoriza a alteração de permissões através de `if ($user->hasRole('Super Administrador')) { ... }`.
*Motivo da falha:* Feriu a regra "Nunca usar o nome de uma Role". O nome pode ter sido alterado pelo usuário legitimamente na UI. A verificação correta exige o check no conjunto de permissões (Capabilities).

---

## FLUXO DE AUTORIZAÇÃO

O modelo da aplicação consolida a requisição de acordo com o seguinte funil em cascata:

**SaaS Authority**
*(Plataforma Geral e Plataforma de Contratos/Licenciamentos. Avaliado independentemente).*
↓
**Organization Authority**
*(Acesso primário validado via middleware/contexto ao tentar ingressar no Tenant).*
↓
**Organization Unit Scope**
*(Restrição espacial calculada sobre a árvore usando `parent_id` do target e origens da sessão logada).*
↓
**Membership**
*(Consolidação das regras de status da pessoa e suas restrições temporais e contratuais ativas com aquele Tenant).*
↓
**Role / Permission**
*(Selecão granular das capacidades técnicas e verbos permitidos sobre o modelo em questão).*

---

## TERMINOLOGIA

Para garantir a coerência cognitiva entre devs, analistas e documentação, todo nome técnico possui um análogo nominal de interface que não pode ser desrespeitado.

* **Tenant** → Organização
* **Membership** → Membro / Vínculo
* **Role** → Função
* **Permission** → Permissão
* **OrganizationUnit** → Unidade Organizacional

**Observação:** Termos internos como _Tenant_, _Membership_, etc., **podem e devem** existir no código-fonte, banco de dados e URLs internas, mas **não devem vazar para a UI** em mensagens de flash, toasts ou telas de erro direcionadas ao usuário final.

---

## Documentos relacionados
- ADR-006-tenant-scoped-rbac.md
- ADR-008-organizational-hierarchy-and-scope.md
- ADR-009-saas-and-organization-administration.md
