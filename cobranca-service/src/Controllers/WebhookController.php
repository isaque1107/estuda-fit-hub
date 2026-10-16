<?php

declare(strict_types=1);

namespace Cobranca\Controllers;

use Cobranca\Http\Request;
use Cobranca\Http\Response;
use Cobranca\Services\ConfirmarPagamento;

final class WebhookController
{
    public function receber(Request $requisicao): Response
    {
        $resultado = (new ConfirmarPagamento())->executar($requisicao->corpo);

        return Response::ok(['resultado' => $resultado->value]);
    }
}
