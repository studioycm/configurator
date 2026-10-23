let identifierIntentSequence = 0;

export function nextIdentifierIntent() {
    return String(++identifierIntentSequence);
}

export function suggestedIdentifier(config) {
    return {
        suggested: false,
        timer: null,
        active: true,
        init() { this.identifierIntent(); },
        identifierIntent() {
            this.$wire.$set(config.intent, nextIdentifierIntent(), false);
        },
        identifierInput() {
            this.identifierIntent();
            this.$wire.$set(config.used, true, false);
        },
        receiveIdentifier(detail) {
            if (!this.active || !this.$el.isConnected || detail.livewireId !== this.$wire.$id ||
                detail.field !== config.field || detail.rowRef !== (this.$wire.$get(config.ref) ?? null) ||
                detail.intent !== this.$wire.$get(config.intent) ||
                detail.label !== this.$wire.$get(config.label)) return;
            if (!detail.explicit && (this.$wire.$get(config.used) || String(this.$wire.$get(config.field) ?? '').trim() !== '')) return;
            this.$wire.$set(config.field, detail.candidate, false);
            this.$wire.$set(config.used, true, false);
            this.identifierIntent();
            clearTimeout(this.timer);
            this.suggested = true;
            this.timer = setTimeout(() => { this.suggested = false; }, 1200);
        },
        destroy() {
            this.active = false;
            clearTimeout(this.timer);
        },
    };
}
