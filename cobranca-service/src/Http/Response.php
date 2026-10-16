<?php

declare(strict_types=1);

namespace Cobranca\Http;

final class Response
{
    private function __construct(
        public readonly int $status,
        public readonly array $corpo,
    ) {
    }

    public static function ok(array $corpo, int $status = 200): self
    {
        return new self($status, $corpo);
    }

    public static function erro(int $status, string $mensagem): self
    {
        return new self($status, ['erro' => $mensagem]);
    }

    public function enviar(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($this->corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
