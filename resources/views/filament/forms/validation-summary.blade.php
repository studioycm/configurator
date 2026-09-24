@php($validationErrors = $getLivewire()->getErrorBag())
@if ($validationErrors->any())
    <div role="alert" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-950">
        <p class="font-semibold">Repair these items before saving</p>
        <ul class="mt-2 list-inside list-disc">
            @foreach (array_unique($validationErrors->all()) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
