@if ($getLivewire()->getErrorBag()->any())
    <div role="alert" class="rounded-lg border border-red-300 p-3">
        <p class="font-semibold">Review these fields before including Attributes</p>
        <ul class="mt-2 space-y-1">
            @foreach ($getLivewire()->getErrorBag()->messages() as $path => $messages)
                @php($fieldId = $getLivewire()->getMountedActionSchema()?->getComponentByStatePath($path, withHidden: true, withAbsoluteStatePath: true)?->getId())
                <li wire:key="configurator-review-error-{{ sha1($path) }}" x-data="{ reveal(focus = false) { const field = document.getElementById(@js($fieldId)); if (!field) return; for (let parent = field.parentElement; parent; parent = parent.parentElement) parent.dispatchEvent(new CustomEvent('expand')); if (focus) $nextTick(() => { field.scrollIntoView({ block: 'center', behavior: 'smooth' }); (field.matches('input, select, textarea, button, [tabindex]') ? field : field.querySelector('input, select, textarea, button, [tabindex]'))?.focus(); }); } }" x-init="$nextTick(() => reveal())">
                    <button type="button" class="text-sm underline" x-on:click="reveal(true)">{{ $messages[0] }}</button>
                </li>
            @endforeach
        </ul>
    </div>
@endif
