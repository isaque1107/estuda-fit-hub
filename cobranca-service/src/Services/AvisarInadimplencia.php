<?php

declare(strict_types=1);

namespace Cobranca\Services;

use Cobranca\Support\Database;

final class AvisarInadimplencia
{
    public function paraOAluno(int $alunoId): int
    {
        $dias = $this->diasDeAtraso($alunoId);

        Database::inserir('caixa_de_saida', [
            'aluno_id' => $alunoId,
            'dias_de_atraso' => $dias,
        ]);

        return $dias;
    }

    private function diasDeAtraso(int $alunoId): int
    {
        $linha = Database::buscarUm(
            "SELECT COALESCE(MAX(DATEDIFF(CURDATE(), vencimento)), 0) AS dias
             FROM faturas
             WHERE aluno_id = ? AND status = 'aberta' AND vencimento < CURDATE()",
            [$alunoId],
        );

        return (int) ($linha['dias'] ?? 0);
    }
}
