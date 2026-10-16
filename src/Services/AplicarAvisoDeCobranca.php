<?php

declare(strict_types=1);

namespace EstudaFitHub\Services;

use EstudaFitHub\Models\Aluno;
use EstudaFitHub\Support\HttpException;

final class AplicarAvisoDeCobranca
{
    private const DIAS_DE_TOLERANCIA = 5;

    public function executar(int $alunoId, int $diasDeAtraso): void
    {
        $aluno = Aluno::find($alunoId);

        if ($aluno === null) {
            throw new HttpException(404, 'Aluno não encontrado');
        }

        if ($aluno->situacao === 'inativo') {
            return;
        }

        $situacao = $diasDeAtraso > self::DIAS_DE_TOLERANCIA ? 'bloqueado' : 'ativo';

        if ($aluno->situacao !== $situacao) {
            $aluno->update(['situacao' => $situacao]);
        }
    }
}
