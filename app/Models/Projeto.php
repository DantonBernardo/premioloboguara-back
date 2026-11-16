<?php

namespace App\Models;

use App\Services\Avaliacao;
use App\Services\ChaveProjeto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['nome', 'subcategoria_id', 'notas', 'avaliador_ids'])]
class Projeto extends Model
{
    use HasFactory;

    protected $table = 'projetos';

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            return parent::save($options);
        }

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            try {
                return DB::connection($this->getConnectionName())->transaction(fn (): bool => parent::save($options));
            } catch (UniqueConstraintViolationException $exception) {
                if ($tentativa === 4 || ! preg_match('/projetos[._](chave|codigo)/i', $exception->getMessage())) {
                    throw $exception;
                }
                unset($this->attributes['chave'], $this->attributes['codigo']);
            }
        }

        return false;
    }

    protected static function booted(): void
    {
        static::saving(function (Projeto $projeto): void {
            if ($projeto->exists && $projeto->isDirty(['chave', 'codigo'])) {
                throw ValidationException::withMessages(['codigo' => 'A identificação do projeto é gerada pelo servidor e não pode ser alterada.']);
            }
            if ($projeto->exists && $projeto->isDirty('subcategoria_id')
                && json_decode($projeto->getRawOriginal('notas') ?? '[]', true) !== []) {
                throw ValidationException::withMessages(['subcategoria_id' => 'Não é possível reclassificar um projeto que já recebeu avaliações.']);
            }

            $subcategoria = Subcategoria::with('categoria')->find($projeto->subcategoria_id);
            if ($subcategoria === null) {
                throw ValidationException::withMessages(['subcategoria_id' => 'Selecione uma subcategoria válida.']);
            }
            $projeto->setRelation('subcategoria', $subcategoria);
            $projeto->chave = $projeto->exists ? $projeto->getRawOriginal('chave') : app(ChaveProjeto::class)->gerar();
            $projeto->codigo = $projeto->chave.'/'.$subcategoria->categoria->codigo.'/'.$subcategoria->codigo;

            $servico = app(Avaliacao::class);
            $notas = $servico->normalizar($subcategoria->categoria->codigo, $projeto->notas ?? []);
            $projeto->notas = $notas;
            $projeto->nota_final = $servico->media($notas);
        });
    }

    protected function casts(): array
    {
        return [
            'notas' => 'array',
            'avaliador_ids' => 'array',
            'nota_final' => 'float',
        ];
    }
}
