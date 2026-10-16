<?php

declare(strict_types=1);

namespace Cobranca\Services;

use Cobranca\Support\Database;
use Throwable;

final class VarrerInadimplencia
{
    public function __construct(private readonly AvisarInadimplencia $aviso = new AvisarInadimplencia())
    {
    }

    public function executar(): ResultadoDaVarredura
    {
        $avisados = 0;
        $falhas = 0;

        foreach ($this->alunosEmAtraso() as $alunoId) {
            try {
                $this->aviso->paraOAluno($alunoId);
                $avisados++;
            } catch (Throwable $erro) {
                $falhas++;
                error_log(sprintf('falha ao avisar o aluno %d: %s', $alunoId, $erro->getMessage()));
            }
        }

        return new ResultadoDaVarredura($avisados, $falhas);
    }

    private function alunosEmAtraso(): array
    {
        $linhas = Database::buscarTodos(
            "SELECT DISTINCT aluno_id
             FROM faturas
             WHERE status = 'aberta' AND vencimento < CURDATE()
             ORDER BY aluno_id",
        );

        return array_map(static fn (array $linha): int => (int) $linha['aluno_id'], $linhas);
    }
}
