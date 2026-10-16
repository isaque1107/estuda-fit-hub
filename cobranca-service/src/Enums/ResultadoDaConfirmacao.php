<?php

declare(strict_types=1);

namespace Cobranca\Enums;

enum ResultadoDaConfirmacao: string
{
    case Pago = 'pago';
    case JaEstavaPaga = 'ja_estava_paga';
    case NaoAprovado = 'nao_aprovado';
}
