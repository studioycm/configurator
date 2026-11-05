import test from 'node:test';
import assert from 'node:assert/strict';
import { tourAuthor, guidedTourAuthorPage } from '../../resources/js/guided-tour-authoring.js';

const token = '12345678-1234-1234-1234-123456789abc';
const channel = 'ari-tour-author';
function events() {
    const listeners = new Map();
    return { listeners, addEventListener(k, cb) { if (!listeners.has(k)) listeners.set(k, new Set()); listeners.get(k).add(cb); }, removeEventListener(k, cb) { listeners.get(k)?.delete(cb); }, fire(k, event) { [...(listeners.get(k) ?? [])].forEach(cb => cb(event)); } };
}
function editor() {
    let form = { stage: 'images', title: 'Unsaved', steps: { a: { title: 'Find', target: 'images.find', description: 'Unsaved text' } } };
    const sent = [], sets = [];
    const child = { postMessage: msg => sent.push(msg), close() {} };
    const win = { ...events(), location: { origin: 'https://app.test' }, open: url => { sent.push(url); return child; }, focus() {} };
    const ui = tourAuthor({ urls: { images: 'https://app.test/admin/images' }, targets: { images: { 'images.find': 'Find', 'images.save': 'Save' } }, labels: { picked: 'Picked', badAddress: 'Bad', chooseStep: 'Step', addressRequired: 'Address', opened: 'Open' } }, { $get: () => form, $set: (...args) => sets.push(args) }, 'mountedActions.0.data', { window: win, uuid: () => token });
    ui.$watch = () => {}; ui.init();
    return { ui, win, child, sent, sets, form, replace: value => { form = value; } };
}

test('editor sends current unsaved steps only to its same-origin correlated workspace', () => {
    const h = editor(); h.ui.open('preview');
    const good = { origin: h.win.location.origin, source: h.child, data: { channel, token, type: 'ready' } };
    h.win.fire('message', { ...good, origin: 'https://other.test' });
    h.win.fire('message', { ...good, source: {} });
    h.win.fire('message', { ...good, data: { ...good.data, token: 'wrong' } });
    assert.equal(h.sent.length, 1);
    h.win.fire('message', good);
    assert.equal(h.sent[1].steps[0].description, 'Unsaved text');
    assert.equal(h.sent[1].steps[0].authorId, 'a');
    assert.equal(h.sets.length, 0); h.ui.destroy();
});

test('picks update only the intended existing step and reject stale contexts and unknown targets', () => {
    const h = editor(); h.ui.open('pick');
    const message = target => ({ origin: h.win.location.origin, source: h.child, data: { channel, token, type: 'picked', step: 'a', target } });
    h.win.fire('message', message('#arbitrary')); assert.equal(h.sets.length, 0);
    h.win.fire('message', message('images.save'));
    assert.deepEqual(h.sets, [['mountedActions.0.data.steps.a.target', 'images.save', false]]);
    h.form.stage = 'writing'; h.win.fire('message', message('images.find')); assert.equal(h.sets.length, 1);
    h.ui.destroy();
});

test('authoring refuses external addresses and requires an explicit record address when not known', () => {
    const h = editor(); h.ui.address = 'https://external.test/admin'; h.ui.open('preview'); assert.equal(h.ui.notice, 'Bad');
    h.ui.address = 'javascript:alert(1)'; h.ui.open('preview'); assert.equal(h.ui.notice, 'Bad');
    h.form.stage = 'groups-edit'; h.ui.address = ''; h.ui.open('preview'); assert.equal(h.ui.notice, 'Address');
    assert.equal(h.sent.length, 0); h.ui.destroy();
});

function workspace() {
    const sent = [], drivers = [];
    const field = { isConnected: true, parentElement: null, closest: () => null, getClientRects: () => [1], contains: element => element === field, scrollIntoView() {}, getBoundingClientRect: () => ({ left: 10, top: 20, width: 100, height: 40 }) };
    const section = { ...field, contains: element => element === section || element === field };
    const focused = [];
    const nodes = { field, section, '[data-tour-author-tools] select': { focus() { focused.push('toolbar'); } } };
    const doc = { ...events(), documentElement: {}, querySelector: selector => nodes[selector] ?? null };
    const opener = { postMessage: data => sent.push(data) };
    const win = { ...events(), location: { origin: 'https://app.test', hash: `#tour-author=${token}` }, opener, getComputedStyle: () => ({ visibility: 'visible' }), matchMedia: () => ({ matches: true }) };
    const ui = guidedTourAuthorPage({ page: 'images', catalog: { 'images.field': { label: 'Save', selector: 'field' }, 'images.section': { label: 'Settings', selector: 'section' } }, labels: { wrongPage: 'Wrong', unavailable: 'Unavailable' } }, { window: win, document: doc, observe: () => ({ observe() {}, disconnect() {} }), createDriver: config => { drivers.push(config); return { drive() {}, destroy() { config.onDestroyed(); } }; } });
    ui.init();
    const receive = (data, overrides = {}) => win.fire('message', { origin: win.location.origin, source: opener, data: { channel, token, type: 'draft', page: 'images', mode: 'pick', step: 'a', steps: [{ authorId: 'a', page: 'images', target: 'images.section', title: '<b>Draft</b>', description: 'Current text' }], ...data }, ...overrides });
    return { ui, doc, win, sent, drivers, field, section, receive, focused };
}

test('visual picker blocks underlying actions, prefers the innermost registered field and can select a whole section', () => {
    const h = workspace(); h.receive({}); let prevented = false, stopped = false;
    h.doc.fire('click', { target: h.field, preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } });
    assert.equal(prevented && stopped, true); assert.equal(h.ui.selected, 'images.field');
    h.ui.useTarget(); assert.equal(h.sent.at(-1).target, 'images.field'); assert.equal(h.ui.picking, false);
    h.ui.pick(); h.ui.selected = 'images.section'; h.ui.useTarget(); assert.equal(h.sent.at(-1).target, 'images.section');
    h.ui.destroy(); assert.equal([...h.doc.listeners.values()].every(set => set.size === 0), true);
});

test('workspace ignores mismatched senders and pages and previews only registered escaped draft targets', () => {
    const h = workspace(); h.receive({}, { origin: 'https://other.test' }); assert.equal(h.ui.picking, false);
    h.receive({ page: 'writing' }); assert.equal(h.ui.notice, 'Wrong');
    h.receive({ mode: 'preview' });
    assert.equal(h.drivers.length, 1); assert.equal(h.drivers[0].steps[0].popover.title, '&lt;b&gt;Draft&lt;/b&gt;');
    assert.equal(h.drivers[0].disableActiveInteraction, true);
    h.doc.fire('keydown', { key: 'Escape', preventDefault() {}, stopPropagation() {} });
    assert.equal(h.ui.authoring, false); assert.equal(h.ui.active, false); h.ui.destroy();
});

test('keyboard highlight choice keeps the picker active and sends the chosen whole section', () => {
    const h = workspace(); h.receive({}); h.ui.selected = 'images.section'; h.ui.selectTarget();
    assert.equal(h.ui.picking, true); h.ui.useTarget();
    assert.equal(h.sent.at(-1).target, 'images.section'); h.ui.destroy();
});

test('picking suppresses native pointer and keyboard activation while toolbar controls remain usable', () => {
    const h = workspace(); h.receive({}); let blocked = 0;
    const event = { target: h.field, key: 'Enter', preventDefault() { blocked++; }, stopImmediatePropagation() {} };
    h.doc.fire('pointerdown', event); h.doc.fire('keydown', event); assert.equal(blocked, 2);
    h.doc.fire('pointerdown', { ...event, target: { closest: () => ({}) } }); assert.equal(blocked, 2);
    h.ui.destroy();
});

test('Tab-focused workspace editing and autosave events are blocked only during picking', () => {
    const h = workspace(); h.receive({});
    h.doc.fire('focusin', { target: h.field });
    assert.deepEqual(h.focused, ['toolbar']);
    let value = 'Saved source', autosaves = 0;
    for (const type of ['keydown', 'keypress', 'keyup', 'beforeinput', 'paste', 'cut', 'drop', 'compositionstart', 'compositionupdate', 'compositionend', 'input', 'change', 'submit']) {
        let prevented = false, stopped = false;
        h.doc.fire(type, { target: h.field, key: 'x', preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } });
        if (!prevented) value = 'Edited source';
        if (!stopped && ['input', 'change'].includes(type)) autosaves++;
        assert.equal(prevented, true, `${type} must not edit workspace`);
        assert.equal(stopped, true, `${type} must not reach workspace handlers`);
    }
    assert.equal(value, 'Saved source'); assert.equal(autosaves, 0);
    let toolbarBlocked = false;
    h.doc.fire('beforeinput', { target: { closest: () => ({}) }, preventDefault() { toolbarBlocked = true; }, stopImmediatePropagation() {} });
    assert.equal(toolbarBlocked, false);
    h.ui.locate(); let normalBlocked = false;
    h.doc.fire('beforeinput', { target: h.field, preventDefault() { normalBlocked = true; }, stopImmediatePropagation() {} });
    assert.equal(normalBlocked, false); h.ui.destroy();
});
