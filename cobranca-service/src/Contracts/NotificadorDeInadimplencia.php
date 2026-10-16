<?php

declare(strict_types=1);

namespace Cobranca\Contracts;

interface NotificadorDeInadimplencia
{
    public function avisarInadimplencia(int $alunoId, int $diasDeAtraso): void;
}
