<?php

declare(strict_types=1);

putenv('DB_NAME=' . (getenv('DB_TEST_NAME') ?: 'cobranca_test'));
putenv('GATEWAY_LATENCIA_MS=0');

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/TestCase.php';
require __DIR__ . '/Dubles.php';

use Cobranca\Console\Migrar;

$filtro = $argv[1] ?? '';

ob_start();
(new Migrar())->executar();
ob_end_clean();

$arquivos = glob(__DIR__ . '/*Test.php') ?: [];
sort($arquivos);

$total = 0;
$falhas = 0;
$inicio = microtime(true);

foreach ($arquivos as $arquivo) {
    require_once $arquivo;
    $classe = basename($arquivo, '.php');

    if (! class_exists($classe)) {
        continue;
    }

    $instancia = new $classe();
    $metodos = array_filter(
        get_class_methods($instancia),
        static fn (string $metodo): bool => str_starts_with($metodo, 'test'),
    );
    $imprimiuClasse = false;

    foreach ($metodos as $metodo) {
        if ($filtro !== '' && stripos("{$classe}::{$metodo}", $filtro) === false) {
            continue;
        }

        if (! $imprimiuClasse) {
            echo "\n{$classe}\n";
            $imprimiuClasse = true;
        }

        $total++;

        try {
            $instancia->limparBanco();
            $instancia->$metodo();
            echo "  ok    {$metodo}\n";
        } catch (Throwable $erro) {
            $falhas++;
            echo "  FALHA {$metodo}\n";
            echo '        ' . $erro::class . ': ' . $erro->getMessage() . "\n";
            echo "        em {$erro->getFile()}:{$erro->getLine()}\n";
        }
    }
}

printf("\n%d testes, %d falhas, %.1fs\n", $total, $falhas, microtime(true) - $inicio);

exit($falhas > 0 ? 1 : 0);
