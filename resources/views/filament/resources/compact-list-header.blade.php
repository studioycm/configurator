<x-filament-panels::header
    class="catalog-resource-list-header"
    :actions="$this->getCachedHeaderActions()"
    :actions-alignment="$this->getHeaderActionsAlignment()"
    :breadcrumbs="$this->getBreadcrumbs()"
    :heading="$this->getTitle()"
/>
