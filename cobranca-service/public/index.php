<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Cobranca\Controllers\FaturaController;
use Cobranca\Controllers\StatusController;
use Cobranca\Controllers\WebhookController;
use Cobranca\Http\Request;
use Cobranca\Http\Response;
use Cobranca\Support\HttpException;

try {
    $requisicao = Request::daRequisicaoAtual();

    $resposta = match (true) {
        $requisicao->metodo === 'GET' && $requisicao->caminho === '/health' => (new StatusController())->saude(),
        $requisicao->metodo === 'POST' && $requisicao->caminho === '/faturas' => (new FaturaController())->emitir($requisicao),
        $requisicao->metodo === 'POST' && $requisicao->caminho === '/webhooks/gateway' => (new WebhookController())->receber($requisicao),
        default => Response::erro(404, 'Rota não encontrada'),
    };
} catch (HttpException $erro) {
    $resposta = Response::erro($erro->status, $erro->getMessage());
} catch (Throwable $erro) {
    error_log(sprintf('%s: %s', $erro::class, $erro->getMessage()));
    $resposta = Response::erro(500, 'Erro interno');
}

$resposta->enviar();
