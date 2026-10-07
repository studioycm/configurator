import { test } from 'node:test';
import assert from 'node:assert/strict';
import { catalogShell } from '../../resources/js/catalog-shell.js';

function environment({ desktop = true, saved = null, storageUnavailable = false } = {}) {
    const media = new EventTarget();
    media.matches = desktop;
    const document = new EventTarget();
    document.querySelector = () => null;
    const values = new Map(saved === null ? [] : [['aquestia:catalog:sidebar-collapsed:v1', saved]]);
    const browser = {
        document,
        matchMedia: () => media,
        localStorage: {
            getItem(key) { if (storageUnavailable) throw new Error('Storage denied'); return values.get(key) ?? null; },
            setItem(key, value) { if (storageUnavailable) throw new Error('Storage denied'); values.set(key, value); },
        },
    };
    const shell = catalogShell(browser);
    shell.$nextTick = callback => callback();
    shell.$refs = {};
    return { shell, media, document, values };
}

test('desktop collapse persists and is restored by the next page', () => {
    const { shell, values } = environment();
    shell.init();
    shell.toggleDesktop();
    assert.equal(shell.collapsed, true);
    assert.equal(values.get('aquestia:catalog:sidebar-collapsed:v1'), 'true');
    const next = environment({ saved: values.get('aquestia:catalog:sidebar-collapsed:v1') }).shell;
    next.init();
    assert.equal(next.collapsed, true);
});

test('mobile starts closed and keeps desktop preference through breakpoint transitions', () => {
    const { shell, media } = environment({ desktop: false, saved: 'true' });
    shell.init();
    assert.equal(shell.mobileOpen, false);
    assert.equal(shell.collapsed, false);
    shell.openMobile();
    assert.equal(shell.mobileOpen, true);
    media.matches = true;
    media.dispatchEvent(new Event('change'));
    assert.equal(shell.mobileOpen, false);
    assert.equal(shell.collapsed, true);
    media.matches = false;
    media.dispatchEvent(new Event('change'));
    assert.equal(shell.mobileOpen, false);
    assert.equal(shell.collapsed, false);
});

test('denied and malformed storage fall back to expanded usable navigation', () => {
    for (const settings of [{ storageUnavailable: true }, { saved: 'invalid' }]) {
        const { shell } = environment(settings);
        shell.init();
        assert.equal(shell.collapsed, false);
        assert.doesNotThrow(() => shell.toggleDesktop());
        assert.equal(shell.collapsed, true);
    }
});

test('navigation closes the mobile drawer and destroyed instances stop responding', () => {
    const { shell, media, document } = environment({ desktop: false });
    shell.init();
    shell.openMobile();
    document.dispatchEvent(new Event('livewire:navigating'));
    assert.equal(shell.mobileOpen, false);
    shell.destroy();
    shell.openMobile();
    document.dispatchEvent(new Event('livewire:navigating'));
    media.matches = true;
    media.dispatchEvent(new Event('change'));
    assert.equal(shell.mobileOpen, true);
    assert.equal(shell.isDesktop, false);
});
