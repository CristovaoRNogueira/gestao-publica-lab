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

1. Definição da tarefa.
2. Análise arquitetural (leitura de `AGENTS.md` e ADRs relevantes).
3. Escolha do agente/modelo adequado ao risco e complexidade.
4. Criação de branch específica da tarefa.
5. Implementação.
6. Testes e validações.
7. Revisão independente quando necessário.
8. Pull Request.
9. Merge com autorização explícita do responsável pelo projeto.

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
