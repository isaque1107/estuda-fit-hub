<?php

declare(strict_types=1);

namespace Cobranca\Http;

use Cobranca\Support\HttpException;

final class Request
{
    private function __construct(
        public readonly string $metodo,
        public readonly string $caminho,
        public readonly array $corpo,
    ) {
    }

    public static function daRequisicaoAtual(): self
    {
        $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rtrim($caminho, '/') ?: '/',
            self::corpo(),
        );
    }

    private static function corpo(): array
    {
        $bruto = (string) file_get_contents('php://input');

        if ($bruto === '') {
            return [];
        }

        $decodificado = json_decode($bruto, true);

        if (! is_array($decodificado)) {
            throw new HttpException(400, 'Corpo da requisição não é um JSON válido');
        }

        return $decodificado;
    }
}
