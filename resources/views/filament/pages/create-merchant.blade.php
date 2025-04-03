<x-filament::page>
    {{ $this->form }}
    <div class="mt-6">
        @foreach ($this->getFormActions() as $action)
            {{ $action }}
        @endforeach
    </div>
</x-filament::page>
