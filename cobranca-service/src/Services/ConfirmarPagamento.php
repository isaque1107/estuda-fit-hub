<?php

declare(strict_types=1);

namespace Cobranca\Services;

use Cobranca\Enums\ResultadoDaConfirmacao;
use Cobranca\Support\Database;
use Cobranca\Support\HttpException;
use Throwable;

final class ConfirmarPagamento
{
    private const APROVADO = 'approved';

    public function __construct(private readonly AvisarInadimplencia $aviso = new AvisarInadimplencia())
    {
    }

    public function executar(array $dados): ResultadoDaConfirmacao
    {
        $referencia = (string) ($dados['reference'] ?? '');
        $situacao = (string) ($dados['status'] ?? '');

        if ($referencia === '' || $situacao === '') {
            throw new HttpException(422, 'reference e status são obrigatórios');
        }

        $fatura = $this->buscar($referencia);

        if ($fatura === null) {
            throw new HttpException(404, sprintf('Nenhuma fatura com a referência %s', $referencia));
        }

        if ($situacao !== self::APROVADO) {
            return ResultadoDaConfirmacao::NaoAprovado;
        }

        if ($fatura['status'] === 'paga') {
            return ResultadoDaConfirmacao::JaEstavaPaga;
        }

        $this->registrar($fatura, $dados);
        $this->avisarOMonolito((int) $fatura['aluno_id']);

        return ResultadoDaConfirmacao::Pago;
    }

    private function avisarOMonolito(int $alunoId): void
    {
        try {
            $this->aviso->paraOAluno($alunoId);
        } catch (Throwable $erro) {
            error_log(sprintf('aviso imediato falhou para o aluno %d: %s', $alunoId, $erro->getMessage()));
        }
    }

    private function registrar(array $fatura, array $dados): void
    {
        $pagoEm = $this->momentoDoPagamento($dados);

        Database::transacao(static function () use ($fatura, $dados, $pagoEm): void {
            Database::executar(
                "UPDATE faturas SET status = 'paga', pago_em = ? WHERE id = ? AND status <> 'paga'",
                [$pagoEm, $fatura['id']],
            );

            Database::inserir('pagamentos', [
                'fatura_id' => $fatura['id'],
                'valor' => (string) ($dados['amount'] ?? $fatura['valor']),
                'metodo' => (string) ($dados['method'] ?? 'desconhecido'),
                'gateway_payload' => json_encode($dados, JSON_UNESCAPED_UNICODE),
            ]);
        });
    }

    private function momentoDoPagamento(array $dados): string
    {
        $informado = strtotime((string) ($dados['occurred_at'] ?? ''));

        return date('Y-m-d H:i:s', $informado === false ? time() : $informado);
    }

    private function buscar(string $referencia): ?array
    {
        return Database::buscarUm(
            'SELECT id, aluno_id, valor, status FROM faturas WHERE gateway_ref = ?',
            [$referencia],
        );
    }
}
