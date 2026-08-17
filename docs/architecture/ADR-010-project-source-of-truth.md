# ADR-010 — Repositório como Única Fonte de Verdade

**Status:** Aceito
**Data:** 2026-08-17

---

## Contexto

O Gestão Pública Lab é desenvolvido de maneira assíncrona e colaborativa por múltiplos agentes de Inteligência Artificial (Gemini, Claude, Codex) e desenvolvedores humanos. O histórico conversacional (prompts e respostas) sofre truncamento, apagamento temporal ou se perde entre diferentes sessões e agentes. Depender do conhecimento empírico guardado nas IAs para fundamentar código e decisões arquiteturais tem o potencial de gerar regressões severas e bifurcações não pretendidas.

---

## Decisão

O **repositório GitHub (seu código, testes e documentações formais)** constitui a ÚNICA fonte de verdade persistente do projeto.

O chat é um ambiente de coordenação efêmera, NÃO uma base de conhecimento duradoura.

**Ordem de Precedência Autorizativa:**
1. Decisões explícitas em comandos diretos do responsável pelo projeto.
2. Documentos arquiteturais (ADRs) vigentes em `docs/architecture/`.
3. Documentação técnica e operacional oficial em `docs/development/`.
4. Código fonte e testes do estado atual da branch estável.
5. Arquivos de Roadmap e Histórico consolidados.
6. *Prompts e sessões de agentes (Histórico volátil).*
7. *Memória e pre-treinamento da IA sobre o projeto (Conhecimento inferido).*

### Resolução de Inconsistências
Quando documentação formal e código divergirem frontalmente:
- Identificar a divergência.
- **NÃO** alterar o código automaticamente ou silenciosamente baseando-se apenas no que a documentação diz.
- Registrar a inconsistência explicitamente na resposta/relatório do chat.
- Indicar precisamente qual decisão ou documentação deve ser revisada (ou se o código exige refatoração autorizada).

---

## Consequências

**Positivas:**
- Elimina falhas oriundas de perda de histórico ("Janela de Contexto" ou "Truncamento").
- Padroniza o comportamento operacional de qualquer agente (Anthropic, OpenAI, Google) instanciado sob a plataforma Antigravity.

**Regra Definitiva:**
Fica terminantemente proibido que uma IA assuma como fato qualquer informação existente em memórias ou sessões paralelas quando tal fato estrutural deveria, compulsoriamente, constar documentado em ADR, Roadmap ou Testes no repositório.
