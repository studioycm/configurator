import { test } from 'node:test';
import assert from 'node:assert/strict';
import { projectWritingSource, applyWritingEditorChange, writingHistory } from '../../resources/js/configurator-rule-writing-editor.js';

test('textarea projection maps mixed line endings and code point boundaries exactly', () => {
    const p = projectWritingSource('\uFEFFA\r\n\r😀שלום');
    assert.equal(p.text, '\uFEFFA\n\n😀שלום');
    assert.equal(p.rawOffset(3), 4);
    assert.equal(p.editorOffset(3), null);
    assert.equal(p.rawOffset(5), null);
    assert.equal(applyWritingEditorChange('A\r\n\rB', 'A\n\nB', {}).source, 'A\r\n\rB');
});

for (const [start, end, raw] of [[1, 2, 'A\rB'], [2, 3, 'A\r\nB']]) {
    test(`observed selection ${start} deletes the correct identical projected newline`, () => {
        const result = applyWritingEditorChange('A\r\n\rB', 'A\nB', { start, end, inputType: 'deleteContentBackward' });
        assert.equal(result.ok, true);
        assert.equal(result.source, raw);
        const history = writingHistory('A\r\n\rB');
        history.edit('A\nB', { start, end, inputType: 'deleteContentBackward' }, [start, start]);
        assert.equal(history.undo().source, 'A\r\n\rB');
        assert.equal(history.redo().source, raw);
    });
}

test('caret direction locates deletions and missing context refuses ambiguous identical lines', () => {
    assert.equal(applyWritingEditorChange('A\r\n\rB', 'A\nB', { start: 2, end: 2, inputType: 'deleteContentBackward' }).source, 'A\rB');
    assert.equal(applyWritingEditorChange('A\r\n\rB', 'A\nB', { start: 2, end: 2, inputType: 'deleteContentForward' }).source, 'A\r\nB');
    assert.deepEqual(applyWritingEditorChange('A\r\n\rB', 'A\nB', {}), { ok: false, type: 'ambiguous_edit', pendingText: 'A\nB' });
});

test('paste retains untouched raw whitespace and unicode, clears redo and preserves inserted LF', () => {
    const history = writingHistory('\uFEFFA\r\nB');
    history.edit('\uFEFFA\nשלום\nB', { start: 3, end: 3, inputType: 'insertFromPaste' }, [8, 8]);
    assert.equal(history.source, '\uFEFFA\r\nשלום\nB');
    history.undo();
    history.edit('\uFEFFA\nC', { start: 3, end: 4, inputType: 'insertText' });
    assert.equal(history.redo(), null);
    assert.equal(history.source, '\uFEFFA\r\nC');
});

test('backward deletion handles an emoji grapheme without splitting it', () => {
    const r = applyWritingEditorChange('A😀\r\nB', 'A\nB', { start: 3, end: 3, inputType: 'deleteContentBackward' });
    assert.equal(r.source, 'A\r\nB');
});

test('history availability notifies proxy observers after editing, Undo and Redo without replacing history', () => {
    const notifications = [];
    const history = new Proxy(writingHistory('X\r\nY'), {
        set(target, key, value, receiver) {
            const previous = Reflect.get(target, key, receiver);
            const result = Reflect.set(target, key, value, receiver);
            if (result && previous !== value) notifications.push([key, value]);
            return result;
        },
    });

    history.edit('X\nYQ', { start: 3, end: 3, inputType: 'insertText' }, [4, 4]);

    assert.deepEqual(notifications.splice(0), [['canUndo', true]]);
    assert.equal(history.source, 'X\r\nYQ');
    assert.deepEqual([history.canUndo, history.canRedo], [true, false]);

    history.undo();
    assert.deepEqual(notifications.splice(0), [['canUndo', false], ['canRedo', true]]);
    assert.equal(history.source, 'X\r\nY');
    history.edit('X\nY', { start: 3, end: 3, inputType: 'insertText' });
    assert.deepEqual(notifications, []);
    assert.deepEqual([history.canUndo, history.canRedo], [false, true]);

    history.redo();
    assert.deepEqual(notifications.splice(0), [['canUndo', true], ['canRedo', false]]);
    assert.equal(history.source, 'X\r\nYQ');
    history.undo(); notifications.splice(0);
    history.edit('X\nYZ', { start: 3, end: 3, inputType: 'insertText' });
    assert.deepEqual(notifications, [['canUndo', true], ['canRedo', false]]);
    assert.equal(history.redo(), null);
});
