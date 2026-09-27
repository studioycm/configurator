import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createFilterEngine, readDiscovery, discoveryUrl } from '../../resources/js/catalog-filter-engine.js';

const snapshot = {
    schema: 1, groupId: '9007199254740993', revision: '1',
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
const engine = createFilterEngine(snapshot);
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
test('singletons clear manual choices, multiple values preserve them, presets are atomic and repeated preset is a no-op', () => {
    let state = selectSequence([['connection','Flange'], ['pressure','25']]);
    state = engine.change(state, {type:'preset', id:'1'}).state;
    assert.equal(state.filters.connection, undefined);
    assert.equal(engine.change(state, {type:'preset',id:'1'}).changed, false);
    state = engine.change(state, {type:'preset',id:'2'}).state;
    assert.equal(state.filters.pressure, '25');
    state = engine.change(state, {type:'preset',id:'1'}).state;
    state = engine.change(state, {type:'filter',key:'size',value:'1'}).state;
    state = engine.change(state, {type:'filter',key:'pressure',value:'25'}).state;
    assert.equal(state.subGroupId, null);
    assert.deepEqual(engine.evaluate(state).ids, ['C']);
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
    const e=createFilterEngine(typed);
    assert.equal(e.evaluate(e.normalize({}).state).total,9);
    const state=e.change(e.normalize({}).state,{type:'filter',key:'value',value:'0'}).state;
    assert.deepEqual(e.evaluate(state).ids,['0','duplicate']);
    assert.equal(e.evaluate(state).compatibility.value[1],true);
});
test('URL parsing is bounded canonical and preserves unrelated query parameters', () => {
    const raw=readDiscovery('https://catalog.test/?utm=x&d[version]=1&d[filters][pressure]=25&d[filters][size]=1&d[page]=2');
    const normalized=engine.normalize(raw);
    assert.deepEqual(normalized.state.precedence,['filter:pressure','filter:size']);
    const url=discoveryUrl('https://catalog.test/?utm=x&d[page]=2#part',normalized.state);
    assert.equal(url.searchParams.get('utm'),'x'); assert.equal(url.hash,'#part');
    assert.equal(url.searchParams.has('d[page]'),false);
    assert.deepEqual(engine.normalize(readDiscovery(url)).state,normalized.state);
    assert.equal(engine.normalize({version:99,filters:{pressure:'25'}}).state.precedence.length,0);
    assert.equal(engine.normalize({filters:{pressure:'25',size:'1'},precedence:['filter:pressure','filter:pressure']}).adjusted,true);
});

test('removing obsolete pagination never changes valid selection precedence', () => {
    const raw = readDiscovery('https://catalog.test/?d[version]=1&d[filters][pressure]=25&d[filters][connection]=Flange&d[filters][size]=1&d[precedence][0]=filter:connection&d[precedence][1]=filter:pressure&d[precedence][2]=filter:size&d[page]=2');
    assert.deepEqual(engine.evaluate(engine.normalize(raw).state).ids, ['C']);
});
