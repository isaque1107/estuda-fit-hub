<?php

declare(strict_types=1);

namespace Cobranca\Services;

use Cobranca\Contracts\GatewayDePagamento;
use Cobranca\Support\Database;
use Cobranca\Support\Gateway;
use Cobranca\Support\HttpException;
use PDOException;
use Throwable;

final class EmitirFatura
{
    public function __construct(private readonly GatewayDePagamento $gateway = new Gateway())
    {
    }

    public function executar(array $dados): ResultadoDaEmissao
    {
        $matriculaId = (int) ($dados['matricula_id'] ?? 0);
        $alunoId = (int) ($dados['aluno_id'] ?? 0);
        $competencia = $this->competencia((string) ($dados['competencia'] ?? ''));
        $vencimento = $this->vencimento((string) ($dados['vencimento'] ?? ''));
        $valor = $this->valor((string) ($dados['valor'] ?? ''));

        if ($matriculaId <= 0 || $alunoId <= 0) {
            throw new HttpException(422, 'matricula_id e aluno_id devem ser inteiros positivos');
        }

        $existente = $this->buscar($matriculaId, $competencia);

        if ($existente !== null) {
            return ResultadoDaEmissao::jaExistia($existente);
        }

        try {
            $id = Database::inserir('faturas', [
                'matricula_id' => $matriculaId,
                'aluno_id' => $alunoId,
                'competencia' => $competencia,
                'valor' => $valor,
                'vencimento' => $vencimento,
            ]);
        } catch (PDOException $erro) {
            if (! Database::violouChaveUnica($erro)) {
                throw $erro;
            }

            return ResultadoDaEmissao::jaExistia($this->buscar($matriculaId, $competencia));
        }

        $this->registrarNoGateway($id);

        return ResultadoDaEmissao::emitida($this->buscar($matriculaId, $competencia));
    }

    private function registrarNoGateway(int $faturaId): void
    {
        try {
            Database::executar(
                'UPDATE faturas SET gateway_ref = ? WHERE id = ?',
                [$this->gateway->registrarCobranca($faturaId), $faturaId],
            );
        } catch (Throwable $erro) {
            error_log(sprintf('gateway indisponivel para a fatura %d: %s', $faturaId, $erro->getMessage()));
        }
    }

    private function buscar(int $matriculaId, string $competencia): ?array
    {
        return Database::buscarUm(
            'SELECT id, matricula_id, aluno_id, competencia, valor, vencimento, status, pago_em, gateway_ref
             FROM faturas WHERE matricula_id = ? AND competencia = ?',
            [$matriculaId, $competencia],
        );
    }

    private function competencia(string $valor): string
    {
        if (preg_match('/^(\d{4})-(\d{2})(-\d{2})?$/', $valor, $partes) !== 1) {
            throw new HttpException(422, 'competencia deve estar no formato YYYY-MM');
        }

        return sprintf('%s-%s-01', $partes[1], $partes[2]);
    }

    private function vencimento(string $valor): string
    {
        $formatoValido = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) === 1;

        if (! $formatoValido || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw new HttpException(422, 'vencimento deve ser uma data YYYY-MM-DD válida');
        }

        return $valor;
    }

    private function valor(string $valor): string
    {
        if (! is_numeric($valor) || (float) $valor <= 0) {
            throw new HttpException(422, 'valor deve ser numérico e maior que zero');
        }

        return $valor;
    }
}
