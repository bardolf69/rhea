<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }} }">
        <progress max="100" x-bind:value="state" class="w-full h-12 rounded-md" @progressUpdated="$refresh"></progress>
    </div>
</x-dynamic-component>