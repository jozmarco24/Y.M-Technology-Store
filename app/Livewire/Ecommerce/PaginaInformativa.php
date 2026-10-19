<?php

declare(strict_types=1);

namespace App\Livewire\Ecommerce;

use App\Models\Pagina;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PaginaInformativa extends Component
{
    public Pagina $pagina;

    public function mount(string $slug): void
    {
        $this->pagina = Pagina::where('slug', $slug)->where('publicada', true)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.ecommerce.pagina-informativa')
            ->layout('layouts.ecommerce', [
                'metaTitle' => ($this->pagina->meta_titulo ?: $this->pagina->titulo).' | SYS-COPM',
                'metaDescription' => $this->pagina->meta_descripcion ?: "Información sobre {$this->pagina->titulo} en nuestra tienda online.",
            ]);
    }
}
