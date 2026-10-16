<?php

declare(strict_types=1);

namespace Cobranca\Support;

use Cobranca\Contracts\NotificadorDeInadimplencia;
use RuntimeException;

final class Monolito implements NotificadorDeInadimplencia
{
    private const TIMEOUT_EM_SEGUNDOS = 5;

    public function avisarInadimplencia(int $alunoId, int $diasDeAtraso): void
    {
        $url = rtrim((string) getenv('MONOLITO_URL'), '/') . '/avisos/cobranca';

        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(['aluno_id' => $alunoId, 'dias_de_atraso' => $diasDeAtraso]),
                'timeout' => self::TIMEOUT_EM_SEGUNDOS,
                'ignore_errors' => true,
            ],
        ]);

        $resposta = @file_get_contents($url, false, $contexto);
        $status = $this->statusDaResposta($http_response_header ?? []);

        if ($resposta === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                sprintf('Monólito respondeu %d ao aviso do aluno %d', $status, $alunoId),
            );
        }
    }

    private function statusDaResposta(array $cabecalhos): int
    {
        foreach ($cabecalhos as $cabecalho) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $cabecalho, $partes) === 1) {
                return (int) $partes[1];
            }
        }

        return 0;
    }
}
