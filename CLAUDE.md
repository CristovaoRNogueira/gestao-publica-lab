# CLAUDE.md

Instruções específicas para o agente Claude neste repositório.

## Autoridade

- **Leia e siga `AGENTS.md` antes de qualquer tarefa.** `AGENTS.md` é a
  autoridade principal do repositório.
- Leia os ADRs relevantes em `docs/architecture/` antes de propor ou executar
  mudanças arquiteturais.
- Não contradiga decisões já registradas.
- Não altere decisões arquiteturais sem propor a mudança e obter aprovação
  explícita do responsável pelo projeto.

## Ambiente

- Use o ambiente Docker Compose (`workspace`) como ambiente canônico.
  Não dependa de ferramentas instaladas diretamente no host.
- Use `pnpm` como package manager oficial para dependências JavaScript/Node.js.

## Fluxo de trabalho

- Trabalhe sempre em uma branch específica da tarefa.
- Não edite simultaneamente o mesmo checkout que outro agente esteja usando.
- Não crie commits, faça push ou merge sem autorização explícita.
- Antes de mudanças significativas, analise o projeto e apresente um plano.
- Ao concluir, execute os testes e validações relevantes e reporte:
  - arquivos criados e modificados;
  - testes executados e seus resultados;
  - riscos ou pontos de atenção identificados.
