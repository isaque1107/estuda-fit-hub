<?php

declare(strict_types=1);

namespace Cobranca\Console;

use Cobranca\Services\EntregarAvisos;

final class Entrega implements Comando
{
    public function executar(): int
    {
        $resultado = (new EntregarAvisos())->executar();

        printf(
            "%d avisos entregues%s\n",
            $resultado->entregues,
            $resultado->interrompida ? ', entrega interrompida por falha' : '',
        );

        return $resultado->interrompida ? 1 : 0;
    }
}
