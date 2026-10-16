<?php

declare(strict_types=1);

namespace Cobranca\Controllers;

use Cobranca\Http\Request;
use Cobranca\Http\Response;
use Cobranca\Services\EmitirFatura;

final class FaturaController
{
    public function emitir(Request $requisicao): Response
    {
        $resultado = (new EmitirFatura())->executar($requisicao->corpo);

        return Response::ok($resultado->fatura, $resultado->foiEmitidaAgora ? 201 : 200);
    }
}
