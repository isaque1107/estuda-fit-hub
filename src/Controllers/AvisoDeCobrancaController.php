<?php

declare(strict_types=1);

namespace EstudaFitHub\Controllers;

use EstudaFitHub\Http\Request;
use EstudaFitHub\Http\Response;
use EstudaFitHub\Services\AplicarAvisoDeCobranca;

final class AvisoDeCobrancaController
{
    public function inadimplencia(Request $request): Response
    {
        $request->exigir(['aluno_id', 'dias_de_atraso']);

        (new AplicarAvisoDeCobranca())->executar(
            (int) $request->input('aluno_id'),
            (int) $request->input('dias_de_atraso'),
        );

        return Response::vazio();
    }
}
