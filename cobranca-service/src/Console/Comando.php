<?php

declare(strict_types=1);

namespace Cobranca\Console;

interface Comando
{
    public function executar(): int;
}
