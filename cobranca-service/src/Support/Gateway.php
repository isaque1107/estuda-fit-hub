<?php

declare(strict_types=1);

namespace Cobranca\Support;

use Cobranca\Contracts\GatewayDePagamento;

final class Gateway implements GatewayDePagamento
{
    public function registrarCobranca(int $faturaId): string
    {
        $latencia = (int) getenv('GATEWAY_LATENCIA_MS');

        if ($latencia > 0) {
            usleep($latencia * 1000);
        }

        return sprintf('gw_%d_%s', $faturaId, substr(md5((string) $faturaId), 0, 8));
    }
}
