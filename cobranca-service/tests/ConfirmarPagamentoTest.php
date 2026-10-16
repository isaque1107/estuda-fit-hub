<?php

declare(strict_types=1);

use Cobranca\Enums\ResultadoDaConfirmacao;
use Cobranca\Services\AvisarInadimplencia;
use Cobranca\Services\ConfirmarPagamento;
use Cobranca\Support\HttpException;

final class ConfirmarPagamentoTest extends TestCase
{
    private function servico(NotificadorEmMemoria $notificador): ConfirmarPagamento
    {
        return new ConfirmarPagamento(new AvisarInadimplencia($notificador));
    }

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

        $resultado = $this->servico(new NotificadorEmMemoria())->executar($this->aviso($fatura));

        $this->assertSame(ResultadoDaConfirmacao::Pago, $resultado);
        $this->assertSame('paga', $this->buscarFatura((int) $fatura['id'])['status']);
        $this->assertNotNull($this->buscarFatura((int) $fatura['id'])['pago_em']);
        $this->assertSame(1, $this->contarPagamentos());
    }

    public function testAvisoDuplicadoNaoRegistraSegundoPagamento(): void
    {
        $fatura = $this->emitirFatura();
        $aviso = $this->aviso($fatura);

        $this->assertSame(ResultadoDaConfirmacao::Pago, $this->servico(new NotificadorEmMemoria())->executar($aviso));
        $this->assertSame(ResultadoDaConfirmacao::JaEstavaPaga, $this->servico(new NotificadorEmMemoria())->executar($aviso));

        $this->assertSame(1, $this->contarPagamentos());
    }

    public function testPagamentoRecusadoNaoAlteraAFatura(): void
    {
        $fatura = $this->emitirFatura();

        $resultado = $this->servico(new NotificadorEmMemoria())->executar($this->aviso($fatura, 'refused'));

        $this->assertSame(ResultadoDaConfirmacao::NaoAprovado, $resultado);
        $this->assertSame('aberta', $this->buscarFatura((int) $fatura['id'])['status']);
        $this->assertSame(0, $this->contarPagamentos());
    }

    public function testReferenciaDesconhecidaEhRecusadaComoNaoEncontrada(): void
    {
        $this->assertLanca(
            HttpException::class,
            fn () => $this->servico(new NotificadorEmMemoria())->executar([
                'reference' => 'gw_inexistente',
                'status' => 'approved',
            ]),
            404,
        );
    }

    public function testPagamentoAvisaOMonolitoNaHora(): void
    {
        $fatura = $this->emitirFatura(['aluno_id' => 77]);
        $notificador = new NotificadorEmMemoria();

        $this->servico($notificador)->executar($this->aviso($fatura));

        $this->assertCount(1, $notificador->avisos);
        $this->assertSame([77, 0], $notificador->avisos[0]);
    }

    public function testMonolitoForaDoArNaoDerrubaOPagamento(): void
    {
        $fatura = $this->emitirFatura(['aluno_id' => 88]);

        $resultado = $this->servico(new NotificadorEmMemoria(88))->executar($this->aviso($fatura));

        $this->assertSame(ResultadoDaConfirmacao::Pago, $resultado);
        $this->assertSame('paga', $this->buscarFatura((int) $fatura['id'])['status']);
    }
}
