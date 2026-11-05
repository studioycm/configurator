import test from 'node:test';
import assert from 'node:assert/strict';
import { guidedTour } from '../../resources/js/guided-tour.js';

function harness() {
    const events = new Map(), windowEvents = new Map(), calls = [];
    const element = name => ({ name, isConnected: true, hidden: false, getClientRects() { return this.hidden ? [] : [1]; }, closest() { return this.hidden; }, focus() { calls.push(['focus', name]); } });
    const nodes = new Map([['[data-tour="images.find"]', element('find')], ['[data-tour="images.records"]', element('records')]]);
    const document = { querySelector: selector => nodes.get(selector) ?? null, documentElement: {}, addEventListener: (k, fn) => events.set(k, fn), removeEventListener: k => events.delete(k) };
    const window = { matchMedia: () => ({ matches: true }), addEventListener: (k, fn) => windowEvents.set(k, fn), removeEventListener: k => windowEvents.delete(k) };
    let options, mutation;
    const launcher = element('launcher');
    const config = { page: 'images', tours: [{ key: 'intro', steps: [
        { key: 'find', page: 'images', target: 'images.find', title: '<img src=x onerror=alert(1)>', description: 'A & B', placement: 'bottom' },
        { key: 'records', page: 'images', target: 'images.records', title: 'Records', description: 'Inspect records', placement: 'top' },
    ] }], targets: { 'images.find': '[data-tour="images.find"]', 'images.records': '[data-tour="images.records"]' }, labels: { unavailable: 'Unavailable', changed: 'Changed', next: 'Next', previous: 'Previous', done: 'Done' } };
    const ui = guidedTour(config, { document, window, createDriver: value => { options = value; return { drive: n => calls.push(['drive', n]), moveTo: n => calls.push(['move', n]), destroy() { calls.push(['destroy']); options.onDestroyed(); } }; }, observe: fn => { mutation = fn; return { observe() {}, disconnect() { calls.push(['disconnect']); } }; } });
    ui.init();
    return { ui, nodes, events, windowEvents, calls, launcher, options: () => options, mutate: () => mutation() };
}

test('Next Previous Done and restart stay local and restore focus', () => {
    const h = harness(); h.ui.start('intro', h.launcher);
    h.options().onNextClick(); h.options().onPrevClick(); h.options().onNextClick(); h.options().onNextClick();
    assert.equal(h.ui.active, false);
    assert.deepEqual(h.calls.filter(c => ['drive', 'move'].includes(c[0])), [['drive', 0], ['move', 1], ['move', 0], ['move', 1]]);
    assert.deepEqual(h.calls.at(-1), ['focus', 'launcher']);
    h.ui.start('intro', h.launcher); assert.equal(h.ui.active, true); h.ui.destroy();
    assert.equal(h.events.size, 0); assert.equal(h.windowEvents.size, 0);
});

test('popover content is text escaped and reduced motion is respected', () => {
    const h = harness(); h.ui.start('intro', h.launcher); const o = h.options();
    assert.equal(o.steps[0].popover.title, '&lt;img src=x onerror=alert(1)&gt;');
    assert.equal(o.steps[0].popover.description, 'A &amp; B');
    assert.equal(o.animate, false); assert.equal(o.disableActiveInteraction, true);
    assert.equal(o.allowKeyboardControl, false); h.ui.destroy();
});

test('missing targets are skipped and an entirely unavailable tour explains why', () => {
    const h = harness(); h.nodes.delete('[data-tour="images.find"]'); h.ui.start('intro', h.launcher);
    assert.deepEqual(h.calls.find(c => c[0] === 'drive'), ['drive', 1]); h.ui.close();
    h.nodes.clear(); h.ui.start('intro', h.launcher);
    assert.equal(h.ui.active, false); assert.equal(h.ui.notice, 'Unavailable'); h.ui.destroy();
});

test('a morphed or hidden active target ends the tour instead of leaving an orphaned overlay', () => {
    for (const change of ['replace', 'hide']) {
        const h = harness(); h.ui.start('intro', h.launcher);
        if (change === 'replace') h.nodes.delete('[data-tour="images.find"]');
        else h.nodes.get('[data-tour="images.find"]').hidden = true;
        h.mutate(); assert.equal(h.ui.active, false); assert.equal(h.ui.notice, 'Changed'); h.ui.destroy();
    }
});

test('Escape navigation and teardown remove the active overlay without starting an operation', () => {
    for (const event of ['escape', 'navigation', 'pagehide']) {
        const h = harness(); h.ui.start('intro', h.launcher);
        if (event === 'escape') h.events.get('keydown')({ key: 'Escape', preventDefault() {}, stopPropagation() {} });
        if (event === 'navigation') h.events.get('livewire:navigating')();
        if (event === 'pagehide') h.windowEvents.get('pagehide')();
        assert.equal(h.ui.active, false); h.ui.destroy();
    }
});
