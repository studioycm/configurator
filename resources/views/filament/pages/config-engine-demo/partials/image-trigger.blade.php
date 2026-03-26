<button
    type="button"
    wire:click="mountAction('viewImage', {{ \Illuminate\Support\Js::from(['url' => $url]) }})"
    class="group block w-full"
>
    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <img src="{{ $url }}" class="h-full w-full object-cover" loading="lazy" alt="{{ $alt ?? '' }}" />
    </div>

    <div class="{{ $captionClass ?? 'mt-1 text-[11px] text-primary-600 group-hover:underline' }}">
        {{ $caption ?? 'Enlarge' }}
    </div>
</button>
