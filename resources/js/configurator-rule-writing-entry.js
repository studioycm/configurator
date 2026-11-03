export function ruleWritingEntry(config = {}) {
    return {
        enter(event) {
            if (event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.currentTarget?.target === '_blank') return;
            const workspace = event.currentTarget?.closest('[data-configurator-workspace]');
            if (workspace?.querySelector('[data-draft-dirty="true"]') && !(config.confirm ?? globalThis.confirm)(config.message ?? 'Leave the ordinary editor? Its unsaved changes will not be saved by Writing.')) event.preventDefault();
        },
    };
}
