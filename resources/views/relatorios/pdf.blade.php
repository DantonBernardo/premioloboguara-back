@php
    $nota = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', '.');
    $coresCategoria = \App\Services\GraficosRelatorio::CORES_CATEGORIA;
    $coresStatus = \App\Services\GraficosRelatorio::CORES_STATUS;
    $total = $r['totais']['projetos'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Prêmio Lobo-guará - Relatório</title>
<style>
@page { margin: 25mm 14mm 19mm; }
* { font-family: "DejaVu Sans", sans-serif; }
body { font-size: 8pt; color: #243e54; line-height: 1.35; }
header { margin-bottom: 16px; border-bottom: 2px solid #6fa2cf; padding-bottom: 8px; }
header strong { font-size: 13pt; } header span { font-size: 7pt; color: #748496; }
footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #dfe7ef; padding-top: 6px; color: #748496; font-size: 6.5pt; }
.page:after { content: counter(page); }
h1 { font-size: 23pt; margin: 0 0 3px; letter-spacing: -1px; } h2 { font-size: 11pt; margin: 0 0 10px; }
p { margin: 0; } .muted { color: #748496; } .small { font-size: 6.5pt; } .eyebrow { color: #579bd3; font-size: 7pt; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 8px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } td { vertical-align: top; }
.metrics { margin: 16px 0; } .metrics td { width: 25%; background: #f2f6fa; padding: 12px; border-right: 5px solid white; } .value { font-size: 22pt; font-weight: bold; } .label { color: #748496; font-size: 7pt; }
.chart-wide { border: 1px solid #dfe7ef; border-top: 3px solid #6fa2cf; padding: 14px 14px 6px; } .chart-total { font-size: 22pt; font-weight: bold; }
.card { border: 1px solid #dfe7ef;  padding: 14px; } .charts td { padding-bottom: 12px; } .legend td { padding: 4px 0; font-size: 7pt; } .dot { display: inline-block; width: 6px; height: 6px;  margin-right: 5px; }
.list-page { page-break-before: always; } .projects { margin-top: 14px; } .projects thead { display: table-header-group; } .projects th { background: #243e54; color: white; padding: 10px 7px; font-size: 6.5pt; text-align: left; } .projects td { border-bottom: 1px solid #e3eaf1; padding: 8px 7px; font-size: 7pt; } .projects tr { page-break-inside: avoid; } .projects tr.alt td { background: #f7f9fc; }
.name { font-weight: bold; margin-bottom: 4px; } .code { font-size: 6pt; color: #748496; } .done { color: #28866b; font-weight: bold; } .pending { color: #ac6a29; } .unassigned { color: #95a1ae; } .status { font-size: 6.5pt; margin-top: 4px; }
</style></head><body>
<header><strong>Prêmio Lobo-guará</strong><span style="float:right; margin-top:5px;">GUARAPUAVA · SECTI · 2026</span></header>
<footer>Sistema desenvolvido por SECTI · {{ $agora->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<span style="float:right;">Relatório de gestão · <span class="page"></span></span></footer>
<p class="eyebrow">Visão executiva</p><h1>Inovação em números.</h1>
<p class="muted">Inscrições e andamento das avaliações</p>
@if ($r['filtros']['categoria'] || $r['filtros']['status'])
<p class="small muted" style="margin-top:8px;">Recorte: {{ collect($r['categorias'])->whereIn('codigo', (array) $r['filtros']['categoria'])->pluck('nome')->implode(', ') ?: 'Todas as categorias' }} · {{ collect((array) $r['filtros']['status'])->map(fn ($status) => \App\Services\Relatorios::STATUS[$status])->implode(', ') ?: 'Todas as situações' }}</p>
@endif
<table class="metrics"><tr>
<td><div class="value">{{ $total }}</div><div class="label">Projetos inscritos</div></td>
<td><div class="value">{{ $r['totais']['avaliacoes_recebidas'] }}<span style="font-size:10pt; color:#748496;">/{{ $r['totais']['avaliacoes_previstas'] }}</span></div><div class="label">Avaliações recebidas</div></td>
<td><div class="value">{{ round($r['totais']['percentual_concluido']) }}%</div><div class="label">Avaliações concluídas</div></td>
<td><div class="value">{{ $nota($r['totais']['media_geral']) }}</div><div class="label">Média geral</div></td>
</tr></table>
<div class="chart-wide">
<table><tr><td><span class="eyebrow">01 / PARTICIPAÇÃO</span><h2>Onde a inovação acontece</h2></td><td style="width:90px; text-align:right;"><span class="chart-total">{{ $total }}</span><div class="small muted">inscrições</div></td></tr></table>
<img src="{{ $graficos['categorias'] }}" style="width:100%; height:180px;">
</div>
<table class="charts" style="margin-top:14px;"><tr>
<td style="width:45%; padding-right:12px;"><div class="card">
<p class="eyebrow">02 / SITUAÇÃO</p><h2>Retrato das avaliações</h2>
<div style="text-align:center;"><img src="{{ $graficos['situacao'] }}" style="width:165px; height:143px;"></div>
<table class="legend">@foreach($r['por_status'] as $s)<tr><td><span class="dot" style="background:{{ $coresStatus[$s['status']] }};"></span>{{ $s['rotulo'] }}</td><td style="width:52px; text-align:right;"><strong>{{ $s['total'] }}</strong> <span class="muted"> / {{ $total ? round($s['total'] / $total * 100) : 0 }}%</span></td></tr>@endforeach</table>
</div></td>
<td style="width:55%;"><div class="card">
<p class="eyebrow">03 / DESEMPENHO</p><h2>Como estão as médias</h2>
<img src="{{ $graficos['notas'] }}" style="width:100%; height:210px;">
<p class="small muted" style="margin-top:9px;">Projetos por faixa de nota · médias parciais</p>
</div></td></tr></table>
@foreach (collect($r['projetos'])->chunk(7)->whenEmpty(fn ($c) => $c->push(collect())) as $lote)
<div style="page-break-before:always;"></div>
<header><strong>Prêmio Lobo-guará</strong><span style="float:right; margin-top:5px;">GUARAPUAVA · SECTI · 2026</span></header>
<p class="eyebrow">Acompanhamento</p><h1>Projetos e avaliações.</h1><p class="small muted">✓ Avaliação recebida &nbsp; · &nbsp; ○ Pendente &nbsp; · &nbsp; — Não designado</p>
<table class="projects"><thead><tr><th style="width:39%;">Projeto</th><th style="width:20%;">Avaliador 1</th><th style="width:20%;">Avaliador 2</th><th style="width:7%;">Média</th><th style="width:14%;">Situação</th></tr></thead><tbody>
@forelse($lote as $p)
<tr class="{{ $loop->even ? 'alt' : '' }}"><td><div class="name">{{ $p['nome'] }}</div><div class="code">{{ $p['codigo'] }}</div><div class="small" style="color:{{ $coresCategoria[$p['categoria_codigo']] ?? '#579bd3' }}; margin-top:4px;">{{ $p['categoria'] }}</div></td>
@for($i = 0; $i < 2; $i++)
<td>@php $slots = array_slice($p['avaliadores'], $i, 1); @endphp
@forelse($slots as $a)<div style="margin-bottom:5px;">{{ $a['nome'] }}<div class="status {{ $a['concluida'] ? 'done' : 'pending' }}">{{ $a['concluida'] ? '✓ Recebida' : '○ Pendente' }}</div></div>@empty<span class="unassigned">—<br>Não designado</span>@endforelse</td>
@endfor
<td><strong>{{ $nota($p['nota_final']) }}</strong></td>
@php
    $situacao = match (true) {
        $p['status'] === 'inabilitada' => 'inabilitada',
        $p['avaliacoes_recebidas'] === 0 => 'sem_avaliacao',
        $p['avaliacoes_recebidas'] < 2 => 'em_avaliacao',
        default => $p['status'],
    };
@endphp
<td><strong class="small" style="color:{{ $coresStatus[$situacao] }};">{{ \App\Services\Relatorios::STATUS[$situacao] }}</strong></td></tr>
@empty<tr><td colspan="6" class="muted">Nenhum projeto neste recorte.</td></tr>@endforelse
</tbody></table>
@endforeach
</body></html>
