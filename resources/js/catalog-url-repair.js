// Repair only the catalog snapshot after Livewire has finished its own history update.
// The server supplies the canonical state; ordinary actions remain owned by #[Url].
document.addEventListener('livewire:init', () => {
    Livewire.hook('component.init', ({ component, cleanup }) => {
        if (!component.el.hasAttribute('data-catalog-state')) return;

        let frame;
        const repair = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                const root = component.el;
                if (!root.isConnected || root.dataset.catalogRepair !== '1') return;

                const canonical = JSON.parse(root.dataset.catalogState);
                const expected = new URL(root.dataset.catalogUrl, location.href);
                const current = new URL(location.href);
                if (!canonical || current.pathname !== expected.pathname) return;

                for (const key of [...current.searchParams.keys()]) {
                    if (key === 'd' || key.startsWith('d[')) current.searchParams.delete(key);
                }
                for (const [key, value] of expected.searchParams) current.searchParams.append(key, value);

                const state = history.state ?? {};
                history.replaceState({
                    ...state,
                    alpine: { ...state.alpine, d: { ...state.alpine?.d, value: canonical } },
                }, '', current);
            });
        };

        // Back supersedes unfinished read-only discovery actions. Cancel them before
        // Livewire's native popstate handler restores the complete URL snapshot.
        const pending = new Set();
        const stopActions = component.$wire.intercept(({ action, onFinish }) => {
            pending.add(action);
            onFinish(() => pending.delete(action));
        });
        const onPop = (event) => {
            if (!event.state?.alpine?.d) return;
            cancelAnimationFrame(frame);
            for (const action of [...pending]) action.cancel();
        };
        window.addEventListener('popstate', onPop, true);

        repair();
        const stop = Livewire.hook('morphed', ({ component: changed }) => {
            if (changed.id === component.id) repair();
        });
        cleanup(() => {
            cancelAnimationFrame(frame);
            window.removeEventListener('popstate', onPop, true);
            stopActions();
            stop();
        });
    });
});
