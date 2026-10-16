<?php

declare(strict_types=1);

use Cobranca\Services\EmitirFatura;
use Cobranca\Support\HttpException;

final class EmitirFaturaTest extends TestCase
{
    private const PEDIDO = [
        'matricula_id' => 10,
        'aluno_id' => 10,
        'competencia' => '2026-11',
        'valor' => '119.90',
        'vencimento' => '2026-11-10',
    ];

    public function testEmiteFaturaAbertaComReferenciaDoGateway(): void
    {
        $resultado = (new EmitirFatura(new GatewayFixo()))->executar(self::PEDIDO);

        $this->assertTrue($resultado->foiEmitidaAgora);
        $this->assertSame('aberta', $resultado->fatura['status']);
        $this->assertSame('2026-11-01', $resultado->fatura['competencia']);
        $this->assertSame('119.90', $resultado->fatura['valor']);
        $this->assertNotNull($resultado->fatura['gateway_ref']);
    }

    public function testPedidoRepetidoDevolveAMesmaFatura(): void
    {
        $servico = new EmitirFatura(new GatewayFixo());

        $primeira = $servico->executar(self::PEDIDO);
        $segunda = $servico->executar(self::PEDIDO);

        $this->assertTrue($primeira->foiEmitidaAgora);
        $this->assertFalse($segunda->foiEmitidaAgora);
        $this->assertSame($primeira->fatura['id'], $segunda->fatura['id']);
        $this->assertSame(1, $this->contarFaturas());
    }

    public function testGatewayForaDoArNaoImpedeAEmissao(): void
    {
        $resultado = (new EmitirFatura(new GatewayQuebrado()))->executar(self::PEDIDO);

        $this->assertTrue($resultado->foiEmitidaAgora);
        $this->assertNull($resultado->fatura['gateway_ref']);
        $this->assertSame(1, $this->contarFaturas());
    }

    public function testCompetenciaInvalidaEhRejeitada(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => $this->emitirFatura(['competencia' => 'novembro']),
            422,
        );
    }

    public function testVencimentoInexistenteEhRejeitado(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => $this->emitirFatura(['vencimento' => '2026-02-31']),
            422,
        );
    }

    public function testValorZeradoEhRejeitado(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => $this->emitirFatura(['valor' => '0']),
            422,
        );
    }

    public function testMatriculaInvalidaEhRejeitada(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => $this->emitirFatura(['matricula_id' => 0]),
            422,
        );
    }
}
