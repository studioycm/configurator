import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tableColumnWidths } from '../../resources/js/table-column-widths.js';
import { workspaceDialog } from '../../resources/js/workspace-dialogs.js';
import { workspaceSplit } from '../../resources/js/workspace-split.js';

class Element {
    constructor(className = '') {
        this.className = className;
        this.dataset = {};
        this.children = [];
        this.attributes = {};
        this.listeners = new Map();
        this.style = {
            setProperty(name, value) { this[name] = value; },
            getPropertyValue(name) { return this[name] ?? ''; },
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

test('dialog widths use bounded steps and survive a server morph without replacing the form', t => {
    const { observers } = environment(t);
    const modal = dialog();
    const field = new Element(); field.value = 'unsaved draft'; modal.root.append(field);
    modal.button('Wider').fire('click');
    assert.equal(modal.root.style.width, '1280px');
    modal.root.style.width = '';
    observers[0].callback([{ type: 'attributes', target: modal.root, attributeName: 'style' }]);
    assert.equal(modal.root.style.width, '1280px');
    assert.equal(field.value, 'unsaved draft');
    assert.equal(dialog().root.style.width, '1280px');
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
    assert.equal(modal.root.style.width, '1280px');
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
    assert.equal(modal.root.style.width, '1280px');
});

test('dialog steps remain isolated and clamp on mobile without overwriting the saved desktop width', t => {
    const { values, writes, window } = environment(t);
    const modal = dialog(); modal.button('Wider').fire('click');
    assert.equal(writes.length, 1);
    assert.equal(dialog().root.style.width, '1280px');
    assert.equal(dialog(2).root.style.width, '960px');
    assert.equal(dialog(1, 'filters').root.style.width, '960px');
    modal.button('Wider').fire('click'); assert.equal(modal.root.style.width, '1408px');
    assert.equal(modal.button('Wider').disabled, true);
    modal.button('Narrower').fire('click'); modal.button('Narrower').fire('click'); modal.button('Narrower').fire('click');
    assert.equal(modal.button('Narrower').disabled, true);
    assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '640');
    window.innerWidth = 390; window.fire('resize');
    assert.equal(modal.root.style.width, '390px');
    assert.equal(modal.button('Wider').disabled, true);
    assert.equal(modal.button('Narrower').disabled, true);
    assert.equal(values.get('aquestia:dialog:1:admin:columns:v1'), '640');
    window.innerWidth = 1440; window.fire('resize'); assert.equal(modal.root.style.width, '640px');
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

test('workspace split uses three bounded stops, persists per user and tab and preserves mobile preference', t => {
    const { values } = environment(t);
    const first = workspaceSplit({ user: 1, owner: 10, tab: 'attributes' });
    assert.equal(first.percentage, 50);
    first.keyDown({ key: 'ArrowRight', preventDefault() {} });
    assert.equal(first.step, 2);
    assert.equal(first.percentage, 67);
    assert.equal(first.columns, 'minmax(0, 4fr) 12px minmax(0, 2fr)');
    first.keyDown({ key: 'ArrowRight', preventDefault() {} });
    assert.equal(first.step, 2);
    assert.equal(values.get('aquestia:split:1:10:attributes:v1'), '2');
    const restored = workspaceSplit({ user: 1, owner: 10, tab: 'attributes' }); restored.restore();
    assert.equal(restored.step, 2);
    restored.resize(390); assert.equal(restored.stacked, true); assert.equal(restored.step, 2);
    restored.resize(1200); assert.equal(restored.stacked, false); assert.equal(restored.step, 2);
    assert.equal(workspaceSplit({ user: 2, owner: 10, tab: 'attributes' }).step, 1);
    const otherTab = workspaceSplit({ user: 1, owner: 10, tab: 'rules' }); otherTab.restore();
    assert.equal(otherTab.step, 1);
});

test('workspace split snaps pointer ratios and accepts keyboard reset with denied or invalid storage', t => {
    environment(t, { saved: [['aquestia:split:1:10:rules:v1', '99']] });
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'rules' }); split.restore();
    assert.equal(split.step, 1);
    assert.equal(split.snap(-2), 0); assert.equal(split.snap(0.5), 1); assert.equal(split.snap(3), 2);
    split.keyDown({ key: 'End', preventDefault() {} }); assert.equal(split.step, 2);
    split.keyDown({ key: 'Enter', preventDefault() {} }); assert.equal(split.step, 1);
    split.keyDown({ key: 'Home', preventDefault() {} }); assert.equal(split.percentage, 33);
    split.reset(); assert.equal(split.percentage, 50);
});

test('workspace pointer stops use the root width, preserve cancellation and reverse in RTL', t => {
    const { writes } = environment(t);
    const priorStyle = globalThis.getComputedStyle;
    let direction = 'ltr';
    globalThis.getComputedStyle = () => ({ direction });
    t.after(() => { globalThis.getComputedStyle = priorStyle; });
    const split = workspaceSplit({ user: 1, owner: 10, tab: 'rules' });
    split.root = { getBoundingClientRect: () => ({ left: 100, width: 1212 }) };
    split.$el = { getBoundingClientRect: () => ({ left: 700, width: 12 }) };
    split.resize(1212);
    const event = { button: 0, pointerId: 1, currentTarget: { setPointerCapture() {} }, preventDefault() {} };
    split.startDrag(event); split.moveDrag({ clientX: 906 });
    assert.equal(split.percentage, 67);
    split.endDrag(true); assert.equal(split.percentage, 50); assert.equal(writes.length, 0);
    split.startDrag(event); split.moveDrag({ clientX: 506 }); split.endDrag();
    assert.equal(split.percentage, 33); assert.equal(writes.length, 1);
    direction = 'rtl';
    split.startDrag(event); split.moveDrag({ clientX: 506 }); split.endDrag();
    assert.equal(split.percentage, 67);
    split.keyDown({ key: 'ArrowRight', preventDefault() {} }); assert.equal(split.percentage, 50);
});
