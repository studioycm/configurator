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
const criteria = raw => {
    if(!object(raw) || raw.version!==1 || !object(raw.filters) || !Array.isArray(raw.precedence)) invalid();
    const filters=Object.fromEntries(Object.entries(raw.filters).sort(([a],[b])=>a.localeCompare(b)).map(([key,value])=>[text(key),text(value)]));
    if(raw.subGroupId!==null && (typeof raw.subGroupId!=='string' || id(raw.subGroupId)!==raw.subGroupId)) invalid();
    return {version:1,filters,subGroupId:raw.subGroupId,precedence:raw.precedence.map(text)};
};

export function encodeJourney(raw) {
    if(!object(raw) || raw.version!==1) invalid();
    const groupId=id(raw.groupId), productId=raw.productId===null?null:id(raw.productId);
    let catalog=null,products=null;
    if(raw.catalog!==null) {
        if(!object(raw.catalog) || !object(raw.catalog.query)) invalid();
        const q=raw.catalog.query;
        if(!['tree','tags'].includes(q.mode) || [...text(q.search)].length>500 || !Array.isArray(q.tagValueIds) || q.tagValueIds.length>200) invalid();
        const tags=q.tagValueIds.map(value=>{id(value);return value;});
        if(new Set(tags.map(String)).size!==tags.length || (raw.catalog.anchor!==null && raw.catalog.anchor!==`model-${groupId}`)) invalid();
        if(q.branchId!==null) id(q.branchId);
        id(q.page);
        catalog={query:{branchId:q.branchId,search:q.search,tagValueIds:tags,mode:q.mode,page:q.page},anchor:raw.catalog.anchor};
    }
    if(raw.products!==null) {
        if(!object(raw.products) || (raw.products.anchor!==null && (productId===null || raw.products.anchor!==`product-${productId}`))) invalid();
        products={criteria:criteria(raw.products.criteria),anchor:raw.products.anchor};
    }
    const json=JSON.stringify({version:1,groupId,productId,catalog,products});
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
