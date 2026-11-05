import test from 'node:test';
import assert from 'node:assert/strict';
import { tourAnchorRect, guidedTourWorkspace, foregroundTourModal } from '../../resources/js/guided-tour-workspace.js';

function surface(bounds, parent = null, css = {}) {
    return { isConnected: true, parentElement: parent, getClientRects: () => [bounds], closest: () => null, getBoundingClientRect: () => bounds, css };
}
const browser = { innerWidth: 1000, innerHeight: 700, getComputedStyle: node => ({ visibility: 'visible', display: 'block', overflowX: 'visible', overflowY: 'visible', ...node.css }) };

test('section icons follow clipped nested panes and never clamp offscreen targets onto other content', () => {
    const pane = surface({ left: 200, right: 600, top: 100, bottom: 400 }, null, { overflowY: 'auto', overflowX: 'hidden' });
    const target = surface({ left: 150, right: 650, top: 50, bottom: 450 }, pane);
    assert.deepEqual(tourAnchorRect(target, browser), { left: 200, right: 600, top: 100, bottom: 400 });
    target.getBoundingClientRect = () => ({ left: 150, right: 650, top: -600, bottom: -50 });
    assert.equal(tourAnchorRect(target, browser), null);
    target.getBoundingClientRect = () => ({ left: 150, right: 650, top: 50, bottom: 450 }); target.css.visibility = 'hidden';
    assert.equal(tourAnchorRect(target, browser), null);
});

test('multiple tours on one section have distinct launch positions and active tours retain their focus targets', () => {
    const target = surface({ left: 100, right: 400, top: 100, bottom: 300 });
    const doc = { querySelector: () => target, querySelectorAll: () => [] };
    const config = { targets: { section: '#section' }, tours: ['a','b'].map(key => ({ key, title: key, steps: [{ target: 'section' }] })) };
    const ui = guidedTourWorkspace(config, { window: browser, document: doc });
    ui.$store = { guidedTourHelp: { enabled: false } };
    ui.positionLaunchers(); assert.deepEqual(ui.launchers, []);
    ui.$store.guidedTourHelp.enabled = true; ui.positionLaunchers();
    assert.equal(ui.launchers.length, 2); assert.notEqual(ui.launchers[0].x, ui.launchers[1].x);
    const launchers = ui.launchers;
    ui.active = true; ui.positionLaunchers(); assert.equal(ui.launchers, launchers);
    ui.active = false; ui.$store.guidedTourHelp.enabled = false; ui.positionLaunchers(); assert.deepEqual(ui.launchers, []);
});


test('foreground dialogs own Escape while closed or hidden dialogs do not block icon-mode exit', () => {
    const modal = surface({left:100, right:500, top:100, bottom:500});
    const doc = { querySelectorAll: () => [modal] };
    assert.equal(foregroundTourModal(doc, browser), modal);
    modal.css.visibility = 'hidden'; assert.equal(foregroundTourModal(doc, browser), undefined);
    modal.css.visibility = 'visible'; modal.getClientRects = () => [];
    assert.equal(foregroundTourModal(doc, browser), undefined);
});

test('help preference survives modal Escape and navigation removes observers listeners and scheduled frames', () => {
    const originalMutation = globalThis.MutationObserver, originalResize = globalThis.ResizeObserver;
    const observers = [], frames = new Map(); let sequence = 0, modalOpen = true;
    class Observer { constructor() { observers.push(this); } observe() {} disconnect() { this.disconnected = true; } }
    globalThis.MutationObserver = Observer; globalThis.ResizeObserver = Observer;
    try {
        const doc = new EventTarget(), win = new EventTarget();
        const modal = surface({left:100,right:400,top:100,bottom:400});
        doc.body = {}; doc.documentElement = {}; doc.querySelectorAll = () => modalOpen ? [modal] : [];
        Object.assign(win, browser, {
            sessionStorage: { getItem: () => '1', setItem() {}, removeItem() {} },
            requestAnimationFrame(callback) { frames.set(++sequence, callback); return sequence; },
            cancelAnimationFrame(id) { frames.delete(id); },
        });
        const ui = guidedTourWorkspace({ userId:'test',targets:{},tours:[] }, {window:win,document:doc});
        ui.$store = { guidedTourHelp: { contexts:{},enabled:false,userKey:null } }; ui.$nextTick = callback => callback();
        ui.init(); assert.equal(ui.$store.guidedTourHelp.enabled, true);
        const escape = () => { const event = new Event('keydown'); Object.defineProperty(event,'key',{value:'Escape'}); doc.dispatchEvent(event); };
        escape(); assert.equal(ui.$store.guidedTourHelp.enabled, true);
        modalOpen = false; escape(); assert.equal(ui.$store.guidedTourHelp.enabled, false);
        doc.dispatchEvent(new Event('livewire:navigating'));
        assert.equal(frames.size,0); assert.ok(observers.every(observer=>observer.disconnected));
        assert.equal(Object.keys(ui.$store.guidedTourHelp.contexts).length,0);
        win.dispatchEvent(new Event('guided-tour-help-toggle')); assert.equal(ui.$store.guidedTourHelp.enabled,false);
        ui.destroy();
    } finally {
        globalThis.MutationObserver = originalMutation; globalThis.ResizeObserver = originalResize;
    }
});


test('section launchers avoid covering the native header action underneath their anchor corner', () => {
    const target = surface({left:100,right:400,top:100,bottom:200});
    const action = {closest: selector => selector === '[data-tour-launchers]' ? null : {tagName:'BUTTON'}};
    const heading = {closest: () => null};
    const doc = {querySelector:()=>target,querySelectorAll:()=>[],elementsFromPoint:x=>[x>360?action:heading]};
    const ui = guidedTourWorkspace({targets:{heading:'#heading'},tours:[{key:'intro',title:'Intro',steps:[{target:'heading'}]}]}, {window:browser,document:doc});
    ui.$store={guidedTourHelp:{enabled:true}};ui.positionLaunchers();
    assert.equal(ui.launchers.length,1);assert.ok(ui.launchers[0].x+15<=360);
});
