<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['codigo', 'nome'])]
class Categoria extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Categoria $categoria): void {
            if ($categoria->isDirty('codigo')) {
                throw ValidationException::withMessages(['codigo' => 'O código da categoria é permanente.']);
            }
        });
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class);
    }
}
