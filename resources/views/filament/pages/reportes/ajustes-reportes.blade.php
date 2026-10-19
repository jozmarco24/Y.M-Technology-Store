<x-filament-panels::page>
    <form wire:submit.prevent="guardar">
        {{ $this->form }}

        <div class="flex justify-between items-center mt-6">
            <x-filament::button type="submit" color="primary" icon="heroicon-m-check">
                Guardar Ajustes
            </x-filament::button>

            <x-filament::button wire:click="enviarResumenPrueba" color="gray" icon="heroicon-m-paper-airplane">
                Enviar resumen de prueba ahora
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
