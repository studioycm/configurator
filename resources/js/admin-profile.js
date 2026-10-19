const maximumBytes = 32768;
const clone = value => JSON.parse(JSON.stringify(value));
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);

export function browserProfile(user, scope, storage) {
    const key = `aquestia:profile:${user}:admin:${scope}:v1`;
    return {
        key,
        read() {
            try {
                const raw = (storage ?? globalThis.localStorage).getItem(key);
                if (!raw || raw.length > maximumBytes) return null;
                const value = JSON.parse(raw);
                return object(value) ? value : null;
            } catch { return null; }
        },
        save(value) {
            try {
                if (!object(value)) return false;
                const raw = JSON.stringify(value);
                if (raw.length > maximumBytes) return false;
                if ((storage ?? globalThis.localStorage).getItem(key) !== raw) (storage ?? globalThis.localStorage).setItem(key, raw);
                return true;
            } catch { return false; }
        },
        clear() { try { (storage ?? globalThis.localStorage).removeItem(key); } catch {} },
    };
}

export function tableProfileState(state) {
    const result = {
        columns: (state.tableColumns ?? []).map(column => ({ name: column.name, isToggled: Boolean(column.isToggled) })),
        sort: state.tableSort ?? null,
        search: state.tableSearch ?? '',
        searchScope: state.tableSearchScope ?? 'all',
        filters: clone(state.tableFilters && Object.keys(state.tableFilters).length ? state.tableFilters : {}),
        perPage: Number(state.tableRecordsPerPage ?? 10),
    };
    for (const name of ['showSelection', 'showOrderControls']) if (typeof state[name] === 'boolean') result[name] = state[name];
    return result;
}

const properties = ['tableColumns', 'tableSort', 'tableSearch', 'tableSearchScope', 'tableFilters', 'tableRecordsPerPage', 'showSelection', 'showOrderControls'];

export function localTableProfile(config) {
    let profile, unsubscribe, restoring = false, destroyed = false;
    const read = wire => {
        const state = {};
        for (const property of properties) {
            // Optional visibility controls do not exist on every table component.
            if (wire[property] !== undefined) state[property] = wire[property];
        }
        return tableProfileState(state);
    };
    return {
        async init() {
            profile = browserProfile(config.user, `table:${config.scope}`);
            const capture = () => { if (!destroyed && !restoring) profile.save(read(this.$wire)); };
            unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.id !== this.$wire.$id) return;
                onSuccess(({ onMorphed }) => onMorphed(capture));
            });
            const saved = profile.read();
            if (!saved) { capture(); return; }
            const current = read(this.$wire);
            const changes = {};
            const query = new URLSearchParams(window.location.search);
            for (const [name, value] of Object.entries(saved)) {
                if (!Object.hasOwn(current, name)) continue;
                const queryKey = config.query?.[name];
                if (queryKey && [...query.keys()].some(key => key === queryKey || key.startsWith(`${queryKey}[`))) continue;
                if (JSON.stringify(value) !== JSON.stringify(current[name])) changes[name] = value;
            }
            if (!Object.keys(changes).length) { capture(); return; }
            restoring = true;
            try {
                await this.$wire.$call('restoreTableBrowserProfile', changes);
                restoring = false;
                capture();
            } catch {
                // Keep the saved profile after a failed request; a later visit can restore it.
                restoring = false;
            }
        },
        destroy() { destroyed = true; unsubscribe?.(); },
    };
}

const initializedComponents = new WeakSet();

export function rememberSchemaState(element, value, config) {
    const profile = browserProfile(config.user, `page:${window.location.pathname}:${config.kind}:${config.key}`);
    if (!initializedComponents.has(element)) {
        initializedComponents.add(element);
        const saved = profile.read();
        const data = window.Alpine.$data(element);
        if (config.kind === 'tab') {
            if (!(config.query && new URLSearchParams(window.location.search).has(config.query)) && typeof saved?.value === 'string' && data.getTabs?.().includes(saved.value)) {
                if (value !== saved.value) { data.tab = saved.value; return; }
            }
        } else if (typeof saved?.value === 'boolean' && value !== saved.value) {
            data.isCollapsed = saved.value;
            return;
        }
    }
    profile.save({ value });
}

export function localSectionProfile(config) {
    return {
        section: null,
        init() {
            this.$nextTick(() => {
                const section = this.$el.querySelector(':scope > section');
                if (!section) return;
                this.section = window.Alpine.$data(section);
                rememberSchemaState(section, this.section.isCollapsed, { ...config, kind: 'section' });
                this.$watch('section.isCollapsed', value => rememberSchemaState(section, value, { ...config, kind: 'section' }));
            });
        },
    };
}

export function rememberOrderControls(data, config, watch) {
    const key = config.key.replace(/[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}/gi, 'item');
    const profile = browserProfile(config.user, `page:${window.location.pathname}:ordering:${key}`);
    const saved = profile.read();
    for (const name of ['showDragOrder', 'showButtonOrder']) if (typeof saved?.[name] === 'boolean') data[name] = saved[name];
    const capture = () => profile.save({ showDragOrder: data.showDragOrder, showButtonOrder: data.showButtonOrder });
    for (const name of ['showDragOrder', 'showButtonOrder']) watch(name, capture);
}

export function localOrderProfile(config) {
    return {
        showDragOrder: false,
        showButtonOrder: true,
        init() { rememberOrderControls(this, config, (name, callback) => this.$watch(name, callback)); },
    };
}

export function localTraceProfile(config) {
    let unsubscribe;
    return {
        init() {
            const profile = browserProfile(config.user, `page:${window.location.pathname}:preview`);
            const saved = profile.read();
            if (typeof saved?.trace === 'boolean' && saved.trace !== this.$wire.showTrace) this.$wire.$call('toggleTrace');
            unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.id !== this.$wire.$id) return;
                onSuccess(({ onMorphed }) => onMorphed(() => profile.save({ trace: this.$wire.showTrace })));
            });
        },
        destroy() { unsubscribe?.(); },
    };
}

export function personalAppearance(settings, config) {
    if (!object(settings)) return null;
    settings = { ...config.settings, ...Object.fromEntries(Object.entries(settings).filter(([key]) => Object.hasOwn(config.settings, key) && key !== 'show_configurator_loading_indicator')) };
    for (const [key, [minimum, maximum]] of Object.entries(config.ranges)) {
        if (!Number.isInteger(settings[key]) || settings[key] < minimum || settings[key] > maximum) return null;
    }
    if (settings.logo_height > settings.header_height - 12 || Math.ceil(settings.page_title_size * 1.2) > settings.header_height - 12) return null;
    if (typeof settings.show_configurator_loading_indicator !== 'boolean') return null;
    for (const key of ['modal_width', 'slide_over_width']) if (!['small', 'medium', 'large'].includes(settings[key])) return null;
    const mapping = { cell_padding_block: 'cell-padding-block', cell_padding_inline: 'cell-padding-inline', workspace_gap: 'shell-gap', header_height: 'shell-header-min-height', logo_height: 'shell-logo-height', page_title_size: 'shell-heading-size', sidebar_width: 'shell-sidebar-width', collapsed_sidebar_width: 'shell-rail-width', navigation_padding_block: 'navigation-padding-block', control_height: 'shell-desktop-target' };
    const variables = {};
    for (const [key, token] of Object.entries(mapping)) if (Number.isInteger(settings[key])) variables[`--aquestia-${token}`] = `${settings[key]}px`;
    variables['--aquestia-shell-brand-height'] = variables['--aquestia-shell-header-min-height'];
    variables['--aquestia-shell-heading-line-height'] = `${Math.ceil(settings.page_title_size * 1.2)}px`;
    for (const key of ['modal_width', 'slide_over_width']) variables[`--aquestia-${key.replaceAll('_', '-')}`] = `${{ small: 640, medium: 960, large: 1280 }[settings[key]]}px`;
    return { settings, overrides: Object.fromEntries(Object.entries(settings).filter(([key, value]) => value !== config.settings[key])), variables };
}

export function adminBrowserProfile(config) {
    let profile, themeListener, appearanceListener, sharedAppearanceListener;
    const applyVariables = variables => {
        for (const [key, value] of Object.entries(variables)) {
            if (/^--aquestia-[a-z-]+$/.test(key) && /^\d+px$/.test(value)) document.documentElement.style.setProperty(key, value);
        }
    };
    return {
        sidebar: null,
        init() {
            profile = browserProfile(config.user, 'global');
            let saved = profile.read() ?? {};
            const write = patch => { saved = { ...saved, ...patch }; profile.save(saved); };
            const appearance = personalAppearance(saved.appearance?.settings, config) ?? {};
            applyVariables(appearance.variables ?? {});
            this.sidebar = window.Alpine.store('sidebar');
            const expanded = typeof saved.sidebarExpanded === 'boolean' ? saved.sidebarExpanded : true;
            this.sidebar.isOpenDesktop = expanded;
            if (window.innerWidth >= 1024) this.sidebar.isOpen = expanded;
            this.sidebar.collapsedGroups = Array.isArray(saved.collapsedGroups) ? saved.collapsedGroups.filter(value => typeof value === 'string') : [];
            this.$watch('sidebar.isOpenDesktop', value => write({ sidebarExpanded: Boolean(value) }));
            this.$watch('sidebar.isOpen', value => { if (window.innerWidth >= 1024) write({ sidebarExpanded: Boolean(value) }); });
            this.$watch('sidebar.collapsedGroups', value => write({ collapsedGroups: clone(value) }));
            const theme = ['light', 'dark', 'system'].includes(saved.theme) ? saved.theme : 'light';
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
            themeListener = event => { if (['light', 'dark', 'system'].includes(event.detail)) write({ theme: event.detail }); };
            window.addEventListener('theme-changed', themeListener);
            appearanceListener = event => {
                const personal = personalAppearance(event.detail.settings, config);
                write({ appearance: personal ? { settings: personal.overrides } : null });
                applyVariables(personal?.variables ?? event.detail.variables ?? config.variables);
            };
            window.addEventListener('admin-personal-appearance-saved', appearanceListener);
            sharedAppearanceListener = event => {
                config.settings = event.detail.settings ?? config.settings;
                config.variables = event.detail.variables ?? config.variables;
                const personal = personalAppearance(saved.appearance?.settings, config);
                applyVariables(personal?.variables ?? config.variables);
            };
            window.addEventListener('admin-appearance-saved', sharedAppearanceListener);
            this.$nextTick(() => {
                const page = document.querySelector('[data-personal-appearance]');
                if (page && object(appearance.settings)) window.Livewire.find(page.closest('[wire\\:id]').getAttribute('wire:id')).$set('data', appearance.settings, false);
            });
        },
        destroy() {
            window.removeEventListener('theme-changed', themeListener);
            window.removeEventListener('admin-personal-appearance-saved', appearanceListener);
            window.removeEventListener('admin-appearance-saved', sharedAppearanceListener);
        },
    };
}
