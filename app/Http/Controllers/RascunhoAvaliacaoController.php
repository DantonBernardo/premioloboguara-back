<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarRascunhoRequest;
use App\Models\Projeto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RascunhoAvaliacaoController extends Controller
{
    public function update(SalvarRascunhoRequest $request, Projeto $projeto): JsonResponse
    {
        $dados = $request->validated();
        $dados['etapa'] = (int) $dados['etapa'];
        foreach ($dados['notas'] as $codigo => $valor) {
            $dados['notas'][$codigo] = $valor === null ? null : (float) $valor;
        }

        return DB::transaction(function () use ($request, $projeto, $dados): JsonResponse {
            $atual = Projeto::query()->lockForUpdate()->findOrFail($projeto->id);
            $id = $request->user()->id;
            abort_unless(in_array($id, array_map('intval', $atual->avaliador_ids ?? []), true), 403);
            abort_if(collect($atual->notas ?? [])->contains(fn (array $nota): bool => (int) $nota['avaliador_id'] === $id), 409, 'Esta avaliação já foi publicada e não pode receber rascunhos.');
            $agora = now();
            DB::table('avaliacao_rascunhos')->upsert([[
                'projeto_id' => $atual->id, 'avaliador_id' => $id,
                'dados' => json_encode($dados, JSON_THROW_ON_ERROR), 'created_at' => $agora, 'updated_at' => $agora,
            ]], ['projeto_id', 'avaliador_id'], ['dados', 'updated_at']);

            return response()->json(['rascunho' => $dados + ['salvo_em' => $agora->toISOString()]]);
        }, 3);
    }
}
