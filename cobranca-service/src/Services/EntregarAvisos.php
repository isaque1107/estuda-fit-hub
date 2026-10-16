<?php

declare(strict_types=1);

namespace Cobranca\Services;

use Cobranca\Contracts\NotificadorDeInadimplencia;
use Cobranca\Support\Database;
use Cobranca\Support\Monolito;
use Throwable;

final class EntregarAvisos
{
    private const LOTE = 100;

    public function __construct(private readonly NotificadorDeInadimplencia $monolito = new Monolito())
    {
    }

    public function executar(): ResultadoDaEntrega
    {
        $entregues = 0;

        foreach ($this->pendentes() as $aviso) {
            try {
                $this->monolito->avisarInadimplencia(
                    (int) $aviso['aluno_id'],
                    (int) $aviso['dias_de_atraso'],
                );
            } catch (Throwable $erro) {
                $this->registrarTentativa((int) $aviso['id']);
                error_log(sprintf('aviso %d nao entregue: %s', $aviso['id'], $erro->getMessage()));

                return new ResultadoDaEntrega($entregues, true);
            }

            $this->marcarEntregue((int) $aviso['id']);
            $entregues++;
        }

        return new ResultadoDaEntrega($entregues, false);
    }

    private function pendentes(): array
    {
        return Database::buscarTodos(
            'SELECT id, aluno_id, dias_de_atraso
             FROM caixa_de_saida
             WHERE entregue_em IS NULL
             ORDER BY id
             LIMIT ' . self::LOTE,
        );
    }

    private function marcarEntregue(int $id): void
    {
        Database::executar('UPDATE caixa_de_saida SET entregue_em = NOW() WHERE id = ?', [$id]);
    }

    private function registrarTentativa(int $id): void
    {
        Database::executar('UPDATE caixa_de_saida SET tentativas = tentativas + 1 WHERE id = ?', [$id]);
    }
}
