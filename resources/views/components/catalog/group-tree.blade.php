<nav aria-label="{{ __('catalog.browse.group_tree') }}" class="catalog-tree">
    <button type="button" @click="chooseBranch(null)" :aria-current="criteria.branchId === null ? 'location' : null" class="catalog-tree-root">{{ __('catalog.browse.all_models') }}</button>
    <div id="catalog-tree-stream">
        <template x-for="row in visibleTreeRows" :key="row.id">
            <div>
                <div class="catalog-tree-row" :style="'--tree-depth:' + Math.min(row.depth, 4)">
                    <button type="button" class="catalog-tree-expand" x-show="row.hasChildren" @click="toggleTree(row)" :aria-expanded="!!expanded[row.id]" aria-controls="catalog-tree-stream" :aria-label="(expanded[row.id] ? @js(__('catalog.browse.collapse')) : @js(__('catalog.browse.expand'))) + ' ' + row.name"><span x-text="expanded[row.id] ? '−' : '+'" aria-hidden="true"></span></button>
                    <span x-show="!row.hasChildren" aria-hidden="true" class="catalog-tree-leaf">·</span>
                    <button type="button" class="catalog-tree-name" :data-catalog-focus="'tree-' + row.id" @click="chooseBranch(row.id)" :aria-current="criteria.branchId === row.id ? 'location' : null" :title="row.name + (row.depth > 4 ? ' · ' + @js(__('catalog.browse.depth')) + ' ' + (row.depth + 1) : '')"><span x-text="row.name"></span><small x-show="row.depth > 4" x-text="@js(__('catalog.browse.depth')) + ' ' + (row.depth + 1)"></small></button>
                    <span class="catalog-tree-count" :title="@js(__('catalog.browse.model_count'))" x-text="row.modelCount"></span>
                </div>
                <div class="catalog-tree-feedback" x-show="treeRequests[row.id]" role="status">{{ __('catalog.browse.loading') }}</div>
                <div class="catalog-tree-feedback" x-show="treeErrors[row.id]"><span role="status" x-text="treeErrors[row.id]"></span> <button type="button" @click="retryTree(row.id)">{{ __('catalog.browse.retry') }}</button></div>
                <button type="button" class="catalog-tree-more" x-show="expanded[row.id] && treePages[row.id]?.hasMore" @click="moreTree(row.id)" :disabled="!!treeRequests[row.id]">{{ __('catalog.browse.more_children') }} <span x-text="row.name"></span></button>
            </div>
        </template>
    </div>
    <button type="button" class="catalog-tree-more" x-show="treePages.root?.hasMore" @click="moreTree(null)" :disabled="!!treeRequests.root">{{ __('catalog.browse.more_roots') }}</button>
    <div x-show="treeErrors.root" role="status"><span x-text="treeErrors.root"></span> <button type="button" @click="retryTree(null)">{{ __('catalog.browse.retry') }}</button></div>
</nav>
