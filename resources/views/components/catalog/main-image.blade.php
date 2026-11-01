@props(['image' => null, 'decorative' => true, 'alt' => ''])
<div {{ $attributes->class(['catalog-main-image', 'catalog-image-placeholder']) }}
    x-data="catalogImage({{ \Illuminate\Support\Js::from($image) }})"
    data-image-state="{{ $image === null ? 'missing' : 'loading' }}" :data-image-state="status"
    @if ($decorative) aria-hidden="true" @endif>
    @if ($image !== null)
        <img x-ref="initialImage" src="{{ $image['thumbnail_url'] }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" alt="{{ $decorative ? '' : $alt }}"
            x-show="attemptIdentity === 0 && currentUrl" x-on:load="onLoad(0)" x-on:error="onError(0)" decoding="async" />
    @endif
    <template x-for="attempt in attempts" :key="attempt.id">
        <img :src="attempt.url" :width="attempt.width" :height="attempt.height" alt="{{ $decorative ? '' : $alt }}"
            x-on:load="onLoad(attempt.id)" x-on:error="onError(attempt.id)" decoding="async" />
    </template>
    <svg x-show="!currentUrl" @if ($image !== null) style="display:none" @endif viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="5" width="24" height="22" rx="3"/><circle cx="11" cy="12" r="2"/><path d="m5 23 8-7 5 4 4-3 6 6"/></svg>
    <span x-show="!currentUrl" @if ($image !== null) style="display:none" @endif x-text="status === 'unavailable' ? @js(__('catalog.browse.image_unavailable')) : @js(__('catalog.browse.no_image'))">{{ __('catalog.browse.no_image') }}</span>
</div>
