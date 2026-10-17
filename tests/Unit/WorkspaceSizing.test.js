import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tableColumnWidths } from '../../resources/js/table-column-widths.js';
import { workspaceDialog } from '../../resources/js/workspace-dialogs.js';
import { workspaceSplit } from '../../resources/js/workspace-split.js';

class Element {
    constructor(className = '', tagName = 'DIV') {
        this.tagName = tagName;
        this.className = className;
        this.dataset = {};
        this.children = [];
        this.attributes = {};
        this.listeners = new Map();
        this.style = {
            setProperty(name, value) { this[name] = value; },
            getPropertyValue(name) { return this[name] ?? ''; },
            removeProperty(name) { delete this[name.startsWith('--') ? name : name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())]; },
        };
        this.classList = { toggle() {} };
    }
    append(child) { child.remove(); child.parent = this; this.children.push(child); }
    remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); this.parent = null; }
    get isConnected() { return this.connected || Boolean(this.parent?.isConnected); }
    setAttribute(name, value) { this.attributes[name] = value; }
    addEventListener(name, callback) { const callbacks = this.listeners.get(name) ?? new Set(); callbacks.add(callback); this.listeners.set(name, callbacks); }
    removeEventListener(name, callback) { this.listeners.get(name)?.delete(callback); }
    fire(name, details = {}) {
        const event = { button: 0, pointerId: 1, clientX: 100, preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; }, ...details };
        for (const callback of this.listeners.get(name) ?? []) callback(event);
        return event;
    }
    setPointerCapture() {}
    focus() {}
    getBoundingClientRect() { return { width: parseFloat(this.style.width) || 120 }; }
    closest(selector) {
        if (selector === '[data-width-table]' && this.dataset.widthTable) return this;
        return this.parent?.closest(selector) ?? null;
    }
    matches(selector) {
        if (selector === '[data-width-column]') return Boolean(this.dataset.widthColumn);
        if (selector === 'th[data-width-column]') return this.tagName === 'TH' && Boolean(this.dataset.widthColumn);
        if (selector === 'th' || selector === 'thead th') return this.tagName === 'TH';
        if (selector === '[data-maximize]') return Object.hasOwn(this.dataset, 'maximize');
        if (selector === '[data-width-step]') return Object.hasOwn(this.dataset, 'widthStep');
        return selector.startsWith('.') && this.className.split(' ').includes(selector.slice(1));
    }
    querySelectorAll(selector) { return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
}

function environment(t, { saved = [], denied = false } = {}) {
    const values = new Map(saved);
    const writes = [];
    const observers = [];
    const sizeObservers = [];
    const frames = new Map();
    const window = new Element();
    window.innerWidth = 1440;
    const replacements = {
        window,
        getComputedStyle: element => ({ direction: element.direction ?? 'ltr' }),
        document: { createElement: () => new Element() },
        localStorage: {
            getItem(key) { if (denied) throw new Error('Storage denied'); return values.get(key) ?? null; },
            setItem(key, value) { if (denied) throw new Error('Storage denied'); writes.push(key); values.set(key, value); },
            removeItem(key) { if (denied) throw new Error('Storage denied'); values.delete(key); },
        },
        MutationObserver: class {
            constructor(callback) { this.callback = callback; observers.push(this); }
            observe() {}
            disconnect() { this.disconnected = true; }
        },
        ResizeObserver: class {
            constructor(callback) { this.callback = callback; sizeObservers.push(this); }
            observe() {}
            disconnect() { this.disconnected = true; }
        },
        requestAnimationFrame(callback) { frames.set(1, () => { frames.delete(1); callback(); }); return 1; },
        cancelAnimationFrame(id) { frames.delete(id); },
    };
    const previous = Object.fromEntries(Object.keys(replacements).map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(replacements)) Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    t.after(() => { for (const [key, descriptor] of Object.entries(previous)) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]; } });
    return { values, writes, window, observers, sizeObservers, frames };
}

function columns(user = 1, table = 'options') {
    const root = new Element(); root.connected = true; root.dataset.widthTable = table;
    const header = new Element('', 'TH'); header.dataset.widthColumn = 'code'; root.append(header);
    const controller = tableColumnWidths({ user, table, labels: { resize_column: 'Width of :column. Drag or use arrow keys; double-click or Delete resets; Escape cancels.' } }); controller.$el = root; controller.$nextTick = callback => callback(); controller.init();
    return { controller, root, header, handle: header.querySelector('.catalog-column-resize') };
}

function dialog(user = 1, purpose = 'columns') {
    const root = new Element(); root.connected = true;
    root.append(new Element('fi-modal-header'));
    const controller = workspaceDialog({ user, purpose, width: 960, slideOver: true, labels: { width: 'Dialog width', narrower: 'Narrower', wider: 'Wider', reset: 'Reset width' } }); controller.$el = root; controller.$nextTick = callback => callback(); controller.init();
    return { controller, root, handle: root.querySelector('.catalog-dialog-resize-handle'), button: label => root.querySelector('.catalog-dialog-width-controls').children.find(child => child.textContent === label) };
}

test('dialog widths use bounded steps and survive a server morph without replacing the form', t => {
    const { observers } = environment(t);
    const modal = dialog();
    const field = new Element(); field.value = 'unsaved draft'; modal.root.append(field);
    modal.button('Wider').fire('click');
    assert.equal(modal.root.style.width, '1120px');
    modal.root.style.width = '';
    observers[0].callback([{ type: 'attributes', target: modal.root, attributeName: 'style' }]);
    assert.equal(modal.root.style.width, '1120px');
    assert.equal(field.value, 'unsaved draft');
    assert.equal(dialog().root.style.width, '1120px');
    modal.button('Narrower').fire('click');
    assert.equal(modal.root.style.width, '960px');
    modal.button('Reset width').fire('click');
    assert.equal(dialog().root.style.width, '960px');
});

test('a replaced dialog header gets one width toolbar and retains the current width', t => {
    const { observers } = environment(t);
    const modal = dialog(); modal.button('Wider').fire('click');
    modal.root.querySelector('.fi-modal-header').remove();
    modal.root.append(new Element('fi-modal-header'));
    observers[0].callback([{ type: 'childList', target: modal.root }]);
    observers[0].callback([{ type: 'childList', target: modal.root }]);
    assert.equal(modal.root.querySelectorAll('.catalog-dialog-width-controls').length, 1);
    assert.equal(modal.root.style.width, '1120px');
    modal.button('Narrower').fire('click');
    assert.equal(modal.root.style.width, '960px');
});

test('invalid saved dialog widths fall back to the default while valid widths restore', t => {
    environment(t, { saved: [
        ['aquestia:dialog:1:admin:columns:v1', '{broken'],
        ['aquestia:dialog:2:admin:columns:v1', '"1280"'],
        ['aquestia:dialog:3:admin:columns:v1', '20'],
        ['aquestia:dialog:4:admin:columns:v1', '5000'],
        ['aquestia:dialog:5:admin:columns:v1', '1280'],
    ] });
    for (const user of [1, 2, 3, 4]) assert.equal(dialog(user).root.style.width, '960px');
    assert.equal(dialog(5).root.style.width, '1280px');
});

test('column drag persists only on release and cancellation restores the previous width', t => {
    const { writes, values } = environment(t);
    const { header, handle } = columns();
    const down = handle.fire('pointerdown');
    handle.fire('pointermove', { clientX: 160 });
    assert.equal(header.style.width, '180px');
    assert.equal(writes.length, 0);
    assert.equal(down.stopped, true);
    handle.fire('pointercancel');
    assert.equal(header.style.width, undefined);
    handle.fire('pointerdown'); handle.fire('pointermove', { clientX: 180 }); handle.fire('pointerup');
    assert.equal(values.get('aquestia:columns:1:admin:options:v1'), '{"code":200}');
    handle.fire('pointerdown'); handle.fire('pointermove', { clientX: 900 }); handle.fire('lostpointercapture');
    assert.equal(header.style.width, '200px');
    assert.equal(writes.length, 1);
});

test('column preferences are isolated by user and table and reset affects only its table', t => {
    const { values, window } = environment(t);
    const first = columns(); first.handle.fire('keydown', { key: 'End' });
    assert.equal(columns().header.style.width, '320px');
    const anotherUser = columns(2); const anotherTable = columns(1, 'attributes');
    assert.equal(anotherUser.header.style.width, undefined);
    assert.equal(anotherTable.header.style.width, undefined);
    anotherTable.handle.fire('keydown', { key: 'Home' });
    window.fire('table-widths-reset', { detail: { table: 'options' } });
    assert.equal(first.header.style.width, undefined);
    assert.equal(anotherTable.header.style.width, '96px');
    assert.equal(values.get('aquestia:columns:1:admin:attributes:v1'), '{"code":96}');
});

test('malformed saved JSON leaves usable keyboard widths', t => {
    environment(t, { saved: [['aquestia:columns:1:admin:options:v1', '{broken']] });
    const first = columns(); assert.equal(first.header.style.width, undefined);
    assert.doesNotThrow(() => first.handle.fire('keydown', { key: 'ArrowRight' }));
    assert.equal(first.header.style.width, '136px');
});

test('invalid saved column widths are ignored while valid widths still restore', t => {
    environment(t, { saved: [
        ['aquestia:columns:1:admin:options:v1', '[320]'],
        ['aquestia:columns:2:admin:options:v1', '{"code":"320"}'],
        ['aquestia:columns:3:admin:options:v1', '{"code":20}'],
        ['aquestia:columns:4:admin:options:v1', '{"code":700}'],
        ['aquestia:columns:5:admin:options:v1', '{"code":320}'],
    ] });
    for (const user of [1, 2, 3, 4]) assert.equal(columns(user).header.style.width, undefined);
    assert.equal(columns(5).header.style.width, '320px');
});

test('denied storage does not break either resize controller', t => {
    environment(t, { denied: true });
    const table = columns(); const modal = dialog();
    assert.doesNotThrow(() => table.handle.fire('keydown', { key: 'End' }));
    assert.doesNotThrow(() => modal.button('Wider').fire('click'));
    assert.equal(table.header.style.width, '320px');
    assert.equal(modal.root.style.width, '1120px');
});

test('dialog steps remain isolated and clamp on mobile without overwriting the saved desktop width', t => {
    const { values, writes, window } = environment(t);
    const modal = dialog(); modal.button('Wider').fire('click');
    assert.equal(writes.length, 1);
    assert.equal(dialog().root.style.width, '1120px');
    assert.equal(dialog(2).root.style.width, '960px');
    assert.equal(dialog(1, 'filters').root.style.width, '960px');
    modal.button('Wider').fire('click'); assert.equal(modal.root.style.width, '1280px');
    modal.button('Wider').fire('click'); assert.equal(modal.root.style.width, '1408px');
    assert.equal(modal.button('Wider').disabled, true);
    const narrower = [];
    while (!modal.button('Narrower').disabled) {
        modal.button('Narrower').fire('click');
        narrower.push(modal.root.style.width);
    }
    assert.deepEqual(narrower, ['1280px', '1120px', '960px', '880px', '800px', '720px', '640px', '560px', '480px']);
    assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '480');
    window.innerWidth = 390; window.fire('resize');
    assert.equal(modal.root.style.width, '390px');
    assert.equal(modal.button('Wider').disabled, true);
    assert.equal(modal.button('Narrower').disabled, true);
    assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '480');
    window.innerWidth = 1440; window.fire('resize'); assert.equal(modal.root.style.width, '480px');
});

test('saved dialog widths between presets restore exactly without a storage write', t => {
    const { writes } = environment(t, { saved: [['aquestia:dialog:1:admin:columns:v1', '900']] });
    const modal = dialog();
    assert.equal(modal.root.style.width, '900px');
    assert.equal(writes.length, 0);
    modal.button('Wider').fire('click');
    assert.equal(modal.root.style.width, '960px');
    modal.button('Narrower').fire('click');
    assert.equal(modal.root.style.width, '880px');
});

test('destroy removes observers, scheduled column hydration, dialog controls and global listeners', t => {
    const { window, observers, sizeObservers, frames } = environment(t);
    const table = columns(); const modal = dialog();
    observers[0].callback(); assert.equal(frames.size, 1);
    table.controller.destroy(); modal.controller.destroy();
    assert.equal(frames.size, 0);
    assert.equal(observers.every(observer => observer.disconnected), true);
    assert.equal(sizeObservers.every(observer => observer.disconnected), true);
    assert.equal(window.listeners.get('table-widths-reset').size, 0);
    assert.equal(window.listeners.get('resize').size, 0);
    assert.equal(modal.root.querySelector('.catalog-dialog-width-controls'), null);
    assert.equal(modal.root.querySelector('.catalog-dialog-resize-handle'), null);
});

test('workspace split offers finer stops and persists ratios per user and tab', t => {
    const { values } = environment(t);
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'attributes' });
    split.resize(1600);
    assert.equal(split.percentage, 50);
    const percentages = [];
    split.keyDown({ key: 'Home', preventDefault() {} });
    for (let i = 0; i < 9; i++) {
        percentages.push(split.percentage);
        split.keyDown({ key: 'ArrowRight', preventDefault() {} });
    }
    assert.deepEqual(percentages, [30, 35, 40, 45, 50, 55, 60, 65, 70]);
    assert.equal(values.get('aquestia:split:1:10:attributes:v2'), '0.7');
    const restored = workspaceSplit({ user: 1, owner: 10, tab: 'attributes' }); restored.restore();
    assert.equal(restored.percentage, 70);
    assert.equal(workspaceSplit({ user: 2, owner: 10, tab: 'attributes' }).percentage, 50);
    const otherTab = workspaceSplit({ user: 1, owner: 10, tab: 'rules' }); otherTab.restore();
    assert.equal(otherTab.percentage, 50);
});

test('legacy divider preferences retain their exact ratio and resizing does not overwrite them', t => {
    const { values, writes } = environment(t, { saved: [['aquestia:split:1:10:rules:v1', '2']] });
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'rules' }); split.restore();
    split.resize(1600); assert.equal(split.ratio, 2 / 3);
    split.resize(980); assert.equal(split.ratio, 548 / 968);
    assert.deepEqual(split.availableStops, [0.45, 0.5, 0.55]);
    split.resize(390); assert.equal(split.stacked, true);
    split.resize(1600); assert.equal(split.ratio, 2 / 3);
    assert.equal(writes.length, 0);
    assert.equal(values.get('aquestia:split:1:10:rules:v1'), '2');
    split.keyDown({ key: 'ArrowLeft', preventDefault() {} });
    assert.equal(split.percentage, 65);
    assert.equal(values.get('aquestia:split:1:10:rules:v2'), '0.65');
});

test('workspace split validates saved ratios and keeps each pane at least 420 pixels', t => {
    environment(t, { saved: [
        ['aquestia:split:1:10:rules:v1', '99'],
        ['aquestia:split:1:10:rules:v2', '"0.7"'],
    ] });
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'rules' }); split.restore();
    assert.equal(split.percentage, 50);
    split.resize(980);
    split.keyDown({ key: 'End', preventDefault() {} }); assert.equal(split.percentage, 55);
    split.keyDown({ key: 'Home', preventDefault() {} }); assert.equal(split.percentage, 45);
    split.reset(); assert.equal(split.percentage, 50);
});

test('workspace pointer stops use the root width, preserve cancellation and reverse in RTL', t => {
    const { writes } = environment(t);
    let direction = 'ltr';
    globalThis.getComputedStyle = () => ({ direction });
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'rules' });
    split.root = { getBoundingClientRect: () => ({ left: 100, width: 1612 }) };
    split.resize(1612);
    const event = { button: 0, pointerId: 1, currentTarget: { setPointerCapture() {} }, preventDefault() {} };
    split.startDrag(event); split.moveDrag({ clientX: 1226 });
    assert.equal(split.percentage, 70);
    assert.equal(writes.length, 0);
    split.endDrag(true); assert.equal(split.percentage, 50); assert.equal(writes.length, 0);
    split.startDrag(event); split.moveDrag({ clientX: 586 }); split.endDrag();
    assert.equal(split.percentage, 30); assert.equal(writes.length, 1);
    direction = 'rtl';
    split.startDrag(event); split.moveDrag({ clientX: 586 }); split.endDrag();
    assert.equal(split.percentage, 70);
    split.keyDown({ key: 'ArrowRight', preventDefault() {} }); assert.equal(split.percentage, 65);
    split.keyDown({ key: 'Enter', preventDefault() {} }); assert.equal(split.percentage, 50);
});

test('column drag follows the inline edge in RTL and Escape restores the saved width', t => {
    const { values, writes } = environment(t);
    const { root, header, handle } = columns();
    root.direction = 'rtl';
    handle.fire('pointerdown');
    handle.fire('pointermove', { clientX: 60 });
    assert.equal(header.style.width, '160px');
    handle.fire('pointerup');
    assert.equal(values.get('aquestia:columns:1:admin:options:v1'), '{"code":160}');

    handle.fire('pointerdown');
    handle.fire('pointermove', { clientX: 20 });
    handle.fire('keydown', { key: 'Escape' });
    assert.equal(header.style.width, '160px');
    assert.equal(writes.length, 1);
    handle.fire('keydown', { key: 'ArrowRight' });
    assert.equal(header.style.width, '144px');
});

test('double-click resets one column and mobile ignores desktop sizing without losing it', t => {
    const { values, window } = environment(t);
    const first = columns();
    first.handle.fire('keydown', { key: 'End' });
    window.innerWidth = 390; window.fire('resize');
    assert.equal(first.header.style.width, undefined);
    assert.equal(values.get('aquestia:columns:1:admin:options:v1'), '{"code":320}');
    window.innerWidth = 1440; window.fire('resize');
    assert.equal(first.header.style.width, '320px');
    first.handle.fire('dblclick');
    assert.equal(first.header.style.width, undefined);
    assert.equal(columns().header.style.width, undefined);
});

test('resizing reserves the other visible columns and rehydrates replaced cells once', t => {
    const { observers, frames } = environment(t);
    const { root, header, handle } = columns();
    const other = new Element('', 'TH'); other.dataset.widthColumn = 'label'; root.append(other);
    const cell = new Element('', 'TD'); cell.dataset.widthColumn = 'code'; root.append(cell);
    observers[0].callback(); frames.get(1)();

    handle.fire('keydown', { key: 'End' });
    assert.equal(other.style.width, '120px');
    assert.equal(cell.style.width, '320px');
    assert.equal(root.style.getPropertyValue('--catalog-table-width'), '440px');
    cell.remove();
    const replacement = new Element('', 'TD'); replacement.dataset.widthColumn = 'code'; root.append(replacement);
    observers[0].callback(); frames.get(1)();
    assert.equal(replacement.style.width, '320px');
    assert.equal(header.querySelectorAll('.catalog-column-resize').length, 1);
    header.querySelector('.catalog-column-resize').fire('keydown', { key: 'Delete' });
    assert.equal(other.style.width, undefined);
    assert.equal(root.style.getPropertyValue('--catalog-table-width'), '');
});


test('widths for removed or hidden columns leave the visible table in its natural layout', t => {
    environment(t, { saved: [['aquestia:columns:1:admin:options:v1', '{"removed":320}']] });
    const { root, header } = columns();
    assert.equal(header.style.width, undefined);
    assert.equal(root.style.getPropertyValue('--catalog-table-width'), '');
});


test('a table hidden during resize restores replaced headers when its tab becomes visible', t => {
    const { window, sizeObservers, observers, frames } = environment(t);
    const { root, header, handle } = columns();
    handle.fire('keydown', { key: 'End' });
    let visible = false;
    root.getBoundingClientRect = () => ({ width: visible ? 600 : 0 });
    header.getBoundingClientRect = () => ({ width: visible ? 120 : 0 });
    window.fire('resize');
    header.remove();
    const replacement = new Element('', 'TH'); replacement.dataset.widthColumn = 'code'; root.append(replacement);
    replacement.getBoundingClientRect = () => ({ width: visible ? 120 : 0 });
    observers[0].callback(); frames.get(1)();

    visible = true;
    sizeObservers[0]?.callback([{ contentRect: { width: 600 } }]);
    if (frames.size) frames.get(1)();
    assert.equal(replacement.style.width, '320px');
    sizeObservers[0].callback([{ contentRect: { width: 600 } }]);
    assert.equal(frames.size, 0);
    const restoredHandle = replacement.querySelector('.catalog-column-resize');
    restoredHandle.fire('keydown', { key: 'ArrowRight' });
    assert.equal(replacement.style.width, '336px');
});


test('page-scoped dialog reset suppresses legacy widths and leaves other pages independent', t => {
    const { window, values } = environment(t, { saved: [['aquestia:dialog:1:admin:columns:v1', '1280']] });
    window.location = { pathname: '/admin/options' };
    const modal = dialog(); assert.equal(modal.root.style.width, '1280px');
    modal.button('Reset width').fire('click');
    assert.equal(dialog().root.style.width, '960px');
    window.location.pathname = '/admin/values';
    assert.equal(dialog().root.style.width, '1280px');
    assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '1280');
});
