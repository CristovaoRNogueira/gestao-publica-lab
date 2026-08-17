# AI Development Workflow

## Objetivo

O Gestão Pública Lab utiliza múltiplos agentes e modelos de IA como pares de
desenvolvimento. Git e documentação são os mecanismos de coordenação entre eles.

Cada agente recebe a especificação da tarefa e trabalha de forma autônoma,
dentro das regras estabelecidas em `AGENTS.md` e nos ADRs.

---

## Agentes e plataformas

| Agente / Plataforma | Papel |
|---|---|
| **OpenAI Codex** | Agente independente de implementação e revisão |
| **Google Antigravity** | Plataforma de execução multi-modelo (Gemini e outros) |
| **Claude** | Agente principal de desenvolvimento |
| **Gemini** | Modelos disponibilizados pelo Antigravity |

Nenhum agente possui autoridade superior aos demais.

A autoridade do projeto é definida pelo responsável pelo projeto, por
`AGENTS.md`, pelos ADRs em `docs/architecture/` e pelas especificações
técnicas do projeto.

---

## Roteamento de modelos

A seleção do modelo é baseada na **complexidade e risco da tarefa**, não em
preferência fixa por agente.

### Gemini Flash

Tarefas simples, rápidas e de baixo risco.

### Claude Sonnet Thinking

Modelo padrão para desenvolvimento normal.

### Claude Opus Thinking

Tarefas críticas, arquiteturais, de segurança, decisões de alto impacto e
problemas complexos.

### Gemini Pro

Revisão independente.

### Codex

Revisão e implementação independente.

---

## Processo de trabalho

1. Ler `AGENTS.md`.
2. Identificar a fase/tarefa.
3. Ler `docs/development/roadmap.md`.
4. Ler `docs/development/phase-history.md` quando relevante.
5. Identificar e ler ADRs aplicáveis (`docs/architecture/`).
6. Ler contratos técnicos da área.
7. Auditar código existente.
8. Auditar migrations/schema.
9. Auditar testes existentes.
10. Identificar riscos e lacunas.
11. Implementar apenas o escopo autorizado.
12. Executar testes.
13. Executar build quando houver frontend.
14. Executar `git diff --check`.
15. Relatar arquivos alterados.
16. Não criar commit.
17. Não fazer push.
18. Não fazer merge.
19. Revisões devem ser independentes e não modificar código sem autorização.

## Regras de Confiabilidade de Conhecimento

- **Uma IA NÃO deve assumir como fato qualquer informação existente somente em outro chat, outra sessão de agente ou memória externa quando a informação deveria estar registrada no repositório.**
- **Quando uma decisão importante não estiver documentada, o agente deve interromper a implementação e solicitar orientação, ou registrar a decisão formalmente antes de implementar.**

---

## Regras de isolamento

- Agentes diferentes não editam simultaneamente o mesmo checkout.
- Para trabalho simultâneo, utilizar branches ou worktrees separados.
- Cada branch representa uma tarefa, não um agente permanente.

---

## Revisão

A revisão independente deve ocorrer sem modificar o código revisado, salvo
autorização explícita do responsável pelo projeto.

---

## Permissões operacionais

| Operação | Configuração |
|---|---|
| Overages | OFF |
| Terminal | Request Review |
| Commits / push / merge | Somente com autorização explícita |

---

## Ambiente

- Docker Compose (`workspace`) é o ambiente canônico de desenvolvimento.
- `pnpm` é o package manager oficial para dependências JavaScript/Node.js.
- Ferramentas do projeto devem ser executadas dentro do serviço `workspace`.
  Não depender de PHP, Composer, Node.js, pnpm ou PostgreSQL instalados no host.

---

## Princípio

A seleção do modelo é baseada na complexidade e risco da tarefa, não em
preferência fixa pelo agente.
