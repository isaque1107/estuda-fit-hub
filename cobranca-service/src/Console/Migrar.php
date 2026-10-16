<?php

declare(strict_types=1);

namespace Cobranca\Console;

use Cobranca\Support\Database;

final class Migrar implements Comando
{
    public function executar(): int
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $comandos = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($comandos as $comando) {
            Database::conexao()->exec($comando);
        }

        printf("%d comandos aplicados\n", count($comandos));

        return 0;
    }
}
