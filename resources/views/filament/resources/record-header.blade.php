<header class="fi-header catalog-record-header">
    <div>
        @if ($this->recordHeadingLevel() === 3)
            <h3 class="fi-header-heading">{{ $this->getHeading() }}</h3>
        @else
            <h2 class="fi-header-heading">{{ $this->getHeading() }}</h2>
        @endif
    </div>
    <div class="fi-header-actions-ctn">
        <x-filament::actions :actions="$this->getCachedHeaderActions()" :alignment="$this->getHeaderActionsAlignment()" />
    </div>
</header>
