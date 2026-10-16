# Estuda Fit Hub — extração do domínio de Cobrança

Resposta ao desafio descrito em [DESAFIO.md](DESAFIO.md). O sistema base está documentado em [docs/README-monolito.md](docs/README-monolito.md).

## Os documentos

| Documento | O que responde |
|---|---|
| [docs/ADR-001-extracao-cobranca.md](docs/ADR-001-extracao-cobranca.md) | fronteira, contrato, dados, rollout e riscos |
| [docs/PLANO-MIGRACAO.md](docs/PLANO-MIGRACAO.md) | faturas históricas, relatórios e corte do cron mensal |

## A ideia, em quatro frases

1. O serviço de Cobrança é dono das faturas e dos pagamentos, no banco dele.
2. Quando a inadimplência de um aluno muda, ele grava um aviso numa tabela própria, **na mesma transação** que mudou a fatura.
3. Um processo entrega esses avisos ao monólito, que aplica a política e grava `alunos.situacao`.
4. A catraca lê essa coluna e **nunca mais consulta a tabela de faturas**.

A decisão que sustenta o resto: Cobrança informa o fato — *quantos dias o aluno está em atraso* — e quem decide bloquear é o monólito. Assim `alunos.situacao` volta a ter um escritor só.

## O que foi entregue

| Onde | O quê |
|---|---|
| `cobranca-service/` | serviço novo, banco próprio: emitir fatura, receber webhook do gateway, varredura de inadimplência e caixa de saída |
| `src/Controllers/CheckinController.php` | **ponto B**: deixou de consultar `faturas` |
| `src/Services/AplicarAvisoDeCobranca.php` | recebe o aviso, aplica o limiar e grava `alunos.situacao` |
| `tests/` e `cobranca-service/tests/` | 41 testes, 0 falhas |

## Como rodar

```bash
docker compose up -d --build
docker compose exec app php bin/console db:migrate
docker compose exec app php bin/console db:seed
docker compose exec cobranca php bin/console db:migrate
```

| | Endereço |
|---|---|
| Monólito | `http://localhost:8080` |
| Serviço de Cobrança | `http://localhost:8081` |
| MySQL do monólito | `localhost:3316` — `estuda_fit_hub` |
| MySQL de Cobrança | `localhost:3317` — `cobranca` |

São dois bancos em containers separados. O serviço não tem credencial para o banco do monólito, então a restrição do enunciado é estrutural e não uma convenção.

Testes:

```bash
docker compose exec app php tests/run.php
docker compose exec cobranca php tests/run.php
```

Atalhos no `Makefile`: `make up`, `make test`, `make cobranca-test`, `make cobranca-varredura`, `make cobranca-entrega`.

## Verificando em três minutos

### 1. A catraca não consulta mais faturas

```bash
docker compose exec app php tests/run.php testCheckinNaoConsultaMaisATabelaDeFaturas
```

Cria uma fatura vencida há 30 dias direto na tabela e afirma que a catraca **libera** o aluno. Sem aviso de Cobrança o monólito não tem como saber da dívida — é a prova de que o desacoplamento é real.

### 2. Webhook duplicado não cobra duas vezes

```bash
docker compose exec cobranca php tests/run.php testAvisoDuplicadoNaoRegistraSegundoPagamento
```

O monólito tem um teste que documenta o bug de propósito (`testWebhookDuplicadoRegistraPagamentoDuasVezesComportamentoAtual`). O serviço novo não o repete: uma fatura paga não volta a ficar aberta, então o reenvio não encontra o que fazer.

### 3. O aviso sobrevive ao monólito fora do ar

```bash
MID=$(docker compose exec -T db mysql -uestuda_fit_hub -pestuda_fit_hub estuda_fit_hub -N -B -e "SELECT id FROM matriculas WHERE aluno_id=7 LIMIT 1")

curl -s -X POST localhost:8081/faturas -H 'Content-Type: application/json' \
  -d "{\"matricula_id\":$MID,\"aluno_id\":7,\"competencia\":\"2026-10\",\"valor\":\"99.90\",\"vencimento\":\"2026-10-01\"}"

docker compose stop app
docker compose exec cobranca php bin/console cobranca:varredura
docker compose exec cobranca php bin/console cobranca:entregar-avisos

docker compose exec cobranca-db mysql -ucobranca -pcobranca cobranca \
  -e "SELECT aluno_id, dias_de_atraso, entregue_em, tentativas FROM caixa_de_saida"

docker compose start app
docker compose exec cobranca php bin/console cobranca:entregar-avisos
```

O aviso fica pendente com a tentativa registrada enquanto o monólito está fora, e é entregue quando ele volta. O tempo de resposta do webhook é o mesmo nos dois casos — medido em ~29 ms com o monólito de pé e ~28 ms com ele parado — porque a requisição não faz mais chamada de rede.

## Decisões tomadas por falta de tempo

**`POST /faturas` não tem chamador no código.** O contrato está entregue e coberto por teste, mas quem vai chamá-lo é o cron mensal do monólito, na fase 4 do plano. Nas fases 2 e 3 o monólito ainda escreve as faturas localmente, então ainda não chama.

**A entrega de avisos para no primeiro erro.** Isso preserva a ordem — "quitou" não pode ultrapassar "está devendo" — mas significa que um aviso que falhe permanentemente trava a fila. A saída é uma fila de descarte depois de N tentativas, e não foi implementada.

**Só o ponto B foi alterado no monólito.** Os pontos A, C, D, E, F, G e H continuam como estão, porque o enunciado pede alteração apenas no check-in. Cada um tem sua fase no plano de migração.

**A cópia das faturas históricas não foi implementada.** O plano escolhe carga inicial mais captura contínua de mudanças, que é infraestrutura e não código de aplicação. O tempo dessa cópia não foi medido, e é o maior risco de cronograma do plano.

**Unificar o limiar em 5 dias muda comportamento.** O aluno passa a ser bloqueado mais cedo e, como `MatriculaService` recusa matrícula de aluno bloqueado, também perde mais cedo a chance de se rematricular. Apertar a régua é decisão de Finanças, e esse aval está pendente.

## Testes existentes que mudaram

O enunciado permite ajustar os testes existentes com justificativa. Dois mudaram, os dois em `tests/CheckinTest.php`.

`testFaturaVencidaHaMaisDeCincoDiasBloqueiaCatraca` montava o cenário criando uma fatura vencida direto na tabela. Como o check-in não consulta mais essa tabela, o cenário passou a ser montado pelo aviso de Cobrança, que é como o bloqueio acontece agora. O teste virou `testAtrasoAcimaDaToleranciaBloqueiaCatraca`.

`testFaturaVencidaRecenteAindaLibera` tinha o mesmo problema e virou `testAtrasoDentroDaToleranciaLibera`.

Em ambos, `motivo_bloqueio` deixou de distinguir inadimplência de bloqueio cadastral: agora é sempre `situacao_bloqueado`. O check-in não sabe mais por que o aluno está bloqueado — e não precisa saber. Quem quiser o detalhe financeiro pergunta a Cobrança.

## O que faria a seguir

**Trocar HTTP por broker no processo de entrega.** A caixa de saída continua existindo — gravar no banco e publicar no broker também são duas escritas sem transação em comum. O que o broker resolve é a repetição por mensagem e a fila de descarte, que elimina o travamento de fila descrito acima.

**Dar um chamador ao `POST /faturas`.** O cron mensal passa a entregar ao serviço o que precisa ser faturado na competência, e o trabalho demorado sai do monólito. É a fase 4 do plano.

**Resolver os demais pontos de acoplamento**, na ordem do plano: o webhook do gateway apontando para o serviço, depois a primeira fatura da matrícula, a régua, os relatórios, o cancelamento e a tela do aluno.

**Agendar os comandos.** `cobranca:varredura` e `cobranca:entregar-avisos` hoje são executados à mão. Em produção o primeiro é diário e o segundo roda continuamente.
