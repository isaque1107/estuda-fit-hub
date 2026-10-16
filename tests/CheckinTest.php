<?php

declare(strict_types=1);

use EstudaFitHub\Controllers\CheckinController;
use EstudaFitHub\Http\Request;
use EstudaFitHub\Models\Checkin;
use EstudaFitHub\Services\AplicarAvisoDeCobranca;
use EstudaFitHub\Support\HttpException;

final class CheckinTest extends TestCase
{
    private function checkin(string $cpf, int $unidadeId): array
    {
        $request = new Request('POST', '/checkins', [], ['cpf' => $cpf, 'unidade_id' => $unidadeId]);

        return (new CheckinController())->registrar($request)->dados();
    }

    private function avisarAtraso(int $alunoId, int $dias): void
    {
        (new AplicarAvisoDeCobranca())->executar($alunoId, $dias);
    }

    public function testAlunoEmDiaEhLiberado(): void
    {
        $aluno = $this->criarAluno();
        $this->criarMatricula($aluno);

        $resposta = $this->checkin($aluno->cpf, (int) $aluno->unidade_id);

        $this->assertTrue($resposta['liberado']);
        $this->assertNull($resposta['motivo_bloqueio']);
        $this->assertSame(1, Checkin::where('aluno_id', $aluno->id)->where('liberado', 1)->count());
    }

    public function testAtrasoAcimaDaToleranciaBloqueiaCatraca(): void
    {
        $aluno = $this->criarAluno();
        $this->criarMatricula($aluno);

        $this->avisarAtraso((int) $aluno->id, 10);

        $resposta = $this->checkin($aluno->cpf, (int) $aluno->unidade_id);

        $this->assertFalse($resposta['liberado']);
        $this->assertSame('situacao_bloqueado', $resposta['motivo_bloqueio']);
    }

    public function testAtrasoDentroDaToleranciaLibera(): void
    {
        $aluno = $this->criarAluno();
        $this->criarMatricula($aluno);

        $this->avisarAtraso((int) $aluno->id, 5);

        $this->assertTrue($this->checkin($aluno->cpf, (int) $aluno->unidade_id)['liberado']);
    }

    public function testQuitacaoLiberaACatracaNovamente(): void
    {
        $aluno = $this->criarAluno();
        $this->criarMatricula($aluno);

        $this->avisarAtraso((int) $aluno->id, 10);
        $this->assertFalse($this->checkin($aluno->cpf, (int) $aluno->unidade_id)['liberado']);

        $this->avisarAtraso((int) $aluno->id, 0);
        $this->assertTrue($this->checkin($aluno->cpf, (int) $aluno->unidade_id)['liberado']);
    }

    public function testCheckinNaoConsultaMaisATabelaDeFaturas(): void
    {
        $aluno = $this->criarAluno();
        $matricula = $this->criarMatricula($aluno);
        $this->criarFatura($matricula, ['status' => 'vencida', 'vencimento' => $this->diasAtras(30)]);

        $resposta = $this->checkin($aluno->cpf, (int) $aluno->unidade_id);

        $this->assertTrue($resposta['liberado'], 'Sem aviso de Cobrança, a catraca libera');
    }

    public function testAvisoDeCobrancaNaoReativaAlunoInativo(): void
    {
        $aluno = $this->criarAluno(['situacao' => 'inativo']);

        $this->avisarAtraso((int) $aluno->id, 0);

        $this->assertSame('inativo', $aluno->refresh()->situacao);
    }

    public function testAlunoBloqueadoNaoEntraMesmoSemFaturaVencida(): void
    {
        $aluno = $this->criarAluno(['situacao' => 'bloqueado']);
        $this->criarMatricula($aluno);

        $resposta = $this->checkin($aluno->cpf, (int) $aluno->unidade_id);

        $this->assertFalse($resposta['liberado']);
        $this->assertSame('situacao_bloqueado', $resposta['motivo_bloqueio']);
    }

    public function testCpfDesconhecidoRetorna404(): void
    {
        $this->assertLanca(HttpException::class, fn () => $this->checkin('00000000000', 1), 404);
    }
}
