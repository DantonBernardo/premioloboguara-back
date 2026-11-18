@php $nota = fn ($n) => $n === null ? '—' : number_format($n, 2, ',', '.'); $p = $ficha['projeto']; @endphp
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Ficha do projeto</title><style>
@page { margin: 18mm 15mm 20mm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; color: #203548; line-height: 1.5; }
h1 { font-size: 21pt; line-height: 1.25; margin: 12px 0; } h2 { font-size: 16pt; margin: 6px 0; } h3 { font-size: 10pt; margin: 0 0 5px; }
p { margin: 4px 0; } .muted { color: #64788c; font-size: 8pt; } .eyebrow { font-size: 7pt; letter-spacing: 1px; color: #579bd3; text-transform: uppercase; }
.hero { background: #173c50; color: white; padding: 22px; } .hero .muted { color: #bed2e2; } .summary { width: 100%; border-collapse: collapse; margin: 20px 0; } .summary td { padding: 14px; background: #f0f5fa; width: 33%; vertical-align: top; border-right: 4px solid white; } .big { font-size: 25pt; font-weight: bold; }
.section { page-break-before: always; } .heading { border-bottom: 3px solid #6fa2cf; padding-bottom: 14px; margin-bottom: 20px; }
.criterion { border-top: 1px solid #dce4eb; padding: 12px 0; } .score { float: right; font-weight: bold; } .text { white-space: pre-wrap; overflow-wrap: break-word; font-size: 8pt; color: #52687b; } .callout { background: #f0f5fa; padding: 15px; margin-top: 18px; } .danger { color: #b3261e; } .success { color: #28774c; }
footer { position: fixed; bottom: -12mm; color: #64788c; font-size: 6pt; } .page:after { content: counter(page); }
</style></head><body>
<footer>Prêmio Lobo-guará · SECTI · {{ $agora->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · Página <span class="page"></span></footer>
<div class="hero"><span class="eyebrow">Ficha administrativa · Prêmio Lobo-guará</span><h1>{{ $p['nome'] }}</h1><p>{{ $p['categoria'] }}</p><p class="muted">{{ $p['subcategoria'] }}</p><p class="muted">ID #{{ $p['id'] }} · {{ $p['codigo'] }}</p></div>
<table class="summary"><tr><td><span class="muted">Média atual</span><div class="big">{{ $nota($p['nota_final']) }}</div><span class="muted">de 10 pontos</span></td><td><span class="muted">Avaliações recebidas</span><div class="big">{{ $p['avaliacoes_recebidas'] }}/2</div></td><td><span class="muted">Situação</span><h3 style="margin-top:12px">{{ $p['status_rotulo'] }}</h3></td></tr></table>
@if($p['zero_eliminatorio'])<p class="danger">Zero eliminatório registrado: a média não reverte a inabilitação.</p>@elseif($p['avaliacoes_recebidas'] < 2)<p class="muted">Resultado provisório: aguardando as duas avaliações.</p>@endif
<h2>Responsáveis pelo projeto</h2>
@forelse($ficha['avaliadores'] as $a)<div class="criterion"><span class="score">{{ $a['avaliacao'] ? $nota($a['avaliacao']['total']).' / 10' : 'Pendente' }}</span><strong>{{ $a['nome'] }}</strong><p class="muted">{{ $a['organizacao'] }}</p></div>@empty<p class="muted">Nenhum avaliador associado.</p>@endforelse
@foreach($ficha['avaliadores'] as $a)
<div class="section heading"><span class="eyebrow">Avaliação {{ $loop->iteration }} · Projeto #{{ $p['id'] }}</span><h2>{{ $a['nome'] }}</h2><p class="muted">{{ $a['organizacao'] }}</p></div>
@php $n = $a['avaliacao']; @endphp
@if(!$n)<div class="callout">Aguardando avaliação. Nenhuma nota enviada.</div>@else
<p><span class="big">{{ $nota($n['total']) }}</span> / 10 · <strong class="{{ $n['habilitada'] ? 'success' : 'danger' }}">{{ $n['habilitada'] ? 'Habilitado' : 'Inabilitado' }} nesta avaliação</strong></p>
@foreach($ficha['regras']['criterios'] as $codigo => $c)<div class="criterion"><span class="score">{{ $nota($n[$codigo]) }} / {{ $c['maximo'] }}</span><h3 style="margin-right:85px">{{ $c['nome'] }}</h3>@if($c['maximo'] === 3 && $n[$codigo] == 0)<p class="danger">Zero eliminatório</p>@endif @if(!empty($n['justificativas'][$codigo]))<p class="text">{{ $n['justificativas'][$codigo] }}</p>@endif</div>@endforeach
@if($ficha['regras']['bonus_maximo'] > 0)<div class="criterion"><h3>{{ $ficha['regras']['bonus_nome'] }}: {{ $nota($n['bonus']) }}</h3><p class="text">{{ $n['justificativas']['bonus'] ?? '' }}</p></div>@endif
<div class="callout"><h3>Justificativa da nota total</h3><p class="text">{{ $n['justificativa_geral'] ?? 'Não registrada para esta avaliação.' }}</p></div>
@endif
@endforeach
</body></html>
