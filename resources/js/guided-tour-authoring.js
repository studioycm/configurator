import { guidedTour } from './guided-tour.js';

const channel = 'ari-tour-author';
const tokenPattern = /^[a-f0-9-]{36}$/;
const clone = value => JSON.parse(JSON.stringify(value));

export function tourAuthor(config, wire, path, dependencies = {}) {
    const win = dependencies.window ?? window;
    let child = null, token = '', pending = null, listener;
    const read = () => wire.$get(path) ?? {};
    return {
        address: '', step: '', notice: '',
        get page() { return read().stage; },
        get steps() { return Object.entries(read().steps ?? {}).map(([id, step]) => ({ id, title: step.title || config.labels.newStep })); },
        init() {
            this.$watch('page', () => { this.address = ''; this.notice = ''; pending = null; child?.close(); child = null; });
            listener = event => {
                if (event.origin !== win.location.origin || event.source !== child || event.data?.channel !== channel || event.data.token !== token || !pending) return;
                if (pending.page !== this.page) return;
                if (event.data.type === 'ready') {
                    child.postMessage({ channel, token, type: 'draft', ...pending }, win.location.origin);
                }
                if (event.data.type === 'picked' && event.data.step === pending.step && config.targets[this.page]?.[event.data.target]) {
                    if (!read().steps?.[pending.step]) return;
                    wire.$set(`${path}.steps.${pending.step}.target`, event.data.target, false);
                    this.notice = config.labels.picked;
                    win.focus();
                }
            };
            win.addEventListener('message', listener);
        },
        open(mode) {
            this.notice = '';
            const draft = clone(read());
            const steps = Object.entries(draft.steps ?? {});
            const selected = this.step || steps[0]?.[0];
            if (!steps.length || (mode !== 'preview' && !draft.steps?.[selected])) { this.notice = config.labels.chooseStep; return; }
            const address = this.address.trim() || config.urls[draft.stage];
            if (!address) { this.notice = config.labels.addressRequired; return; }
            let url;
            try { url = new URL(address, win.location.origin); } catch { this.notice = config.labels.badAddress; return; }
            if (url.origin !== win.location.origin || !['https:', 'http:'].includes(url.protocol) || url.username || url.password) { this.notice = config.labels.badAddress; return; }
            token = (dependencies.uuid ?? (() => crypto.randomUUID()))();
            pending = { page: draft.stage, mode, step: selected, title: draft.title || config.labels.draft,
                steps: steps.map(([id, step]) => ({ ...step, authorId: id, page: draft.stage })) };
            url.hash = `tour-author=${token}`;
            child?.close();
            child = win.open(url.href, `ari-tour-author-${token}`);
            if (!child) this.notice = config.labels.popup;
            else this.notice = config.labels.opened;
        },
        destroy() { pending = null; child?.close(); win.removeEventListener('message', listener); },
    };
}

export function guidedTourAuthorPage(config, dependencies = {}) {
    const win = dependencies.window ?? window, doc = dependencies.document ?? document;
    const tourConfig = { ...config, tours: [], targets: Object.fromEntries(Object.entries(config.catalog).map(([key, item]) => [key, item.selector])) };
    const tour = guidedTour(tourConfig, dependencies);
    let token = '', draft = null, message, click, pointer, keyboard, navigation, geometry, focus;
    const editingEvents = ['beforeinput', 'input', 'change', 'paste', 'cut', 'drop', 'compositionstart', 'compositionupdate', 'compositionend', 'keypress', 'keyup', 'submit'];
    let blockEditing;
    const node = key => {
        const selector = config.catalog[key]?.selector;
        const target = selector ? doc.querySelector(selector) : null;
        return target?.isConnected && target.getClientRects().length && !target.closest('[hidden], [inert], [aria-hidden="true"]')
            && win.getComputedStyle(target).visibility !== 'hidden' ? target : null;
    };
    return {
        ...tour, authoring: false, picking: false, selected: '', rect: null,
        get targets() { return Object.entries(config.catalog).map(([key, item]) => ({ key, label: item.label, available: Boolean(node(key)) })); },
        init() {
            const match = win.location.hash.match(/^#tour-author=([a-f0-9-]+)$/);
            if (!match || !tokenPattern.test(match[1]) || !win.opener) return;
            token = match[1];
            this.authoring = true;
            tour.init.call(this);
            message = event => {
                if (event.origin !== win.location.origin || event.source !== win.opener || event.data?.channel !== channel || event.data.token !== token || event.data.type !== 'draft') return;
                const data = event.data;
                if (data.page !== config.page) { this.notice = config.labels.wrongPage; return; }
                draft = data;
                this.selected = data.steps?.find(step => step.authorId === data.step)?.target ?? '';
                if (data.mode === 'pick') { this.picking = true; this.mark(); }
                else if (data.mode === 'locate') this.locate();
                else this.preview();
            };
            click = event => {
                if (!this.picking || event.target.closest('[data-tour-author-tools]')) return;
                event.preventDefault(); event.stopImmediatePropagation();
                const candidates = this.targets.filter(item => item.available && node(item.key)?.contains(event.target));
                candidates.sort((a, b) => node(a.key).contains(node(b.key)) ? 1 : -1);
                if (candidates[0]) { this.selected = candidates[0].key; this.mark(); }
                else this.notice = config.labels.notRegistered;
            };
            keyboard = event => {
                if (event.key === 'Escape' && this.authoring) { event.preventDefault(); this.exit(); }
                else if (this.picking && !event.target?.closest('[data-tour-author-tools]') && event.key !== 'Tab') {
                    event.preventDefault(); event.stopImmediatePropagation();
                }
            };
            blockEditing = event => {
                if (this.picking && !event.target.closest('[data-tour-author-tools]')) { event.preventDefault(); event.stopImmediatePropagation(); }
            };
            focus = event => {
                if (this.picking && !event.target.closest('[data-tour-author-tools]')) {
                    doc.querySelector('[data-tour-author-tools] select')?.focus();
                }
            };
            pointer = event => {
                if (this.picking && !event.target.closest('[data-tour-author-tools]')) { event.preventDefault(); event.stopImmediatePropagation(); }
            };
            geometry = () => this.updateRect();
            navigation = () => this.destroy();
            doc.addEventListener('click', click, true);
            doc.addEventListener('pointerdown', pointer, true);
            doc.addEventListener('focusin', focus, true);
            editingEvents.forEach(type => doc.addEventListener(type, blockEditing, true));
            doc.addEventListener('keydown', keyboard, true);
            doc.addEventListener('livewire:navigating', navigation);
            win.addEventListener('scroll', geometry, true);
            win.addEventListener('resize', geometry);
            win.addEventListener('message', message);
            win.opener.postMessage({ channel, token, type: 'ready' }, win.location.origin);
        },
        mark() {
            this.notice = '';
            const target = node(this.selected);
            if (!target) { this.rect = null; this.notice = config.labels.unavailable; return; }
            target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
            this.updateRect();
        },
        updateRect() {
            const target = node(this.selected);
            if (!target) { this.rect = null; return; }
            const bounds = target.getBoundingClientRect();
            this.rect = { left: bounds.left, top: bounds.top, width: bounds.width, height: bounds.height };
        },
        useTarget() {
            if (!draft || !this.picking || !node(this.selected)) return;
            const step = draft.steps?.find(step => step.authorId === draft.step);
            if (step) step.target = this.selected;
            win.opener?.postMessage({ channel, token, type: 'picked', step: draft.step, target: this.selected }, win.location.origin);
            this.picking = false; this.rect = null; this.notice = config.labels.returnEditor;
        },
        locate() { this.close(); this.picking = false; this.mark(); },
        selectTarget() { this.close(); this.mark(); },
        preview() {
            if (!draft) return;
            this.picking = false; this.rect = null;
            const steps = (Array.isArray(draft.steps) ? draft.steps : []).filter(step => config.catalog[step.target]).slice(0, 30);
            tourConfig.tours = [{ key: 'author-preview', steps }];
            tour.start.call(this, 'author-preview');
        },
        pick() { this.close(); this.picking = true; this.mark(); },
        exit() { this.picking = false; this.rect = null; this.authoring = false; this.destroy(); },
        destroy() {
            tour.destroy.call(this);
            this.picking = false; this.rect = null;
            win.removeEventListener('message', message);
            doc.removeEventListener('click', click, true);
            doc.removeEventListener('pointerdown', pointer, true);
            doc.removeEventListener('focusin', focus, true);
            editingEvents.forEach(type => doc.removeEventListener(type, blockEditing, true));
            doc.removeEventListener('keydown', keyboard, true);
            doc.removeEventListener('livewire:navigating', navigation);
            win.removeEventListener('scroll', geometry, true);
            win.removeEventListener('resize', geometry);
        },
    };
}
