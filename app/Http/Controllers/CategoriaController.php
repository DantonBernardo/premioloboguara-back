<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Categoria::with(['subcategorias' => fn ($query) => $query->orderBy('codigo')])->orderBy('codigo')->get());
    }
}
