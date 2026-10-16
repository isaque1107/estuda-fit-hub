# ADR-001 — Extração do domínio de Cobrança

**Status:** proposto · **Data:** 2026-10-16

## Contexto

Cobrança é o módulo com mais incidentes do monólito. O cron do dia 1º gera 250 mil faturas e pode ser derrubado por qualquer deploy, inclusive um que só mexeu em Relatórios. Quando o gateway de pagamento fica lento, o webhook consome a capacidade do sistema e o check-in das catracas começa a falhar. E Finanças não consegue evoluir regras de cobrança sem entrar na fila de deploy do monólito.

A liderança decidiu extrair Cobrança para um serviço independente, mantendo o monólito em produção durante toda a transição, sem janela de indisponibilidade.

## 1. Fronteira

| Dado | Fica onde | Por quê |
|---|---|---|
| `faturas` | **Cobrança** | é o objeto central do domínio extraído |
| `pagamentos` | **Cobrança** | um pagamento só existe por causa de uma fatura |
| `planos.valor_mensal` | monólito | preço é do catálogo, e vai por valor no pedido de emissão |
| `matriculas.dia_vencimento` | monólito | é do contrato comercial, e vai por valor no pedido de emissão |
| `alunos.situacao` | monólito | é estado cadastral do aluno |

**O serviço nunca consulta `planos` nem `matriculas`.** Quem pede a emissão informa o valor e o vencimento, e a fatura guarda esses dados. A fatura registra o preço *praticado*, não o preço vigente: se o plano reajustar em abril, a fatura de março não muda. Isso é regra de negócio, não otimização.

**Cobrança deixa de escrever em `alunos.situacao`.** Hoje quatro lugares escrevem nessa coluna, metade deles por conta de cobrança, a régua bloqueia o inadimplente, o webhook desbloqueia quem pagou. Depois da extração, Cobrança apenas **informa o fato**: quantos dias o aluno está em atraso. Quem decide bloquear é o monólito. A coluna volta a ter um escritor só.

## 2. Contrato

**Monólito → Cobrança: chamada HTTP com resposta.** Emitir e cancelar fatura são pedidos. Nenhum deles está no caminho da catraca. O webhook do gateway passa a chegar direto no serviço, que é quem agora é dono da fatura.

**Cobrança → monólito: aviso assíncrono.** O monólito nunca consulta Cobrança durante um check-in. Quando a inadimplência de um aluno muda, Cobrança avisa, e o monólito grava o resultado em `alunos.situacao`. A catraca lê essa coluna do próprio banco: sem rede, sem JOIN, e respondendo igual com o serviço de Cobrança fora do ar.

**Limiar único.** Hoje a catraca barra com 5 dias de atraso e a régua bloqueia o cadastro com 10. Tratamos isso como inconsistência acumulada, não como regra deliberada, e unificamos em **5 dias** — o limiar mais rígido dos dois, porque afrouxar cobrança é decisão de Finanças e não de arquitetura. O teste que fixava o comportamento antigo muda de expectativa, com a justificativa registrada no README.

**Quem aplica o limiar é o monólito.** Cobrança publica o fato; o monólito decide se aquilo vira `bloqueado`. É isso que mantém `alunos.situacao` com um escritor só.

**O aviso pode chegar repetido.** Tanto o webhook do gateway quanto o aviso de Cobrança podem ser reenviados — o remetente que não recebe confirmação tenta de novo. Os dois carregam um identificador único, e quem recebe ignora o que já processou.

## 3. Dados

As ~14 milhões de faturas e seus pagamentos terminam no **banco do serviço de Cobrança**, que é consequência direta da fronteira: se a fatura é de Cobrança, ela mora com Cobrança.

Chegam lá por **cópia em lotes, competência a competência, da mais antiga para a mais nova**. Os meses fechados já estão pagos ou cancelados e não mudam mais, então não existe corrida com escrita viva. Os meses correntes migram por último, já dentro da janela de corte.

Cada lote é conferido **logo depois de copiado** — contagem de faturas e soma de valores precisam bater nos dois bancos, para aquela competência. A conferência é por competência e não uma varredura final: são 250 mil linhas por vez em vez de 14 milhões, e uma divergência aponta direto para o mês onde está o problema.

**A cópia vem antes de qualquer leitura virar.** Enquanto o serviço não tiver as faturas, ele não sabe quem está inadimplente — e um monólito confiando num serviço vazio liberaria a catraca para todo mundo que deve.

## 4. Rollout e reversão

Cinco etapas, nesta ordem. Nenhuma delas exige parar o sistema.

1. **Serviço no ar e cópia do histórico.** O serviço existe, recebe as faturas antigas, e ninguém depende dele. Comportamento inalterado.
2. **Cobrança passa a avisar.** O monólito recebe o fato e grava `alunos.situacao` com o limiar de 5 dias. É a única etapa que muda comportamento visível, e muda de propósito.
3. **O check-in para de consultar `faturas`.** Como a coluna já está correta desde a etapa 2, o resultado da catraca não muda. A mudança entra por unidade: uma academia, depois cinco, depois as 40.
4. **Cobrança vira dona da escrita.** O webhook do gateway aponta para o serviço e o cron mensal roda lá. O monólito para de escrever faturas.
5. **A tabela `faturas` sai do monólito**, depois de um período de estabilidade.

**Reversão.** As unidades que já usam o caminho novo ficam numa variável de ambiente. Virar uma academia é acrescentar um id; voltar atrás é apagar — segundos, sem deploy. Isso importa porque o próprio enunciado diz que qualquer deploy do monólito pode derrubar o cron do dia 1º: a reversão não pode depender justamente da operação mais arriscada do sistema.

As etapas 1 a 3 revertem assim. A 4 já escreveu dado do outro lado, e por isso só acontece depois de duas etapas de evidência. A 5 é definitiva, e por isso é a última.

## 5. Riscos e o que não resolvi

**Unificar o limiar em 5 dias muda comportamento.** O aluno passa a ser bloqueado mais cedo e, como `MatriculaService` recusa matrícula de aluno bloqueado, também perde mais cedo a chance de se rematricular. Apertar a régua de cobrança é decisão de Finanças e não de arquitetura — esse aval está pendente.

**Não medi quanto tempo leva copiar 14 milhões de faturas.** É o maior risco de cronograma do plano, e precisa de um ensaio cronometrado antes de qualquer promessa de data.

**Só o check-in mudou no monólito.** Os outros sete pontos de acoplamento — a primeira fatura nascendo dentro da transação da matrícula, o cron mensal, a régua, os relatórios, o cancelamento e a tela do aluno — estão mapeados e ficaram para as etapas 4 e 5.
