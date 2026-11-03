import {test} from 'node:test';
import assert from 'node:assert/strict';
import {normalizeWritingDisplay, ruleWritingDisplay, displayInspectionRecords, writingPalette} from '../../resources/js/configurator-rule-writing-display.js';
const storage = () => {const data = new Map(); return {getItem:k=>data.get(k)??null,setItem:(k,v)=>data.set(k,v),removeItem:k=>data.delete(k)};};
test('display validates colors, enums, sizes, source permanence and lower section order', () => {
    const p = normalizeWritingDisplay({theme:'bad', colors:{and:'url(evil)',or:'#123456'}, editor:{font:999,wrap:false}, visible:{editor:false,feedback:false}, lowerOrder:['learning','learning','unknown','logic']},'writing');
    assert.equal(p.theme,'inherit'); assert.equal(p.colors.or,'#123456'); assert.notEqual(p.colors.and,'url(evil)'); assert.equal(p.editor.wrap,false); assert.equal(p.editor.font,14);
    assert.equal(p.visible.feedback,false); assert(!Object.hasOwn(p.visible,'editor')); assert.deepEqual(p.lowerOrder,['learning','logic']);
});
test('saved display keys separate users, configurators and screens; reset affects just this screen', () => {
    const s=storage(), w=ruleWritingDisplay({user:'1',owner:'4',screen:'writing',storage:s}); w.patch({visible:{feedback:false},editor:{wrap:false}});
    assert.equal(ruleWritingDisplay({user:'1',owner:'4',screen:'writing',storage:s}).value.editor.wrap,false);
    for(const args of [{user:'2',owner:'4',screen:'writing'},{user:'1',owner:'5',screen:'writing'},{user:'1',owner:'4',screen:'data'}]) assert.notEqual(ruleWritingDisplay({...args,storage:s}).key,w.key);
    const d=ruleWritingDisplay({user:'1',owner:'4',screen:'data',storage:s});d.patch({theme:'dark'});w.reset();assert.equal(d.value.theme,'dark');assert.equal(w.value.visible.feedback,true);
});
test('blocked and malformed preference storage never blocks Writing', () => {
    const p=ruleWritingDisplay({user:'1',owner:'4',screen:'writing',storage:{getItem(){throw Error('blocked')},setItem(){throw Error('blocked')},removeItem(){throw Error('blocked')}}});
    assert.doesNotThrow(()=>p.patch({theme:'dark'}));assert.equal(p.value.theme,'dark');assert.doesNotThrow(()=>p.reset());
});
test('actual record buckets are represented once, unknown buckets remain reachable and complete copy is unchanged', () => {
    const inspection={json:'FULL JSON',yaml:'FULL YAML',draft_json:'DRAFT',capture_json:'CAPTURE',records:['rule','conditions','condition_groups','mapping_sets','effects','condition_references','effect_references','mapping_sources','mapping_targets','future_bucket'].map(key=>({key,label:key,rows:[{cells:[]}]}))};
    const before=JSON.stringify(inspection), prefs=normalizeWritingDisplay({},'data');
    const groups=displayInspectionRecords(inspection,prefs);const keys=groups.flatMap(x=>x.buckets.map(b=>b.key));
    assert.equal(new Set(keys).size,inspection.records.length);assert.deepEqual([...keys].sort(),inspection.records.map(x=>x.key).sort());
    prefs.visible.conditions=false;prefs.order.reverse();displayInspectionRecords(inspection,prefs);assert.equal(JSON.stringify(inspection),before);assert.equal(inspection.json,'FULL JSON');assert.equal(inspection.yaml,'FULL YAML');
});
test('palette foregrounds remain readable and custom colors do not change their semantic labels', () => {
    const colors=writingPalette({and:'#000000',or:'#ffffff'});assert.equal(colors.and.foreground,'#ffffff');assert.equal(colors.or.foreground,'#111827');assert.notEqual(colors.and.label,colors.or.label);
});

test('mid-tone custom colors select a readable foreground rather than a near-black failure',()=>{
    const color=writingPalette({source:'#797979'}).source;assert.equal(color.foreground,'#000000');
});
