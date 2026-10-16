<?php

declare(strict_types=1);

namespace Cobranca\Support;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private const CHAVE_UNICA_VIOLADA = 1062;

    private static ?PDO $conexao = null;

    public static function conexao(): PDO
    {
        if (self::$conexao === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'),
                getenv('DB_PORT'),
                getenv('DB_NAME'),
            );

            self::$conexao = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$conexao;
    }

    public static function buscarUm(string $sql, array $parametros = []): ?array
    {
        $consulta = self::conexao()->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->fetch() ?: null;
    }

    public static function buscarTodos(string $sql, array $parametros = []): array
    {
        $consulta = self::conexao()->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->fetchAll();
    }

    public static function executar(string $sql, array $parametros = []): int
    {
        $consulta = self::conexao()->prepare($sql);
        $consulta->execute($parametros);

        return $consulta->rowCount();
    }

    public static function inserir(string $tabela, array $dados): int
    {
        $colunas = array_keys($dados);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tabela,
            implode(', ', $colunas),
            implode(', ', array_fill(0, count($colunas), '?')),
        );

        self::executar($sql, array_values($dados));

        return (int) self::conexao()->lastInsertId();
    }

    public static function transacao(callable $operacao): void
    {
        $conexao = self::conexao();
        $conexao->beginTransaction();

        try {
            $operacao();
            $conexao->commit();
        } catch (Throwable $erro) {
            $conexao->rollBack();

            throw $erro;
        }
    }

    public static function violouChaveUnica(PDOException $erro): bool
    {
        return (int) ($erro->errorInfo[1] ?? 0) === self::CHAVE_UNICA_VIOLADA;
    }
}
