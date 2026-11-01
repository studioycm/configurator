import { test } from 'node:test';
import assert from 'node:assert/strict';
import { encodeJourney, journeyLink, productBrowsePosition } from '../../resources/js/catalog-journey.js';
import { discoveryUrl, readDiscovery } from '../../resources/js/catalog-filter-engine.js';

const state = () => ({version:2,groupId:'5',productId:'9',catalog:{query:{branchId:1,search:'שסתום é',tagValueIds:[3],attributeValues:{},mode:'tags',page:2},anchor:'model-5'},
    products:{criteria:{version:1,filters:{pressure:'0',code:'001'},subGroupId:null,precedence:['filter:code','filter:pressure']},anchor:'product-9'}});
const decode = encoded => JSON.parse(Buffer.from(encoded,'base64url').toString('utf8'));

test('legacy return upgrades and r2 preserves literal Group maps separately from Product criteria and configuration',()=>{
    const legacy=state();legacy.version=1;delete legacy.catalog.query.attributeValues;const old=encodeJourney(legacy);const upgraded=decode(old);
    assert.equal(upgraded.version,2);assert.deepEqual(upgraded.catalog.query.attributeValues,{});
    const current={...upgraded,catalog:{...upgraded.catalog,query:{...upgraded.catalog.query,attributeValues:{application:[' 0 ','001','Case','case','מים Multi Word']}}}};
    const link=new URL(journeyLink('/dashboard/catalog/products/9?configuration=RAW%2B001&external=keep',current,'https://catalog.test'));
    const wire=decode(link.searchParams.get('r'));
    assert.deepEqual(wire.catalog.query.attributeValues,current.catalog.query.attributeValues);
    assert.deepEqual(wire.products.criteria,legacy.products.criteria);assert.equal(wire.products.criteria.version,1);
    assert.equal(link.searchParams.get('configuration'),'RAW+001');assert.equal(link.searchParams.get('external'),'keep');
    assert.throws(()=>encodeJourney({...current,catalog:{...current.catalog,query:{...current.catalog.query,attributeValues:[]}}}));
    assert.throws(()=>encodeJourney({...legacy,catalog:current.catalog}));
});

test('numeric property key zero stays an object map with exact value and chronology in a return link',()=>{
    const s=state();s.products.criteria.filters={'0':'001'};s.products.criteria.precedence=['filter:0'];
    const encoded=encodeJourney(s);const wire=decode(encoded);
    assert.equal(Array.isArray(wire.products.criteria.filters),false);
    assert.deepEqual(wire.products.criteria.filters,{'0':'001'});assert.deepEqual(wire.products.criteria.precedence,['filter:0']);
    const link=new URL(journeyLink('/dashboard/catalog/groups/5',s,'https://catalog.test'));
    assert.deepEqual(decode(link.searchParams.get('r')).products.criteria,wire.products.criteria);
    const groupReturn=discoveryUrl(link,wire.products.criteria);
    const active=readDiscovery(groupReturn,{fields:[{key:'0'}],presets:[]});
    assert.deepEqual(active.filters,{'0':'001'});assert.deepEqual(active.precedence,['filter:0']);
    s.products.criteria.filters=['001'];assert.throws(()=>encodeJourney(s));
});

test('journey links retain Unicode exact values chronology and unrelated query without changing source state',()=>{
    const s=state();const before=structuredClone(s);const url=new URL(journeyLink('/dashboard/catalog/products/9?external=keep&r=old',s,'https://catalog.test'));
    assert.equal(url.searchParams.get('external'),'keep');assert.deepEqual(decode(url.searchParams.get('r')),s);assert.deepEqual(s,before);
    assert.equal(url.pathname,'/dashboard/catalog/products/9');assert.equal(url.searchParams.getAll('r').length,1);
});
test('return envelopes cannot select a foreign origin or smuggle extra tree or URL objects',()=>{
    const s=state();s.returnUrl='https://evil.test';s.tree={secret:'never'};
    assert.throws(()=>journeyLink('https://evil.test/products/9',s,'https://catalog.test'));
    assert.throws(()=>journeyLink('https://user:pass@catalog.test/products/9',s,'https://catalog.test'));
    assert.throws(()=>encodeJourney(s));
});
test('encoded byte limit is enforced without truncating multibyte values',()=>{
    const s=state();s.products.criteria.filters.code='é'.repeat(4000);assert.throws(()=>encodeJourney(s),/journey/i);
    s.products.criteria.filters.code='0';assert.equal(decode(encodeJourney(s)).products.criteria.filters.code,'0');
});
for(const id of [true,0,1.1,'01','1e3'])test(`invalid identity ${id} cannot be coerced into a different owner`,()=>{
    const s=state();s.groupId=id;assert.throws(()=>encodeJourney(s));
});
test('wire maps lists anchors and UTF-8 input have distinct contracts',()=>{
    for(const mutate of [s=>s.products.criteria.filters=[],s=>s.products.criteria.filters.pressure=0,s=>s.products.criteria.precedence={},s=>s.catalog.anchor='script,button',s=>s.catalog.query.search='\ud800']){
        const s=state();mutate(s);assert.throws(()=>encodeJourney(s));
    }
    const s=state();s.products.criteria.filters={};s.products.criteria.precedence=[];assert.deepEqual(decode(encodeJourney(s)).products.criteria.filters,{});
});
test('Product position identity is order stable but preserves precedence and safe focus only',()=>{
    const a=state().products.criteria;const b={...a,filters:{code:'001',pressure:'0'}};const browser={scrollY:120,document:{activeElement:{dataset:{catalogFocus:'product-9'}}}};
    const p=productBrowsePosition('5',a,browser);assert.deepEqual(p,productBrowsePosition(5,b,browser));assert.equal(p.scroll,120);assert.equal(p.focus,'product-9');
    assert.notEqual(p.criteria,productBrowsePosition('5',{...a,precedence:[...a.precedence].reverse()},browser).criteria);
    browser.document.activeElement.dataset.catalogFocus='button,script';browser.scrollY=Infinity;
    assert.equal(productBrowsePosition('5',a,browser).focus,null);assert.equal(productBrowsePosition('5',a,browser).scroll,0);
});
