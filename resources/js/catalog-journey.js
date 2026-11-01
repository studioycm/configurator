const invalid = () => { throw new Error('Invalid journey'); };
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value) && [Object.prototype,null].includes(Object.getPrototypeOf(value));
const id = value => {
    if ((!['string','number'].includes(typeof value)) || (typeof value==='number' && !Number.isSafeInteger(value)) || !/^[1-9][0-9]*$/.test(String(value))) invalid();
    return String(value);
};
const text = value => {
    if(typeof value!=='string' || [...value].some(c=>c.codePointAt(0)>=0xd800 && c.codePointAt(0)<=0xdfff)) invalid();
    return value;
};
const keys = (value, expected) => {
    if (!object(value) || Object.keys(value).length !== expected.length || expected.some(key=>!Object.hasOwn(value,key))) invalid();
};
export function normalizeCatalogQuery(input, defaultMode = 'tree') {
    if (!object(input) || Object.keys(input).some(key=>!['branchId','search','tagValueIds','attributeValues','mode','page','requestId'].includes(key))) invalid();
    const positive = value => { const result=Number(id(value)); if(!Number.isSafeInteger(result)) invalid(); return result; };
    const search = Object.hasOwn(input,'search') ? text(input.search) : '';
    const tags = Object.hasOwn(input,'tagValueIds') ? input.tagValueIds : [];
    const attributes = Object.hasOwn(input,'attributeValues') ? input.attributeValues : {};
    const mode = Object.hasOwn(input,'mode') ? input.mode : defaultMode;
    if([...search].length>500 || !Array.isArray(tags) || tags.length>200 || !object(attributes) || !['tree','tags'].includes(mode)) invalid();
    const tagValueIds=Array.from(tags,positive).sort((a,b)=>a-b);
    if(new Set(tagValueIds).size!==tagValueIds.length) invalid();
    const entries=[];
    for(const key of Object.keys(attributes).sort()) {
        if(!/^[a-z][a-z0-9_]{0,99}$/.test(key)) invalid();
        const values=attributes[key];
        if(!Array.isArray(values) || values.length>25) invalid();
        const selected=Array.from(values,value=>{text(value);if(value==='' || value.includes('\0') || [...value].length>200)invalid();return value;});
        if(new Set(selected).size!==selected.length)invalid();
        entries.push([key,selected]);
    }
    if(new TextEncoder().encode(JSON.stringify(Object.fromEntries(entries))).length>32768)invalid();
    const attributeValues=Object.fromEntries(entries.filter(([,values])=>values.length));
    return {branchId:input.branchId==null?null:positive(input.branchId),search,tagValueIds,attributeValues,mode,page:positive(Object.hasOwn(input,'page')?input.page:1)};
}
const criteria = raw => {
    keys(raw,['version','filters','subGroupId','precedence']);
    if(raw.version!==1 || !object(raw.filters) || !Array.isArray(raw.precedence)) invalid();
    const filters=Object.fromEntries(Object.entries(raw.filters).sort(([a],[b])=>a.localeCompare(b)).map(([key,value])=>[text(key),text(value)]));
    if(raw.subGroupId!==null && (typeof raw.subGroupId!=='string' || id(raw.subGroupId)!==raw.subGroupId)) invalid();
    return {version:1,filters,subGroupId:raw.subGroupId,precedence:raw.precedence.map(text)};
};

export function encodeJourney(raw) {
    keys(raw,['version','groupId','productId','catalog','products']);
    if(![1,2].includes(raw.version)) invalid();
    const groupId=id(raw.groupId), productId=raw.productId===null?null:id(raw.productId);
    let catalog=null,products=null;
    if(raw.catalog!==null) {
        keys(raw.catalog,['query','anchor']);
        keys(raw.catalog.query,raw.version===1?['branchId','search','tagValueIds','mode','page']:['branchId','search','tagValueIds','attributeValues','mode','page']);
        if(raw.catalog.anchor!==null && raw.catalog.anchor!==`model-${groupId}`)invalid();
        catalog={query:normalizeCatalogQuery(raw.catalog.query),anchor:raw.catalog.anchor};
    }
    if(raw.products!==null) {
        keys(raw.products,['criteria','anchor']);
        if((raw.products.anchor!==null && (productId===null || raw.products.anchor!==`product-${productId}`))) invalid();
        products={criteria:criteria(raw.products.criteria),anchor:raw.products.anchor};
    }
    const json=JSON.stringify({version:2,groupId,productId,catalog,products});
    if(json.length>8192) invalid();
    const encoded=btoa(String.fromCharCode(...new TextEncoder().encode(json))).replaceAll('+','-').replaceAll('/','_').replace(/=+$/,'');
    if(encoded.length>8192) invalid();
    return encoded;
}

export function journeyLink(baseUrl, state, origin) {
    const url=new URL(baseUrl,origin);
    if(url.origin!==new URL(origin).origin || url.username || url.password) invalid();
    for(const key of [...url.searchParams.keys()]) if(key==='r' || key.startsWith('r[')) url.searchParams.delete(key);
    if(state!==null) url.searchParams.set('r',encodeJourney(state));
    return url.href;
}

export function productBrowsePosition(groupId, state, browser) {
    const focus=browser.document?.activeElement?.dataset?.catalogFocus;
    return {groupId:id(groupId),criteria:JSON.stringify(criteria(state)),scroll:Number.isFinite(browser.scrollY)?Math.min(10000000,Math.max(0,browser.scrollY)):0,
        focus:typeof focus==='string' && /^product-[1-9][0-9]*$/.test(focus) && focus.length<=200?focus:null};
}
