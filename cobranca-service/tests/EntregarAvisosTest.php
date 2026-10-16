<?php

declare(strict_types=1);

use Cobranca\Services\EntregarAvisos;
use Cobranca\Support\Database;

final class EntregarAvisosTest extends TestCase
{
    private function gravarAviso(int $alunoId, int $dias): void
    {
        Database::inserir('caixa_de_saida', ['aluno_id' => $alunoId, 'dias_de_atraso' => $dias]);
    }

    public function testEntregaOsAvisosPendentesNaOrdem(): void
    {
        $this->gravarAviso(1, 10);
        $this->gravarAviso(2, 0);

        $notificador = new NotificadorEmMemoria();
        $resultado = (new EntregarAvisos($notificador))->executar();

        $this->assertSame(2, $resultado->entregues);
        $this->assertFalse($resultado->interrompida);
        $this->assertSame([[1, 10], [2, 0]], $notificador->avisos);
    }

    public function testAvisoEntregueNaoEhEnviadoDeNovo(): void
    {
        $this->gravarAviso(1, 10);
        $notificador = new NotificadorEmMemoria();

        (new EntregarAvisos($notificador))->executar();
        (new EntregarAvisos($notificador))->executar();

        $this->assertCount(1, $notificador->avisos);
    }

    public function testMonolitoForaDoArMantemOAvisoPendente(): void
    {
        $this->gravarAviso(7, 12);

        $resultado = (new EntregarAvisos(new NotificadorEmMemoria(7)))->executar();

        $this->assertSame(0, $resultado->entregues);
        $this->assertTrue($resultado->interrompida);

        $aviso = $this->avisosNaCaixa()[0];
        $this->assertNull($aviso['entregue_em']);
        $this->assertSame(1, (int) $aviso['tentativas']);
    }

    public function testEntregaParaNoPrimeiroErroParaPreservarAOrdem(): void
    {
        $this->gravarAviso(7, 12);
        $this->gravarAviso(8, 3);

        $notificador = new NotificadorEmMemoria(7);
        (new EntregarAvisos($notificador))->executar();

        $this->assertCount(0, $notificador->avisos);
        $this->assertNull($this->avisosNaCaixa()[1]['entregue_em']);
    }

    public function testAvisoEhEntregueQuandoOMonolitoVolta(): void
    {
        $this->gravarAviso(7, 12);

        (new EntregarAvisos(new NotificadorEmMemoria(7)))->executar();

        $notificador = new NotificadorEmMemoria();
        $resultado = (new EntregarAvisos($notificador))->executar();

        $this->assertSame(1, $resultado->entregues);
        $this->assertSame([[7, 12]], $notificador->avisos);
    }
}
