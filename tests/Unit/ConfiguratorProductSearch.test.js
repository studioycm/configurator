import test from 'node:test';
import assert from 'node:assert/strict';
import { configuratorProductSearch } from '../../resources/js/configurator-product-search.js';

test('autocomplete supports keyboard selection and commits the actual Product ID', async () => {
    const search = configuratorProductSearch('FIRST');
    let chosen;
    search.$wire = {
        searchProducts: async () => [{ id: '9', label: 'SECOND' }, { id: '7', label: 'THIRD' }],
        chooseProduct: async id => { chosen = id; return Number(id); },
    };
    search.$nextTick = callback => callback();
    search.$refs = { results: { querySelector: () => null } };

    search.query = 'sec';
    await search.search();
    search.move(1);
    await search.chooseActive();

    assert.equal(chosen, '9');
    assert.equal(search.query, 'SECOND');
    assert.equal(search.open, false);
});

test('autocomplete ignores late results from a superseded query or closed popup', async () => {
    const search = configuratorProductSearch('FIRST');
    const responses = [];
    search.$wire = { searchProducts: () => new Promise(resolve => responses.push(resolve)) };

    search.query = 'old';
    const oldRequest = search.search();
    search.query = 'new';
    const newRequest = search.search();
    responses[1]([{ id: '2', label: 'NEW' }]);
    await newRequest;
    responses[0]([{ id: '1', label: 'OLD' }]);
    await oldRequest;
    assert.deepEqual(search.options, [{ id: '2', label: 'NEW' }]);

    const closedRequest = search.search();
    search.close();
    responses[2]([{ id: '3', label: 'LATE' }]);
    await closedRequest;
    assert.equal(search.open, false);
    assert.equal(search.query, 'FIRST');
});

test('an unsuccessful selection retains the accepted Product code', async () => {
    const search = configuratorProductSearch('FIRST');
    search.$wire = { chooseProduct: async () => { throw new Error('Unavailable'); } };

    await search.choose({ id: '99', label: 'FOREIGN' });

    assert.equal(search.query, 'FIRST');
    assert.equal(search.failed, true);
    assert.equal(search.choosing, false);
});

test('Product clear pointer and Enter methods honor the shared workspace barrier', async () => {
    const search = configuratorProductSearch('FIRST');
    const calls = [];
    search.workspace = { busy: true, request: async callback => callback() };
    search.$wire = { chooseProduct: async id => { calls.push(id); return id; } };
    search.options = [{ id: '7', label: 'SECOND' }];
    search.activeIndex = 0;
    await search.choose();
    await search.choose(search.options[0]);
    await search.chooseActive();
    assert.deepEqual(calls, []);
    assert.equal(search.selectedCode, 'FIRST');
});

test('Product choice obtains workspace admission and retains its code if admission is denied', async () => {
    const search = configuratorProductSearch('FIRST');
    let admitted = false;
    let choices = 0;
    search.workspace = { busy: false, request: async callback => { admitted = true; return callback(); } };
    search.$wire = { chooseProduct: async id => { choices++; return Number(id); } };
    await search.choose({ id: '7', label: 'SECOND' });
    assert.equal(admitted, true);
    assert.equal(search.selectedCode, 'SECOND');
    search.workspace.request = async () => false;
    await search.choose({ id: '9', label: 'THIRD' });
    assert.equal(choices, 1);
    assert.equal(search.selectedCode, 'SECOND');
});
