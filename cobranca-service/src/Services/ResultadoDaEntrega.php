<?php

declare(strict_types=1);

namespace Cobranca\Services;

final class ResultadoDaEntrega
{
    public function __construct(
        public readonly int $entregues,
        public readonly bool $interrompida,
    ) {
    }
}
