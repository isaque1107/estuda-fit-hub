<?php

declare(strict_types=1);

use Cobranca\Contracts\GatewayDePagamento;
use Cobranca\Contracts\NotificadorDeInadimplencia;

final class GatewayFixo implements GatewayDePagamento
{
    public function registrarCobranca(int $faturaId): string
    {
        return 'gw_teste_' . $faturaId;
    }
}

final class GatewayQuebrado implements GatewayDePagamento
{
    public function registrarCobranca(int $faturaId): string
    {
        throw new RuntimeException('gateway fora do ar');
    }
}

final class NotificadorEmMemoria implements NotificadorDeInadimplencia
{
    public array $avisos = [];

    public function __construct(private readonly ?int $alunoQueFalha = null)
    {
    }

    public function avisarInadimplencia(int $alunoId, int $diasDeAtraso): void
    {
        if ($alunoId === $this->alunoQueFalha) {
            throw new RuntimeException('monólito fora do ar');
        }

        $this->avisos[] = [$alunoId, $diasDeAtraso];
    }
}
