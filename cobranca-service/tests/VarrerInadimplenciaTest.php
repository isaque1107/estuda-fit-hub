<?php

declare(strict_types=1);

use Cobranca\Services\AvisarInadimplencia;
use Cobranca\Services\VarrerInadimplencia;
use Cobranca\Support\Database;

final class VarrerInadimplenciaTest extends TestCase
{
    private function varrer(NotificadorEmMemoria $notificador): object
    {
        return (new VarrerInadimplencia(new AvisarInadimplencia($notificador)))->executar();
    }

    public function testAvisaSomenteQuemTemFaturaVencida(): void
    {
        $this->emitirFatura(['aluno_id' => 1, 'vencimento' => $this->diasAtras(10)]);
        $this->emitirFatura(['aluno_id' => 2, 'vencimento' => $this->daquiADias(5)]);

        $notificador = new NotificadorEmMemoria();
        $resultado = $this->varrer($notificador);

        $this->assertSame(1, $resultado->avisados);
        $this->assertCount(1, $notificador->avisos);
        $this->assertSame(1, $notificador->avisos[0][0]);
    }

    public function testAvisaOMaiorAtrasoDoAluno(): void
    {
        $this->emitirFatura([
            'matricula_id' => 30,
            'aluno_id' => 3,
            'competencia' => '2026-09',
            'vencimento' => $this->diasAtras(20),
        ]);
        $this->emitirFatura([
            'matricula_id' => 30,
            'aluno_id' => 3,
            'competencia' => '2026-10',
            'vencimento' => $this->diasAtras(7),
        ]);

        $notificador = new NotificadorEmMemoria();
        $this->varrer($notificador);

        $this->assertCount(1, $notificador->avisos);
        $this->assertSame([3, 20], $notificador->avisos[0]);
    }

    public function testFaturaPagaNaoGeraAviso(): void
    {
        $fatura = $this->emitirFatura(['aluno_id' => 9, 'vencimento' => $this->diasAtras(30)]);

        Database::executar("UPDATE faturas SET status = 'paga' WHERE id = ?", [$fatura['id']]);

        $notificador = new NotificadorEmMemoria();
        $resultado = $this->varrer($notificador);

        $this->assertSame(0, $resultado->avisados);
        $this->assertCount(0, $notificador->avisos);
    }

    public function testFalhaNumAlunoNaoImpedeOsDemais(): void
    {
        $this->emitirFatura(['aluno_id' => 4, 'vencimento' => $this->diasAtras(10)]);
        $this->emitirFatura(['aluno_id' => 5, 'vencimento' => $this->diasAtras(10)]);

        $notificador = new NotificadorEmMemoria(4);
        $resultado = $this->varrer($notificador);

        $this->assertSame(1, $resultado->avisados);
        $this->assertSame(1, $resultado->falhas);
        $this->assertCount(1, $notificador->avisos);
        $this->assertSame(5, $notificador->avisos[0][0]);
    }
}
