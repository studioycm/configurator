<livewire:catalog.tag-value-editor :value-id="$getRecord()->getKey()" :tag="$this->tag" :key="'tag-value-'.hash('sha256', $this->tag).'-'.$getRecord()->getKey()" />
