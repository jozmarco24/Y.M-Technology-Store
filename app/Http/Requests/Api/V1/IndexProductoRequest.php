<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('productos.ver') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
