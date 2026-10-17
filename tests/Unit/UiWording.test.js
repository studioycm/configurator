import { test } from 'node:test';
import assert from 'node:assert/strict';
import { interpolate } from '../../resources/js/ui-wording.js';

test('translated placeholders accept literal user text without interpreting replacement patterns', () => {
    const label = `Cost $& $' $\u0060 " עברית <script>`;
    assert.equal(interpolate('Set :number driver :label / :label', {number:1, label}), `Set 1 driver ${label} / ${label}`);
    assert.equal(interpolate(':unknown :label', {label:':number'}), ':unknown :number');
});
