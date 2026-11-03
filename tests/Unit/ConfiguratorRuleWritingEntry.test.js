import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ruleWritingEntry } from '../../resources/js/configurator-rule-writing-entry.js';

test('Writing entry warns for hidden ordinary dirty buffers, cancel preserves navigation and new tabs bypass', () => {
    let confirms = 0, prevented = 0;
    const guard = ruleWritingEntry({ confirm: () => { confirms++; return false; } });
    const event = { button: 0, currentTarget: { closest: () => ({ querySelector: () => ({ hidden: true }) }), target: '' }, preventDefault: () => prevented++ };
    guard.enter(event);
    assert.equal(confirms, 1);
    assert.equal(prevented, 1);
    guard.enter({ ...event, metaKey: true });
    assert.equal(confirms, 1);
    guard.enter({ ...event, currentTarget: { closest: () => ({ querySelector: () => null }) } });
    assert.equal(confirms, 1);
});
