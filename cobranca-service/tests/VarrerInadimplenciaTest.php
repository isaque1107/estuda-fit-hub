<?php

declare(strict_types=1);

use Cobranca\Services\VarrerInadimplencia;
use Cobranca\Support\Database;

final class VarrerInadimplenciaTest extends TestCase
{
    public function testGravaAvisoSomenteParaQuemTemFaturaVencida(): void
    {
        $this->emitirFatura(['aluno_id' => 1, 'vencimento' => $this->diasAtras(10)]);
        $this->emitirFatura(['aluno_id' => 2, 'vencimento' => $this->daquiADias(5)]);

        $resultado = (new VarrerInadimplencia())->executar();

        $this->assertSame(1, $resultado->avisados);
        $this->assertSame(0, $resultado->falhas);

        $avisos = $this->avisosNaCaixa();

        $this->assertCount(1, $avisos);
        $this->assertSame(1, (int) $avisos[0]['aluno_id']);
    }

    public function testGravaOMaiorAtrasoDoAluno(): void
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

        (new VarrerInadimplencia())->executar();

        $avisos = $this->avisosNaCaixa();

        $this->assertCount(1, $avisos);
        $this->assertSame(20, (int) $avisos[0]['dias_de_atraso']);
    }

    public function testFaturaPagaNaoGeraAviso(): void
    {
        $fatura = $this->emitirFatura(['aluno_id' => 9, 'vencimento' => $this->diasAtras(30)]);

        Database::executar("UPDATE faturas SET status = 'paga' WHERE id = ?", [$fatura['id']]);

        $resultado = (new VarrerInadimplencia())->executar();

        $this->assertSame(0, $resultado->avisados);
        $this->assertCount(0, $this->avisosNaCaixa());
    }
}
