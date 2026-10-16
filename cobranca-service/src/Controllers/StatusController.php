<?php

declare(strict_types=1);

namespace Cobranca\Controllers;

use Cobranca\Http\Response;
use Cobranca\Support\Database;
use Throwable;

final class StatusController
{
    public function saude(): Response
    {
        try {
            Database::buscarUm('SELECT 1');
        } catch (Throwable) {
            return Response::ok(['servico' => 'cobranca', 'banco' => 'indisponivel'], 503);
        }

        return Response::ok([
            'servico' => 'cobranca',
            'banco' => 'ok',
            'database' => getenv('DB_NAME'),
        ]);
    }
}
