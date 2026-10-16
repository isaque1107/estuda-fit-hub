<?php

declare(strict_types=1);

namespace Cobranca\Contracts;

interface GatewayDePagamento
{
    public function registrarCobranca(int $faturaId): string;
}
