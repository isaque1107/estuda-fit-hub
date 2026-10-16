<?php

declare(strict_types=1);

namespace Cobranca\Console;

use Cobranca\Services\VarrerInadimplencia;

final class Varredura implements Comando
{
    public function executar(): int
    {
        $resultado = (new VarrerInadimplencia())->executar();

        printf("%d alunos avisados, %d falhas\n", $resultado->avisados, $resultado->falhas);

        return $resultado->falhas === 0 ? 0 : 1;
    }
}
