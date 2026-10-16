<?php

declare(strict_types=1);

namespace Cobranca\Services;

final class ResultadoDaVarredura
{
    public function __construct(
        public readonly int $avisados,
        public readonly int $falhas,
    ) {
    }
}
