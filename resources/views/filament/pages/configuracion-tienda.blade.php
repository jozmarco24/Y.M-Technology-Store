<x-filament-panels::page>
    <x-filament-panels::form wire:submit="guardar">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Guardar Configuración
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
