<?php

namespace App\Services;

use Goat1000\SVGGraph\SVGGraph;

class GraficosRelatorio
{
    public const CORES_CATEGORIA = [1 => '#579bd3', 2 => '#a68bd6', 3 => '#e89455', 4 => '#58aa98'];

    public const CORES_STATUS = ['habilitada' => '#58aa98', 'inabilitada' => '#d66d72', 'em_avaliacao' => '#e89455', 'sem_avaliacao' => '#9ba9b8'];

    public function gerar(array $relatorio): array
    {
        $categorias = [];
        $cores = [];
        foreach ($relatorio['por_categoria'] as $categoria) {
            $categorias[$categoria['categoria']] = $categoria['total'];
            $cor = self::CORES_CATEGORIA[$categoria['codigo']];
            $cores[] = $cor;
        }
        $categorias = $categorias ?: ['Nenhum projeto' => 0];
        $total = $relatorio['totais']['projetos'];
        $status = [];
        $coresStatus = [];
        foreach ($relatorio['por_status'] as $item) {
            if ($item['total'] > 0) {
                $status[$item['rotulo']] = $item['total'];
                $coresStatus[] = self::CORES_STATUS[$item['status']];
            }
        }
        $faixas = array_column($relatorio['distribuicao_notas'], 'total', 'rotulo');

        return [
            'categorias' => $this->grafico('HorizontalBarGraph', 700, 245, $categorias, $cores ?: ['#e3eaf2'], [
                'pad_left' => 225, 'pad_right' => 45, 'pad_top' => 14, 'pad_bottom' => 30,
                'bar_width' => 24, 'bar_round' => 8,
                'show_grid_h' => false, 'show_grid_v' => true,
                'show_axis_text_h' => true, 'show_axis_text_v' => true,
                'axis_text_space' => 14, 'axis_font_size' => 13,
                'axis_max_h' => max(4, ceil(max($categorias) / 4) * 4),
                'minimum_units_x' => 1,
                'data_label_position' => 'outside right',
            ]),
            'situacao' => $this->grafico('DonutGraph', 300, 260, $status ?: ['Sem projetos' => 1], $coresStatus ?: ['#e3eaf2'], [
                'pad_top' => 12, 'pad_bottom' => 12, 'pad_left' => 32, 'pad_right' => 32,
                'inner_radius' => 0.76, 'donut_slice_gap' => $total ? 4 : 0,
                'start_angle' => -90, 'sort' => false, 'show_data_labels' => false,
                'inner_text' => $total === 0 ? '0 projetos' : (string) $total,
                'inner_text_font' => 'DejaVu Sans', 'inner_text_font_size' => $total === 0 ? 24 : 44,
                'inner_text_font_weight' => 'bold', 'inner_text_colour' => '#243e54',
            ]),
            'notas' => $this->grafico('BarGraph', 420, 260, $faixas, [
                '#b9d4eb', '#93bddd', '#6fa2cf', '#326b9a',
            ], [
                'pad_top' => 28, 'pad_left' => 34, 'pad_right' => 12, 'pad_bottom' => 36,
                'bar_width' => 48, 'bar_round' => 8,
                'show_grid_v' => false, 'show_grid_h' => true,
                'axis_max_v' => max(4, ceil(max([0, ...$faixas]) / 4) * 4),
                'minimum_units_y' => 1, 'axis_font_size' => 12,
                'data_label_position' => 'above',
            ]),
        ];
    }

    private function grafico(string $tipo, int $largura, int $altura, array $valores, array $cores, array $opcoes): string
    {
        $grafico = new SVGGraph($largura, $altura, array_replace([
            'back_colour' => '#ffffff', 'back_stroke_width' => 0,
            'font' => 'DejaVu Sans', 'axis_font' => 'DejaVu Sans',
            'axis_text_colour' => '#748496', 'show_axes' => true, 'axis_colour' => '#e6edf4', 'axis_stroke_width' => 0.5,
            'show_divisions' => false, 'show_subdivisions' => false,
            'grid_colour' => '#e6edf4', 'grid_dash' => '3,5',
            'show_data_labels' => true, 'data_label_font' => 'DejaVu Sans',
            'data_label_font_size' => 15, 'data_label_font_weight' => 'bold',
            'data_label_colour' => '#243e54', 'data_label_back_colour' => 'none',
            'data_label_space' => 10, 'data_label_padding' => 0,
            'stroke_width' => 0, 'show_tooltips' => false,
            'show_context_menu' => false, 'show_legend' => false,
            'exception_throw' => true, 'precision' => 2,
        ], $opcoes));
        $grafico->values($valores);
        $grafico->colours($cores);

        return 'data:image/svg+xml;base64,'.base64_encode($grafico->fetch($tipo, false));
    }
}
