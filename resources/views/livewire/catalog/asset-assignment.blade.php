<div class="catalog-asset-assignment" x-data="catalogAssetAssignment({ user: {{ auth()->id() }} })" x-on:image-review-command.stop="command($event.detail.method, ...$event.detail.args)">
    <div class="catalog-images-bar">
        <h2 class="text-lg font-semibold">{{ $operation === 'one_image' ? __('catalog-images.assign_one') : __('catalog-images.match_folder') }}</h2>
        <span>{{ $reviewProgress['total'] ?? $recordCount }} {{ $targetKey === 'product.main_image' ? 'Products' : 'Groups' }}</span>
        {{ $this->backAction }}
    </div>
    @if ($errors->any())<div role="alert" class="catalog-images-notice">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <fieldset x-bind:disabled="running || unknown">
        {{ $this->matchForm }}
        <div class="catalog-images-bar">
            {{ $this->openSourceAction }}
            {{ $this->scanAction }}
            {{ $this->previewAction }}
        </div>
        @if ($oneImageChoice)<p class="text-sm">One source image chosen. Preview will propose it for the checked records.</p>@endif
        @php($example = $this->recipeExample())
        @if ($example)<p class="catalog-images-notice">{{ $example['raw'] ?? 'No valid field' }} @if($example['truncated'] ?? false) (display shortened) @endif → {{ $example['identifier'] ?? '' }} → {{ isset($example['identifier']) ? $example['identifier'].'.[jpg/png/webp]' : ($example['reason'] ?? '') }}</p>@endif
    </fieldset>
    <div class="catalog-images-bar" role="status">
        @if($sourceProgress)<span>Source: {{ $sourceProgress['stage'] }} · {{ $sourceProgress['message'] ?? '' }}</span>@endif
        @if($reviewProgress)<span>{{ $reviewProgress['classified'] }} / {{ $reviewProgress['total'] }} classified · {{ $reviewProgress['stage'] }}</span>@endif
    </div>
    <div class="catalog-images-bar">
        {{ $this->continueAction }}
        <x-filament::button color="gray" :wire:loading.attr="false" x-on:click="stop()" x-bind:disabled="!running">Stop</x-filament::button>
        {{ $this->refreshAction }}
    </div>
    <p role="status" x-text="message" x-show="message" class="catalog-images-notice"></p>
    @if($reviewProgress)
        <div class="catalog-images-bar">
            @foreach($reviewProgress['counts'] as $status => $count)<span>{{ ucfirst($status) }}: {{ $count }}</span>@endforeach
            <span>Explicitly excluded: {{ $reviewProgress['explicitly_excluded'] ?? 0 }}</span><span>Deferred: {{ $reviewProgress['deferred'] ?? 0 }}</span>
        </div>
        <div class="catalog-images-layout">
            <section class="catalog-images-records" aria-label="Image review">
                <label class="sr-only" for="image-review-search-{{ $launchSession }}">Search review</label>
                <x-filament::input.wrapper><x-filament::input id="image-review-search-{{ $launchSession }}" wire:model.live.debounce.350ms="reviewSearch" placeholder="Search record, identifier or filename…" maxlength="500" /></x-filament::input.wrapper>
                <div class="catalog-asset-review-native">{{ $this->table }}</div>
                <div class="catalog-images-footer" x-data="{ custom: false, size: '' }">
                    <x-filament::input.wrapper><x-filament::input.select title="Rows per page" aria-label="Rows per page"
                        x-on:change="if ($event.target.value === 'custom') { custom = true; } else { custom = false; command('setReviewPageSize', $event.target.value); }">
                        @foreach($this->reviewPageOptions() as $size)<option value="{{ $size }}" @selected($this->getTableRecordsPerPage() === $size)>{{ $size }}</option>@endforeach
                        <option value="custom">Custom</option>
                    </x-filament::input.select></x-filament::input.wrapper>
                    <form x-cloak x-show="custom" x-on:submit.prevent="command('setReviewPageSize', size)" class="catalog-images-bar">
                        <x-filament::input.wrapper><x-filament::input x-model="size" type="text" inputmode="numeric" maxlength="3" aria-label="Custom rows per page (1–500)" /></x-filament::input.wrapper>
                        <x-filament::button type="submit" size="sm">Set</x-filament::button>
                    </form>
                    <x-filament::pagination :paginator="$this->getTableRecords()" :page-options="[]" />
                </div>
            </section>
            @include('livewire.catalog.asset-review-inspector')
        </div>
        @if(($reviewProgress['stage'] ?? '') === 'review')<p class="catalog-images-notice">Review ready — applying images is not available in this stage.</p>@endif
    @endif
    @if($sourceId !== null && (($operation === 'one_image') || $reviewItemId !== null))
        @php($candidates = $this->candidateResults())
        <section class="catalog-asset-candidates" aria-label="Source image candidates">
            <h3 class="font-semibold">Choose an exact source file</h3>
            @if(isset($candidates['error']))<p role="alert" class="catalog-images-notice">{{ $candidates['error'] }}</p>@endif
            @if($reviewItemId !== null)
                <label class="catalog-images-bar"><input type="checkbox" wire:model.live="allSourceFiles" />Browse all source files</label>
                {{ $this->candidateForm }}
                <x-filament::button color="gray" x-on:click="command('useCandidateSource')">Use candidate source</x-filament::button>
            @endif
            <label class="sr-only" for="candidate-search-{{ $launchSession }}">Search source filenames</label>
            <x-filament::input.wrapper><x-filament::input id="candidate-search-{{ $launchSession }}" wire:model.live.debounce.350ms="candidateSearch" placeholder="Search source filenames…" maxlength="250" /></x-filament::input.wrapper>
            <div class="catalog-asset-candidate-grid">
                @foreach($candidates['entries'] as $candidate)
                    <button type="button" wire:key="candidate-{{ $candidate['choice']['source_id'] }}-{{ $candidate['choice']['scan_version'] }}-{{ $candidate['choice']['entry_id'] }}" class="catalog-asset-candidate"
                        x-bind:disabled="running || unknown || ($wire.batchId &amp;&amp; $wire.reviewProgress.stage !== 'review')" x-on:click="command({{ \Illuminate\Support\Js::from($reviewItemId === null ? 'chooseOneImage' : 'resolveReviewCandidate') }}, {{ \Illuminate\Support\Js::from($candidate['choice']) }})">
                        <img src="{{ $candidate['url'] }}" alt="" loading="lazy" x-on:error="$el.hidden = true; $el.nextElementSibling.hidden = false" />
                        <span hidden>Source image unavailable. Refresh or choose another valid source.</span>
                        <span>{{ $candidate['relative_path'] }}</span><small>{{ $candidate['width'] }} × {{ $candidate['height'] }}</small>
                    </button>
                @endforeach
            </div>
            <div class="catalog-images-bar"><span>{{ $candidates['total'] }} candidates · {{ $candidates['page'] }} / {{ $candidates['pages'] }}</span>
                <x-filament::button color="gray" x-on:click="command('previousCandidatePage')" :disabled="$candidates['page'] <= 1">Previous files</x-filament::button>
                <x-filament::button color="gray" x-on:click="command('nextCandidatePage')" :disabled="$candidates['page'] >= $candidates['pages']">Next files</x-filament::button>
            </div>
        </section>
    @endif
    @if($reviewNotice)<p role="status" class="catalog-images-notice">{{ $reviewNotice }}</p>@endif
    <x-filament-actions::modals />
</div>
