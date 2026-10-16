<?php

declare(strict_types=1);

namespace Cobranca\Services;

final class ResultadoDaEmissao
{
    private function __construct(
        public readonly array $fatura,
        public readonly bool $foiEmitidaAgora,
    ) {
    }

    public static function emitida(array $fatura): self
    {
        return new self($fatura, true);
    }

    public static function jaExistia(array $fatura): self
    {
        return new self($fatura, false);
    }
}
