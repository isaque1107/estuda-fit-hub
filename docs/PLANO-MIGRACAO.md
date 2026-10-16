# Plano de migração e corte

Complementa o [ADR-001](ADR-001-extracao-cobranca.md), que decide a fronteira e o contrato. Aqui está como as faturas antigas chegam ao serviço, o que acontece com os relatórios, e como o cron do dia 1º troca de dono.

## Faturas históricas

**Transporte: carga inicial mais captura contínua de mudanças** — DMS, Debezium ou equivalente, conforme a infraestrutura disponível. A escolha é pelo mecanismo, não pelo produto.

A carga inicial copia as ~14 milhões de linhas em paralelo, com retomada. A captura contínua lê o log de transações do monólito e replica tudo que muda depois disso — o que resolve sozinho o problema das faturas criadas ou alteradas enquanto a cópia roda. O corte acontece quando o atraso de replicação chega perto de zero.

Escrever isso à mão seria reimplementar um produto. Paralelismo, retomada, monitoramento de atraso e tratamento de erro linha a linha já vêm prontos.

**A ferramenta resolve o transporte, não a migração.** Ela coloca os dados lá. Ela não decide quando o monólito para de escrever, não garante que a catraca continue respondendo e não oferece reversão. Isso continua sendo trabalho do plano.

**Conferência por competência, logo depois de cada lote.** Contagem de faturas e soma de valores precisam bater nos dois bancos para aquela competência. São 250 mil linhas por vez em vez de 14 milhões, e uma divergência aponta direto para o mês onde está o problema.

**A exceção de acesso.** A captura lê o banco do monólito. É a única exceção à regra de que o serviço não lê de lá, e ela é de terceiro: quem lê é a ferramenta de replicação, somente leitura, e o acesso é revogado quando o corte termina.

## Relatórios gerenciais

Os painéis não podem parar em etapa nenhuma, e o enunciado autoriza atraso de alguns minutos.

Depois que `faturas` sai do monólito, os relatórios passam a **consultar o serviço**, que devolve os números **já agregados** — somados por matrícula, não linha a linha. O monólito cruza esse agregado com `planos` e `unidades`, que continuam dele. Cada lado agrega o que é seu, e trafega um punhado de linhas em vez das 250 mil faturas de uma competência.

Se o serviço não responder, o monólito serve o último resultado bom de cada painel, marcado com a hora em que foi obtido. O relatório não para: ele fica atrasado, que é o que a restrição permite.

A alternativa seria manter uma cópia das faturas dentro do monólito. Foi descartada porque cópia de dado é justamente o que produz divergência — duas verdades que um dia discordam.

## Cron mensal

**Como é hoje.** Um comando no monólito percorre todas as matrículas ativas, cria uma fatura por vez e chama o gateway **em série**, uma chamada por fatura, 250 mil vezes. O que torna o dia 1º arriscado não é o laço em si — é o tempo que ele leva, porque qualquer deploy nesse intervalo o interrompe.

**Como fica.** No dia 1º o monólito identifica o que precisa ser faturado naquela competência e **entrega ao serviço em lotes**. O serviço emite as faturas e registra a cobrança no gateway no próprio ritmo, em paralelo.

O que muda de verdade é que **o trabalho demorado sai do monólito**. O que sobra lá é uma varredura rápida e um envio. A janela em que um deploy atrapalha encolhe de horas para minutos — e mesmo dentro dela, interromper não causa dano: reenviar a mesma competência pula o que já existe, pela chave de matrícula e competência. Não há estado de progresso a gerenciar, porque a chave é o controle.

O serviço não guarda cópia de matrículas. A lista é daquela competência, não um espelho permanente, o que mantém a fronteira do ADR intacta: o serviço continua sem consultar `planos` nem `matriculas`.

### O corte, em duas competências

| | O que roda | Reversão |
|---|---|---|
| **M1 — sombra** | o monólito gera de verdade; o serviço gera no banco dele, sem registrar no gateway e sem notificar ninguém | apagar as faturas-sombra |
| **M2 — corte** | o cron antigo é desligado, não removido; o serviço assume a geração | reativar o cron antigo, que pula o que já existe |

Para avançar de M1 para M2, as duas gerações precisam bater: mesma quantidade de faturas, mesmo valor, mesmo vencimento, mesma matrícula. Qualquer divergência vira bug antes de avançar. O cron antigo só sai do código depois de uma competência inteira rodando pelo serviço.

**Isso não dá para acelerar.** O dia 1º acontece uma vez por mês, então o corte leva duas competências de calendário. O único teste que vale é o do dia real, com as matrículas reais.
