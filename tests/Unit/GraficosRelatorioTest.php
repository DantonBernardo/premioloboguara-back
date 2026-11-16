<?php

namespace Tests\Unit;

use App\Services\GraficosRelatorio;
use DOMDocument;
use PHPUnit\Framework\TestCase;

class GraficosRelatorioTest extends TestCase
{
    private function dados(): array
    {
        return [
            'totais' => ['projetos' => 7],
            'por_categoria' => [['codigo' => 3, 'categoria' => 'Projeto <teste> & ciência', 'total' => 7]],
            'por_status' => [['status' => 'sem_avaliacao', 'rotulo' => 'Sem avaliação', 'total' => 7]],
            'distribuicao_notas' => [
                ['rotulo' => '0 a 4,99', 'total' => 0], ['rotulo' => '5 a 6,99', 'total' => 0],
                ['rotulo' => '7 a 8,49', 'total' => 0], ['rotulo' => '8,5 a 10', 'total' => 0],
            ],
        ];
    }

    private function documento(string $uri): DOMDocument
    {
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $xml = base64_decode(substr($uri, strpos($uri, ',') + 1), true);
        $this->assertNotFalse($xml);
        $documento = new DOMDocument;
        $this->assertTrue($documento->loadXML($xml));
        $this->assertSame('svg', $documento->documentElement->localName);
        $this->assertSame(0, $documento->getElementsByTagName('script')->length);
        $this->assertStringNotContainsString('NaN', $xml);

        return $documento;
    }

    public function test_graficos_sao_svg_validos_com_rotulos_escapados_e_cores_da_categoria(): void
    {
        $graficos = (new GraficosRelatorio)->gerar($this->dados());
        $this->assertSame(['categorias', 'situacao', 'notas'], array_keys($graficos));
        foreach ($graficos as $grafico) {
            $this->documento($grafico);
        }
        $categoria = $this->documento($graficos['categorias']);
        $this->assertStringContainsString('Projeto <teste> & ciência', $categoria->textContent);
        $this->assertStringContainsString('#e89455', $categoria->saveXML());
        $this->assertStringContainsString('7', $this->documento($graficos['situacao'])->textContent);
    }

    public function test_recorte_vazio_mostra_zero_no_centro_sem_erro_de_divisao(): void
    {
        $dados = $this->dados();
        $dados['totais']['projetos'] = 0;
        $dados['por_categoria'] = [];
        $dados['por_status'][0]['total'] = 0;
        $graficos = (new GraficosRelatorio)->gerar($dados);
        foreach ($graficos as $grafico) {
            $this->documento($grafico);
        }
        $documento = $this->documento($graficos['situacao']);
        $this->assertStringContainsString('0 projetos', $documento->textContent);
        $this->assertStringContainsString('Nenhum projeto', $this->documento($graficos['categorias'])->textContent);
    }
}
