<?php

declare(strict_types=1);

use Cobranca\Enums\ResultadoDaConfirmacao;
use Cobranca\Services\ConfirmarPagamento;
use Cobranca\Support\HttpException;

final class ConfirmarPagamentoTest extends TestCase
{
    private function aviso(array $fatura, string $situacao = 'approved'): array
    {
        return [
            'reference' => $fatura['gateway_ref'],
            'status' => $situacao,
            'amount' => 119.90,
            'method' => 'pix',
            'event_id' => 'evt_1',
        ];
    }

    public function testPagamentoAprovadoMarcaFaturaPagaERegistraPagamento(): void
    {
        $fatura = $this->emitirFatura();

        $resultado = (new ConfirmarPagamento())->executar($this->aviso($fatura));

        $this->assertSame(ResultadoDaConfirmacao::Pago, $resultado);
        $this->assertSame('paga', $this->buscarFatura((int) $fatura['id'])['status']);
        $this->assertNotNull($this->buscarFatura((int) $fatura['id'])['pago_em']);
        $this->assertSame(1, $this->contarPagamentos());
    }

    public function testAvisoDuplicadoNaoRegistraSegundoPagamento(): void
    {
        $fatura = $this->emitirFatura();
        $aviso = $this->aviso($fatura);

        $this->assertSame(ResultadoDaConfirmacao::Pago, (new ConfirmarPagamento())->executar($aviso));
        $this->assertSame(ResultadoDaConfirmacao::JaEstavaPaga, (new ConfirmarPagamento())->executar($aviso));

        $this->assertSame(1, $this->contarPagamentos());
    }

    public function testPagamentoRecusadoNaoAlteraAFatura(): void
    {
        $fatura = $this->emitirFatura();

        $resultado = (new ConfirmarPagamento())->executar($this->aviso($fatura, 'refused'));

        $this->assertSame(ResultadoDaConfirmacao::NaoAprovado, $resultado);
        $this->assertSame('aberta', $this->buscarFatura((int) $fatura['id'])['status']);
        $this->assertSame(0, $this->contarPagamentos());
    }

    public function testReferenciaDesconhecidaEhRecusadaComoNaoEncontrada(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => (new ConfirmarPagamento())->executar([
                'reference' => 'gw_inexistente',
                'status' => 'approved',
            ]),
            404,
        );
    }

    public function testPagamentoGravaOAvisoNaCaixaDeSaida(): void
    {
        $fatura = $this->emitirFatura(['aluno_id' => 77]);

        (new ConfirmarPagamento())->executar($this->aviso($fatura));

        $avisos = $this->avisosNaCaixa();

        $this->assertCount(1, $avisos);
        $this->assertSame(77, (int) $avisos[0]['aluno_id']);
        $this->assertSame(0, (int) $avisos[0]['dias_de_atraso']);
        $this->assertNull($avisos[0]['entregue_em']);
    }

    public function testAvisoDuplicadoNaoGravaSegundoAviso(): void
    {
        $fatura = $this->emitirFatura();
        $aviso = $this->aviso($fatura);

        (new ConfirmarPagamento())->executar($aviso);
        (new ConfirmarPagamento())->executar($aviso);

        $this->assertCount(1, $this->avisosNaCaixa());
    }
}
