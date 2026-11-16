<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['codigo', 'nome', 'categoria_id'])]
class Subcategoria extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Subcategoria $subcategoria): void {
            if ($subcategoria->isDirty(['codigo', 'categoria_id'])) {
                throw ValidationException::withMessages(['codigo' => 'O código e a categoria da subcategoria são permanentes.']);
            }
        });
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function projetos(): HasMany
    {
        return $this->hasMany(Projeto::class);
    }
}
