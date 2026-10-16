<?php

declare(strict_types=1);

use Cobranca\Services\EmitirFatura;
use Cobranca\Support\Database;

final class FalhaDeAssercao extends RuntimeException
{
}

abstract class TestCase
{
    private const TABELAS = ['pagamentos', 'faturas'];

    private static int $sequencia = 0;

    public function limparBanco(): void
    {
        Database::executar('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABELAS as $tabela) {
            Database::executar("TRUNCATE TABLE {$tabela}");
        }

        Database::executar('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function assertTrue(mixed $condicao, string $mensagem = ''): void
    {
        if ($condicao !== true) {
            throw new FalhaDeAssercao($mensagem !== '' ? $mensagem : 'Esperava true, recebi ' . var_export($condicao, true));
        }
    }

    protected function assertFalse(mixed $condicao, string $mensagem = ''): void
    {
        if ($condicao !== false) {
            throw new FalhaDeAssercao($mensagem !== '' ? $mensagem : 'Esperava false, recebi ' . var_export($condicao, true));
        }
    }

    protected function assertSame(mixed $esperado, mixed $atual, string $mensagem = ''): void
    {
        if ($esperado !== $atual) {
            throw new FalhaDeAssercao(sprintf(
                '%sEsperava %s, recebi %s',
                $mensagem !== '' ? $mensagem . '. ' : '',
                var_export($esperado, true),
                var_export($atual, true),
            ));
        }
    }

    protected function assertNull(mixed $valor, string $mensagem = ''): void
    {
        if ($valor !== null) {
            throw new FalhaDeAssercao($mensagem !== '' ? $mensagem : 'Esperava null, recebi ' . var_export($valor, true));
        }
    }

    protected function assertNotNull(mixed $valor, string $mensagem = ''): void
    {
        if ($valor === null) {
            throw new FalhaDeAssercao($mensagem !== '' ? $mensagem : 'Esperava valor não nulo');
        }
    }

    protected function assertCount(int $esperado, array $itens, string $mensagem = ''): void
    {
        $this->assertSame($esperado, count($itens), $mensagem);
    }

    protected function assertLanca(string $classe, callable $operacao, ?int $status = null): void
    {
        try {
            $operacao();
        } catch (Throwable $erro) {
            if (! $erro instanceof $classe) {
                throw new FalhaDeAssercao(sprintf('Esperava %s, recebi %s: %s', $classe, $erro::class, $erro->getMessage()));
            }

            if ($status !== null && property_exists($erro, 'status') && $erro->status !== $status) {
                throw new FalhaDeAssercao(sprintf('Esperava status %d, recebi %d', $status, $erro->status));
            }

            return;
        }

        throw new FalhaDeAssercao("Esperava exceção {$classe}, nada foi lançado");
    }

    protected function emitirFatura(array $extra = []): array
    {
        $numero = ++self::$sequencia;

        $dados = array_merge([
            'matricula_id' => $numero,
            'aluno_id' => $numero,
            'competencia' => '2026-11',
            'valor' => '119.90',
            'vencimento' => '2026-11-10',
        ], $extra);

        return (new EmitirFatura(new GatewayFixo()))->executar($dados)->fatura;
    }

    protected function buscarFatura(int $id): ?array
    {
        return Database::buscarUm('SELECT * FROM faturas WHERE id = ?', [$id]);
    }

    protected function contarFaturas(): int
    {
        return (int) (Database::buscarUm('SELECT COUNT(*) AS total FROM faturas')['total'] ?? 0);
    }

    protected function contarPagamentos(): int
    {
        return (int) (Database::buscarUm('SELECT COUNT(*) AS total FROM pagamentos')['total'] ?? 0);
    }

    protected function diasAtras(int $dias): string
    {
        return date('Y-m-d', strtotime("-{$dias} days"));
    }

    protected function daquiADias(int $dias): string
    {
        return date('Y-m-d', strtotime("+{$dias} days"));
    }
}
