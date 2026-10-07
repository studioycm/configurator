import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tableColumnWidths } from '../../resources/js/table-column-widths.js';
import { workspaceDialog } from '../../resources/js/workspace-dialogs.js';

class Element {
    constructor(className = '') {
        this.className = className;
        this.dataset = {};
        this.children = [];
        this.attributes = {};
        this.listeners = new Map();
        this.style = {
            setProperty(name, value) { this[name] = value; },
            removeProperty(name) { delete this[name.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())]; },
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
    getBoundingClientRect() { return { width: parseFloat(this.style.width) || 120 }; }
    closest(selector) {
        if (selector === '[data-width-table]' && this.dataset.widthTable) return this;
        return this.parent?.closest(selector) ?? null;
    }
    matches(selector) {
        if (selector === '[data-width-column]' || selector === 'th[data-width-column]') return Boolean(this.dataset.widthColumn);
        if (selector === '[data-maximize]') return Object.hasOwn(this.dataset, 'maximize');
        return selector.startsWith('.') && this.className.split(' ').includes(selector.slice(1));
    }
    querySelectorAll(selector) { return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
}

function environment(t, { saved = [], denied = false } = {}) {
    const values = new Map(saved);
    const writes = [];
    const observers = [];
    const frames = new Map();
    const window = new Element();
    window.innerWidth = 1440;
    const replacements = {
        window,
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
        requestAnimationFrame(callback) { frames.set(1, callback); return 1; },
        cancelAnimationFrame(id) { frames.delete(id); },
    };
    const previous = Object.fromEntries(Object.keys(replacements).map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
    for (const [key, value] of Object.entries(replacements)) Object.defineProperty(globalThis, key, { configurable: true, writable: true, value });
    t.after(() => { for (const [key, descriptor] of Object.entries(previous)) { if (descriptor) Object.defineProperty(globalThis, key, descriptor); else delete globalThis[key]; } });
    return { values, writes, window, observers, frames };
}

function columns(user = 1, table = 'options') {
    const root = new Element(); root.connected = true; root.dataset.widthTable = table;
    const header = new Element(); header.dataset.widthColumn = 'code'; root.append(header);
    const controller = tableColumnWidths({ user, table }); controller.$el = root; controller.$nextTick = callback => callback(); controller.init();
    return { controller, root, header, handle: header.querySelector('.catalog-column-resize') };
}

function dialog(user = 1, purpose = 'columns') {
    const root = new Element(); root.connected = true;
    root.append(new Element('fi-modal-header'));
    const controller = workspaceDialog({ user, purpose, width: 960, slideOver: true }); controller.$el = root; controller.$nextTick = callback => callback(); controller.init();
    return { controller, root, handle: root.querySelector('.catalog-dialog-resize-handle'), button: label => root.querySelector('.catalog-dialog-width-controls').children.find(child => child.textContent === label) };
}

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
    assert.doesNotThrow(() => modal.button('Large').fire('click'));
    assert.equal(table.header.style.width, '320px');
    assert.equal(modal.root.style.width, '1280px');
});

test('dialog presets restore after maximize, cancel without saving and remain isolated', t => {
    const { values, writes, window } = environment(t);
    const modal = dialog(); modal.button('Large').fire('click');
    modal.button('Maximize').fire('click'); assert.equal(modal.root.style.width, '1408px');
    modal.button('Restore').fire('click'); assert.equal(modal.root.style.width, '1280px');
    modal.handle.fire('pointerdown'); modal.handle.fire('pointermove', { clientX: 50 });
    assert.equal(writes.length, 1);
    modal.handle.fire('pointercancel'); assert.equal(modal.root.style.width, '1280px');
    assert.equal(dialog().root.style.width, '1280px');
    assert.equal(dialog(2).root.style.width, '960px');
    assert.equal(dialog(1, 'filters').root.style.width, '960px');
    modal.handle.fire('keydown', { key: 'Home' }); assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '640');
    window.innerWidth = 390; window.fire('resize');
    assert.equal(modal.root.style.width, '390px'); assert.equal(modal.handle.hidden, true);
});

test('destroy removes observers, scheduled column hydration, dialog controls and global listeners', t => {
    const { window, observers, frames } = environment(t);
    const table = columns(); const modal = dialog();
    observers[0].callback(); assert.equal(frames.size, 1);
    table.controller.destroy(); modal.controller.destroy();
    assert.equal(frames.size, 0);
    assert.equal(observers.every(observer => observer.disconnected), true);
    assert.equal(window.listeners.get('table-widths-reset').size, 0);
    assert.equal(window.listeners.get('resize').size, 0);
    assert.equal(modal.root.querySelector('.catalog-dialog-width-controls'), null);
    assert.equal(modal.root.querySelector('.catalog-dialog-resize-handle'), null);
});
