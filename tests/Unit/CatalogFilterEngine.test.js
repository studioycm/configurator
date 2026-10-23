import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createFilterEngine, readDiscovery, discoveryUrl } from '../../resources/js/catalog-filter-engine.js';

import { catalogMessages } from '../Fixtures/catalog-labels.js';

const snapshot = {
    schema: 2, groupId: '9007199254740993', revision: '1',
    columns: [
        {key: 'pressure', values: ['25', '16']},
        {key: 'connection', values: ['Flange', 'Threaded']},
        {key: 'size', values: ['2', '1']},
        {key: 'model', values: ['M', 'N']},
    ],
    rows: [['A', 0, 0, 0, 0], ['B', 1, 0, 1, 1], ['C', 0, 1, 1, 0]],
    presets: [
        {id: '1', label: 'Flanged', key: 'connection', column: 1, values: ['Flange'], codes: [0]},
        {id: '2', label: 'Both pressures', key: 'pressure', column: 0, values: ['25', '16'], codes: [0, 1]},
        {id: '3', label: 'Model M', key: 'model', column: 3, values: ['M'], codes: [0]},
    ],
};
snapshot.fields = snapshot.columns.slice(0, 3).map((c, i) => ({key: c.key, label: c.key, column: i, options: c.values.map((value, code)=>({value, label: value, code}))}));
const engine = createFilterEngine(snapshot, catalogMessages);

for (const key of ['custom field', 'a.b', 'quote"key', "single'key", 'slash\\key', '0', '__proto__', 'constructor', 'a]b[c', 'שדה']) {
    test(`dynamic literal profile key ${key} survives selection and URL restoration`, () => {
        const dynamic = createFilterEngine({schema:2, groupId:'1', revision:'1',
            columns:[{key, values:['0','1']}], rows:[['first',0],['second',1]], presets:[],
            fields:[{key, label:key, column:0, options:[{value:'0',label:'0',code:0},{value:'1',label:'1',code:1}]}],
        }, catalogMessages);
        const selected = dynamic.change(dynamic.normalize({}).state, {type:'filter',key,value:'0'}).state;
        assert.deepEqual(dynamic.evaluate(selected).ids, ['first']);
        assert.equal(Object.hasOwn(selected.filters,key),true);
        const restored = dynamic.normalize(readDiscovery(discoveryUrl('https://catalog.test/',selected),{fields:[{key}],presets:[]})).state;
        assert.deepEqual(restored,selected);
    });
}

for (const count of [60, 120]) {
    test(`all ${count} selected fields and precedence tokens survive URL restoration`, () => {
        const keys = Array.from({length:count}, (_,index) => `field ${index}`);
        const large = {schema:2, groupId:'1', revision:'1', presets:[],
            columns:keys.map(key => ({key,values:['yes','no']})),
            fields:keys.map((key,column) => ({key,label:key,column,options:[{value:'yes',label:'yes',code:0},{value:'no',label:'no',code:1}]})),
            rows:[['first',...keys.map(() => 0)],['second',...keys.map(() => 1)]],
        };
        const dynamic = createFilterEngine(large,catalogMessages);
        const selected = dynamic.normalize({version:1,filters:Object.fromEntries(keys.map(key => [key,'yes'])),
            subGroupId:null,precedence:keys.toReversed().map(key => `filter:${key}`)}).state;
        const restored = dynamic.normalize(readDiscovery(discoveryUrl('https://catalog.test/',selected),large));

        assert.deepEqual(restored.state,selected);
        assert.equal(restored.adjusted,false);
        assert.deepEqual(dynamic.evaluate(restored.state).ids,['first']);
    });
}

test('URL parsing bounds forged fields and precedence by the snapshot field scope', () => {
    const url = new URL('https://catalog.test/');
    for (let index = 0; index < 20; index++) url.searchParams.set(`d[filters][foreign${index}]`,'yes');
    for (let index = 0; index < 20; index++) url.searchParams.set(`d[precedence][${index}]`,`filter:foreign${index}`);
    const raw = readDiscovery(url,snapshot);

    assert.deepEqual(raw.filters,{});
    assert.deepEqual(raw.precedence,[]);
    assert.equal(raw.adjusted,true);
});

function selectSequence(choices) {
    let state = engine.normalize({}).state;
    for (const [key, value] of choices) state = engine.change(state, {type:'filter', key, value}).state;
    return state;
}
test('newer choices win and the independent three-product fixture chooses exact identities', () => {
    const a = selectSequence([['pressure','25'], ['connection','Flange'], ['size','1']]);
    const b = selectSequence([['connection','Flange'], ['pressure','25'], ['size','1']]);
    assert.deepEqual(engine.evaluate(a).ids, ['B']);
    assert.deepEqual(engine.evaluate(b).ids, ['C']);
    assert.equal(a.filters.pressure, undefined);
    assert.equal(b.filters.connection, undefined);
});
test('compatibility excludes own field and keeps preset constraints', () => {
    let state = selectSequence([['pressure','25'], ['size','1']]);
    let result = engine.evaluate(state);
    assert.equal(result.compatibility.pressure[1], true);
    assert.equal(result.compatibility.connection[0], false);
    state = engine.change(state, {type:'preset', id:'3'}).state;
    result = engine.evaluate(state);
    assert.equal(result.compatibility.pressure[1], false);
});
test('singletons clear manual choices, multiple values preserve them and presets are atomic', () => {
    let state = selectSequence([['connection','Flange'], ['pressure','25']]);
    state = engine.change(state, {type:'preset', id:'1'}).state;
    assert.equal(state.filters.connection, undefined);
    state = engine.change(state, {type:'preset',id:'2'}).state;
    assert.equal(state.filters.pressure, '25');
    state = engine.change(state, {type:'preset',id:'1'}).state;
    state = engine.change(state, {type:'filter',key:'size',value:'1'}).state;
    state = engine.change(state, {type:'filter',key:'pressure',value:'25'}).state;
    assert.equal(state.subGroupId, null);
    assert.deepEqual(engine.evaluate(state).ids, ['C']);
});
test('clicking the active singleton clears it without restoring its discarded manual choice', () => {
    const selected = engine.change(selectSequence([['connection','Flange'], ['pressure','25']]), {type:'preset',id:'1'}).state;
    assert.equal(engine.evaluate(selected).hiddenKey, 'connection');
    const cleared = engine.change(selected, {type:'preset',id:'1'});
    assert.equal(cleared.changed, true);
    assert.equal(cleared.state.subGroupId, null);
    assert.deepEqual(cleared.state.filters, {pressure:'25'});
    assert.deepEqual(cleared.state.precedence, ['filter:pressure']);
    assert.equal(engine.evaluate(cleared.state).hiddenKey, null);
    assert.deepEqual(engine.evaluate(cleared.state).ids, ['A','C']);
});
test('clicking the active multivalue preset clears only that preset and preserves ordinary choices', () => {
    const selected = engine.change(selectSequence([['pressure','16']]), {type:'preset',id:'2'}).state;
    const cleared = engine.change(selected, {type:'preset',id:'2'});
    assert.equal(cleared.changed, true);
    assert.deepEqual(cleared.state, {version:1, filters:{pressure:'16'}, subGroupId:null, precedence:['filter:pressure']});
    assert.deepEqual(engine.evaluate(cleared.state).ids, ['B']);
    assert.deepEqual(engine.normalize(readDiscovery(discoveryUrl('https://catalog.test/', cleared.state),snapshot)).state, cleared.state);
    assert.equal(engine.change(cleared.state, {type:'preset',id:null}).changed, false);
});
test('clear keeps preset and reset clears all without restoring discarded values', () => {
    let state = engine.change(selectSequence([['pressure','25']]),{type:'preset',id:'3'}).state;
    state = engine.change(state,{type:'clear'}).state;
    assert.equal(state.subGroupId,'3'); assert.deepEqual(Object.keys(state.filters),[]);
    state = engine.change(state,{type:'reset'}).state;
    assert.equal(state.subGroupId,null); assert.equal(engine.evaluate(state).total,3);
    assert.equal(engine.change(state,{type:'reset'}).changed,false);
});
test('zero codes exact strings missing values and duplicate row combinations remain distinct', () => {
    const values=['0','01','Case','case','"','עברית','<script>alert(1)</script>'];
    const typed={...snapshot, columns:[{key:'value',values}],fields:[{key:'value',label:'Value',column:0,options:values.map((value,code)=>({value,label:value,code}))}],presets:[], rows:values.map((_,i)=>[String(i),i]).concat([['duplicate',0],['missing',-1]])};
    const e=createFilterEngine(typed, catalogMessages);
    assert.equal(e.evaluate(e.normalize({}).state).total,9);
    const state=e.change(e.normalize({}).state,{type:'filter',key:'value',value:'0'}).state;
    assert.deepEqual(e.evaluate(state).ids,['0','duplicate']);
    assert.equal(e.evaluate(state).compatibility.value[1],true);
});
test('URL parsing is bounded canonical and preserves unrelated query parameters', () => {
    const raw=readDiscovery('https://catalog.test/?utm=x&d[version]=1&d[filters][pressure]=25&d[filters][size]=1&d[page]=2',snapshot);
    const normalized=engine.normalize(raw);
    assert.deepEqual(normalized.state.precedence,['filter:pressure','filter:size']);
    const url=discoveryUrl('https://catalog.test/?utm=x&d[page]=2#part',normalized.state);
    assert.equal(url.searchParams.get('utm'),'x'); assert.equal(url.hash,'#part');
    assert.equal(url.searchParams.has('d[page]'),false);
    assert.deepEqual(engine.normalize(readDiscovery(url,snapshot)).state,normalized.state);
    assert.equal(engine.normalize({version:99,filters:{pressure:'25'}}).state.precedence.length,0);
    assert.equal(engine.normalize({filters:{pressure:'25',size:'1'},precedence:['filter:pressure','filter:pressure']}).adjusted,true);
});

test('removing obsolete pagination never changes valid selection precedence', () => {
    const raw = readDiscovery('https://catalog.test/?d[version]=1&d[filters][pressure]=25&d[filters][connection]=Flange&d[filters][size]=1&d[precedence][0]=filter:connection&d[precedence][1]=filter:pressure&d[precedence][2]=filter:size&d[page]=2',snapshot);
    assert.deepEqual(engine.evaluate(engine.normalize(raw).state).ids, ['C']);
});


test('notices preserve literal labels and replace every translated placeholder', () => {
    const custom = structuredClone(snapshot);
    custom.fields[0].label = 'Cost $& $` $\' " עברית';
    custom.presets[0].label = 'Preset $&';
    const e = createFilterEngine(custom, {...catalogMessages, field_cleared: ':field / :field'});
    const conflict = e.normalize({filters:{connection:'Flange', pressure:'25', size:'1'}, precedence:['filter:pressure','filter:connection','filter:size']});
    assert.deepEqual(conflict.notices, [custom.fields[0].label + ' / ' + custom.fields[0].label]);
    const singleton = e.normalize({filters:{connection:'Flange'}, subGroupId:'1'});
    assert.ok(singleton.notices.includes('The preset now controls connection.'));
    const cleared = e.normalize({filters:{pressure:'25', size:'1'}, subGroupId:'1', precedence:['subgroup:1','filter:size','filter:pressure']});
    assert.ok(cleared.notices.includes('Cleared preset Preset $& to keep your newer choice.'));
    assert.deepEqual(e.normalize({version:99}).notices, [catalogMessages.link_reset, catalogMessages.choices_adjusted]);
});

test('a missing or invalid notice dictionary fails explicitly instead of emitting blank notices', () => {
    for (const labels of [undefined, null, [], {}, {...catalogMessages, field_cleared: ''}]) {
        assert.throws(() => createFilterEngine(snapshot, labels), /labels/i);
    }
});
