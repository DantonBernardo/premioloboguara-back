<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Resultados · Prêmio Lobo-guará</title>
<style>
@page { margin: 20mm 14mm; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #203548; }
h1 { font-size: 22pt; margin: 6px 0; } .muted { color: #63788c; font-size: 7pt; }
header { border-bottom: 3px solid #6fa2cf; padding-bottom: 14px; margin-bottom: 18px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
thead { display: table-header-group; } th { background: #173c50; color: white; text-align: left; padding: 10px 7px; font-size: 7pt; }
td { border-bottom: 1px solid #dce4eb; padding: 10px 7px; vertical-align: top; overflow-wrap: break-word; }
tr { page-break-inside: avoid; } .code { margin-top: 5px; font-size: 6pt; color: #63788c; }
footer { position: fixed; bottom: -12mm; font-size: 6pt; color: #63788c; } .page:after { content: counter(page); }
</style></head><body>
<footer>SECTI · Guarapuava · {{ $agora->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · Página <span class="page"></span></footer>
<header><span class="muted">PRÊMIO LOBO-GUARÁ · 2026</span><h1>Resultados dos projetos</h1><span class="muted">{{ $projetos->count() }} projeto(s) com as duas avaliações concluídas · Resultado do recorte selecionado</span></header>
<table><thead><tr><th style="width:43%">Projeto</th><th style="width:27%">Categoria</th><th style="width:10%">Média</th><th style="width:20%">Situação</th></tr></thead><tbody>
@forelse($projetos as $p)
<tr><td><strong>{{ $p['nome'] }}</strong><div class="code">{{ $p['codigo'] }}</div></td><td>{{ $p['categoria'] }}<div class="code">{{ $p['subcategoria'] }}</div></td><td><strong>{{ number_format($p['nota_final'], 2, ',', '.') }}</strong></td><td style="color:{{ $p['status'] === 'habilitada' ? '#28774c' : '#b3261e' }}"><strong>{{ $p['status_rotulo'] }}</strong></td></tr>
@empty<tr><td colspan="4">Nenhum resultado concluído para os filtros selecionados.</td></tr>@endforelse
</tbody></table></body></html>
