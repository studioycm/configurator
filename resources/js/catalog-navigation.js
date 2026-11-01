import { journeyLink, normalizeCatalogQuery } from './catalog-journey.js';

const positive = value => {
    if (!['string','number'].includes(typeof value) || !/^[1-9][0-9]*$/.test(String(value)) || !Number.isSafeInteger(Number(value))) throw new Error('Invalid identifier');
    return Number(value);
};
const validIdentity = value => value && Number.isSafeInteger(value.id) && value.id>0 && typeof value.name==='string' && (value.parentId===null || (Number.isSafeInteger(value.parentId) && value.parentId>0));
const validAttributeRows = rows => Array.isArray(rows) && rows.every(row=>row && typeof row.key==='string' && typeof row.label==='string' && Array.isArray(row.values));
const validCard = card => card && Number.isSafeInteger(card.id) && card.id > 0 && typeof card.name === 'string' && typeof card.url === 'string'
    && card.presentation && Array.isArray(card.presentation.group_card_content) && Array.isArray(card.tags)
    && ['ready','missing','unavailable'].includes(card.attributesStatus) && validAttributeRows(card.attributes)
    && card.attributes.every(row=>row.values.every(value=>typeof value==='string')) && Array.isArray(card.ancestors) && card.ancestors.every(validIdentity)
    && card.tags.every(tag=>Number.isSafeInteger(tag.id) && typeof tag.label==='string' && typeof tag.categoryLabel==='string');
export function normalizeNavigationQuery(input, defaultMode = 'tree') {
    return normalizeCatalogQuery(input, defaultMode);
}
export function navigationQueryFromUrl(url, defaultMode = 'tree') {
    const state={};const tags=new Map();const attributes=new Map();const attributeStyles=new Map();let style=null;
    const invalid=()=>new Error('Invalid catalog link');
    for(const part of new URL(url).search.slice(1).split('&')) {
        const separator=part.indexOf('=');const rawKey=separator<0?part:part.slice(0,separator);const rawValue=separator<0?'':part.slice(separator+1);
        const looseKey=[...new URLSearchParams(rawKey+'=')][0]?.[0] || '';
        if(looseKey!=='g' && !looseKey.startsWith('g['))continue;
        let key,value;
        try { key=decodeURIComponent(rawKey.replace(/\+/g,' '));value=decodeURIComponent(rawValue.replace(/\+/g,' ')); } catch { throw invalid(); }
        if(key==='g')throw invalid();
        const attribute=key.match(/^g\[attributes\]\[([a-z][a-z0-9_]{0,99})\]\[([0-9]*)\]$/);
        if(attribute) {
            const [,name,index]=attribute;const form=index===''?'append':'indexed';const list=attributes.get(name) || new Map();
            if((attributeStyles.has(name) && attributeStyles.get(name)!==form) || list.size>=25 || (form==='indexed' && (!/^(0|[1-9][0-9]*)$/.test(index) || Number(index)>=25 || list.has(Number(index)))))throw invalid();
            attributeStyles.set(name,form);list.set(form==='append'?list.size:Number(index),value);attributes.set(name,list);continue;
        }
        const tag=key.match(/^g\[tags\]\[([0-9]*)\]$/);
        if(tag) {
            const form=tag[1]===''?'append':'indexed';
            if((style!==null && style!==form) || tags.size>=200 || (form==='indexed' && (!/^(0|[1-9][0-9]*)$/.test(tag[1]) || Number(tag[1])>=200 || tags.has(Number(tag[1])))))throw invalid();
            style=form;tags.set(form==='append'?tags.size:Number(tag[1]),value);continue;
        }
        const map={'g[version]':'version','g[branch]':'branchId','g[q]':'search','g[mode]':'mode','g[page]':'page'};
        if(!map[key] || Object.hasOwn(state,map[key]))throw invalid();
        state[map[key]]=value;
    }
    if(style!==null) {
        const entries=[...tags].sort((a,b)=>a[0]-b[0]);
        if(entries.some(([index],position)=>index!==position))throw invalid();
        state.tagValueIds=entries.map(([,value])=>value);
    }
    if(Object.keys(state).length && !['1','2'].includes(state.version))throw invalid();
    if(attributes.size && state.version!=='2')throw invalid();
    if(attributes.size)state.attributeValues=Object.fromEntries([...attributes].map(([key,list])=>{const entries=[...list].sort((a,b)=>a[0]-b[0]);if(entries.some(([index],position)=>index!==position))throw invalid();return [key,entries.map(([,value])=>value)];}));
    delete state.version;
    return normalizeNavigationQuery(state,defaultMode);
}

export function catalogNavigation(browser = window) {
    return {
        hydrated:false, active:false, projection:null, criteria:null, searchText:'', loading:false, error:'', notice:'', journeyError:'', criteriaOpen:false, sidebarOpen:true, narrow:false,
        expanded:{}, treePages:{}, treeRequests:{}, treeErrors:{}, treeRefreshes:0, generation:0, treeGeneration:0, sequence:0, timer:null, searchRevision:0, settledSearchRevision:0, pendingSearchRevision:0, labels:{}, root:null, pendingQuery:null, pendingIntent:null,
        init() {
            if (this.active) return;
            this.root=this.$el; this.labels=JSON.parse(this.root.dataset.labels || '{}');
            try {
                this.projection=JSON.parse(this.root.querySelector('[data-navigation-projection]').textContent);
                this.criteria=normalizeNavigationQuery(this.projection.query); this.validateReply(this.projection,this.criteria,this.projection.requestId); this.searchText=this.criteria.search;
                this.active=true;this.seedTree();this.hydrated=true;this.resize();
                this.onPop=()=>this.restore(); this.onPageShow=event=>{if(event.persisted)this.restore();};this.onPageHide=()=>{this.persistPosition();this.generation++;this.treeGeneration++;this.treeRequests={};this.cancelSearch();};this.onResize=()=>this.resize();
                browser.addEventListener('popstate',this.onPop);browser.addEventListener('pageshow',this.onPageShow);browser.addEventListener('pagehide',this.onPageHide);browser.addEventListener('resize',this.onResize);
                this.restoreJourneyAnchor();
            } catch { this.error=this.labels.invalid || 'Invalid catalog state.'; }
        },
        modelHref(card) {
            try {
                return journeyLink(card.url,{version:2,groupId:String(card.id),productId:null,catalog:{query:this.criteria,anchor:`model-${card.id}`},products:null},browser.location.href);
            } catch {
                this.journeyError=this.root.dataset.journeyOverflow || '';
                try { return journeyLink(card.url,null,browser.location.href); }
                catch { return this.root.dataset.catalogIndexUrl; }
            }
        },
        restoreJourneyAnchor() {
            const raw=this.root.dataset.journeyReturn;
            if(!raw)return;
            let position;
            try { position=JSON.parse(raw);if(!position || JSON.stringify(normalizeNavigationQuery(position.query))!==JSON.stringify(this.criteria) || !/^model-[1-9][0-9]*$/.test(position.anchor))return; }
            catch { return; }
            const generation=this.generation;
            this.$nextTick(()=>{
                if(!this.active || this.generation!==generation)return;
                const element=[...(this.root.querySelectorAll?.('[data-catalog-focus]') || [])].find(el=>el.dataset.catalogFocus===position.anchor);
                element?.focus({preventScroll:true});element?.scrollIntoView?.({block:'nearest'});
            });
        },
        resize() { this.narrow=browser.innerWidth < 1024; },
        familyHeading(pattern,root) { return this.projection.branch ? pattern.replace(':name',()=>this.projection.branch.name) : root; },
        get drawer() { return this.narrow || this.projection?.presentation.criteria_container === 'drawer'; },
        get visibleTreeRows() {
            const rows=[];const visit=(parent,depth,path)=>{
                for(const row of this.treePages[parent]?.rows || []) {
                    if(path.has(row.id))continue;
                    rows.push({...row,depth});
                    if(this.expanded[row.id])visit(row.id,depth+1,new Set([...path,row.id]));
                }
            };visit('root',0,new Set());return rows;
        },
        get intendedCriteria() {
            const base=this.pendingQuery || this.criteria;
            const revision=this.pendingQuery ? this.pendingSearchRevision : this.settledSearchRevision;
            return this.searchRevision > revision ? {...base,search:this.searchText,page:1} : base;
        },
        get selectedTags() { return (this.projection?.tags || []).flatMap(c=>c.values).filter(v=>this.intendedCriteria?.tagValueIds.includes(v.id)); },
        get selectedAttributes() {
            const labels=new Map((this.projection?.attributeDefinitions || []).map(row=>[row.key,row.label]));
            return Object.entries(this.intendedCriteria?.attributeValues || {}).flatMap(([key,values])=>values.map(value=>({key,value,label:labels.get(key) || key,identity:JSON.stringify([key,value])})));
        },
        seedTree() {
            this.treeGeneration++;this.expanded={};this.treeRequests={};this.treeErrors={};
            this.treePages={root:{rows:this.projection.treeRows,...this.projection.rootTreePagination}};
        },
        id() { return `navigation-${Date.now().toString(36)}-${++this.sequence}`; },
        validateReply(reply,query,id) {
            if(!reply || reply.requestId !== id || typeof reply.queryIdentity !== 'string' || !reply.queryIdentity || typeof reply.treeVersion !== 'string' || !/^[a-f0-9]{64}$/.test(reply.treeVersion)
                || typeof reply.displayVersion !== 'string' || !reply.presentation || !Array.isArray(reply.tags) || !Array.isArray(reply.treeRows) || reply.treeRows.length>50 || !Array.isArray(reply.cards) || reply.cards.length>24
                || !reply.cards.every(validCard) || new Set(reply.cards.map(card=>card.id)).size!==reply.cards.length
                || !Array.isArray(reply.attributeDefinitions) || reply.attributeDefinitions.some(row=>!row || !/^[a-z][a-z0-9_]{0,99}$/.test(row.key) || typeof row.label!=='string')
                || new Set(reply.attributeDefinitions.map(row=>row.key)).size!==reply.attributeDefinitions.length || !validAttributeRows(reply.attributes)
                || reply.attributes.some(row=>!reply.attributeDefinitions.some(def=>def.key===row.key) || row.values.some(value=>typeof value.value!=='string' || !Number.isInteger(value.count) || value.count<0 || typeof value.selected!=='boolean' || typeof value.available!=='boolean'))
                || !Array.isArray(reply.ancestors) || (query.branchId===null ? reply.branch!==null : reply.branch?.id!==query.branchId)
                || !Number.isInteger(reply.total) || reply.total<reply.cards.length || reply.page!==query.page
                || reply.cards.length!==Math.min(24,Math.max(0,reply.total-(query.page-1)*24)) || reply.hasMore!==(query.page<Math.ceil(reply.total/24)) || typeof reply.hasMore !== 'boolean' || !reply.rootTreePagination
                || JSON.stringify(normalizeNavigationQuery(reply.query)) !== JSON.stringify(normalizeNavigationQuery(query))) throw new Error('Invalid response');
            return reply;
        },
        async navigate(input, {history='push', branchChange=false, reconcile=true, removed=[], draftRevision=this.searchRevision}={}) {
            const previousQuery=JSON.stringify(this.criteria);
            if(history!=='none')this.persistPosition();
            const generation=++this.generation;const id=this.id();this.loading=true;this.error='';this.notice='';
            let query;
            try {
                query=normalizeNavigationQuery(input,this.projection.presentation.sidebar_mode);this.pendingQuery=query;this.pendingSearchRevision=draftRevision;
                this.pendingIntent={query,options:{history,branchChange,reconcile,removed,draftRevision}};
                const reply=this.validateReply(await this.$wire.loadNavigation({...query,requestId:id}),query,id);
                if(!this.active || generation!==this.generation)return;
                if(branchChange && reconcile) {
                    const available=new Set(reply.tags.flatMap(c=>c.values.map(v=>v.id)));
                    const retained=query.tagValueIds.filter(v=>available.has(v));
                    const availableAttributes=new Map(reply.attributes.map(row=>[row.key,new Set(row.values.filter(value=>value.available).map(value=>value.value))]));
                    const retainedAttributes=Object.fromEntries(Object.entries(query.attributeValues).map(([key,values])=>[key,query.branchId===this.criteria.branchId?values:values.filter(value=>availableAttributes.get(key)?.has(value))]).filter(([,values])=>values.length));
                    if(retained.length!==query.tagValueIds.length || JSON.stringify(retainedAttributes)!==JSON.stringify(query.attributeValues)) {
                        const newlyRemoved=[...this.removedTagChoices(query.tagValueIds.filter(id=>!available.has(id))),
                            ...Object.entries(query.attributeValues).flatMap(([key,values])=>values.filter(value=>!retainedAttributes[key]?.includes(value)).map(value=>({attributeKey:key,value,category:reply.attributeDefinitions.find(row=>row.key===key)?.label || key,label:value})))];
                        const removedChoices=[...new Map([...removed,...newlyRemoved].map(choice=>[this.removedIdentity(choice),choice])).values()];
                        return await this.navigate({...query,tagValueIds:retained,attributeValues:retainedAttributes},{history,branchChange:true,reconcile:false,removed:removedChoices,draftRevision});
                    }
                }

                const treeChanged=reply.treeVersion!==this.projection.treeVersion;
                this.projection=reply;this.criteria=normalizeNavigationQuery(reply.query);this.settledSearchRevision=draftRevision;
                if(this.searchRevision===draftRevision)this.searchText=this.criteria.search;
                if(treeChanged)this.seedTree();
                if(removed.length)this.notice=(this.labels.removed || 'Unavailable choices removed') + ': ' + removed.map(choice=>choice.label===undefined ? '#'+choice.id : choice.category + ': ' + choice.label + (choice.attributeKey===undefined?' (#'+choice.id+')':'')).join('; ');
                this.pendingQuery=null;this.pendingIntent=null;this.loading=this.timer!==null;
                if(history!=='none')this.writeHistory(history==='push' && JSON.stringify(this.criteria)===previousQuery ? 'replace' : history);
            } catch {
                if(!this.active || generation!==this.generation)return;
                this.error=this.labels.failed || 'Could not load groups. Retry or clear invalid choices.';this.loading=false;
            }
        },
        removedIdentity(choice) { return JSON.stringify(choice.attributeKey===undefined?['tag',choice.id]:['attribute',choice.attributeKey,choice.value]); },
        removedTagChoices(ids) {
            const known=new Map(this.projection.tags.flatMap(category=>category.values.map(value=>[value.id,{id:value.id,category:category.label,label:value.label}])));
            return ids.map(id=>known.get(id) || {id});
        },
        cancelSearch() {
            if(this.timer!==null){if(browser.clearTimeout)browser.clearTimeout(this.timer);else clearTimeout(this.timer);this.timer=null;}
        },
        consumeSearch() { const intent=this.intendedCriteria;this.cancelSearch();return intent; },
        searchLater(value=this.searchText) {
            this.searchText=value;this.searchRevision++;this.cancelSearch();this.loading=true;
            const callback=()=>{this.timer=null;return this.searchNow();};
            this.timer=browser.setTimeout?browser.setTimeout(callback,250):setTimeout(callback,250);
        },
        continueNavigation(input, {history='push', branchChange=false}={}) {
            let query;
            try { query=normalizeNavigationQuery(input,this.projection.presentation.sidebar_mode); }
            catch { return this.navigate(input,{history,branchChange,draftRevision:this.searchRevision}); }
            const pending=this.pendingIntent;
            const options=pending && pending.query.branchId===query.branchId ? pending.options : null;
            const sameTags=options && JSON.stringify(pending.query.tagValueIds)===JSON.stringify(query.tagValueIds) && JSON.stringify(pending.query.attributeValues)===JSON.stringify(query.attributeValues);
            const contextChanged=['branchId','tagValueIds','attributeValues','mode','page'].some(key=>JSON.stringify(query[key])!==JSON.stringify(this.criteria[key]));
            return this.navigate(query,{
                history:options?.history==='push' && contextChanged ? 'push' : history,
                branchChange:branchChange || options?.branchChange || false,
                reconcile:sameTags ? options.reconcile : true,
                removed:(options?.removed || []).filter(choice=>choice.attributeKey===undefined?!query.tagValueIds.includes(choice.id):!query.attributeValues[choice.attributeKey]?.includes(choice.value)),
                draftRevision:this.searchRevision,
            });
        },
        searchNow() { const intent={...this.consumeSearch(),search:this.searchText,page:1};return this.continueNavigation(intent,{history:'replace'}); },
        chooseBranch(id) { const branchId=id===null?null:positive(id);if(branchId===this.intendedCriteria.branchId)return;const intent=this.consumeSearch();const tagValueIds=this.projection.presentation.branch_tag_retention==='clear'?[]:intent.tagValueIds;const attributeValues=this.projection.presentation.branch_attribute_retention==='clear'?{}:intent.attributeValues;return this.navigate({...intent,branchId,tagValueIds,attributeValues,page:1},{branchChange:true}); },
        chooseMode(mode) { const intent=this.consumeSearch();this.treeGeneration++;this.treeRequests={};return this.continueNavigation({...intent,mode}); },
        toggleTag(id) { const intent=this.consumeSearch();const tags=intent.tagValueIds;return this.continueNavigation({...intent,page:1,tagValueIds:tags.includes(id)?tags.filter(v=>v!==id):[...tags,id]}); },
        toggleAttribute(key,value) { const intent=this.consumeSearch();const values=intent.attributeValues[key] || [];const selected=values.includes(value)?values.filter(v=>v!==value):[...values,value];return this.continueNavigation({...intent,page:1,attributeValues:{...intent.attributeValues,[key]:selected}}); },
        clearAttributes() { return this.continueNavigation({...this.consumeSearch(),attributeValues:{},page:1}); },
        reset() { this.cancelSearch();this.searchRevision++;this.searchText='';return this.navigate({...this.criteria,search:'',tagValueIds:[],attributeValues:{},page:1}); },
        page(number) { return this.continueNavigation({...this.consumeSearch(),page:number}); },
        retry() { return this.continueNavigation(this.consumeSearch(),{history:'replace'}); },
        refresh() { return this.continueNavigation(this.consumeSearch(),{history:'replace',branchChange:true}); },
        recoverTree() {
            const pending=this.pendingIntent;
            return pending ? this.navigate(pending.query,pending.options)
                : this.navigate(this.criteria,{history:'none',draftRevision:this.settledSearchRevision});
        },
        async restore() {
            this.cancelSearch();this.pendingQuery=null;this.pendingIntent=null;this.treeGeneration++;this.treeRequests={};
            try {
                const query=navigationQueryFromUrl(browser.location.href,this.projection.presentation.sidebar_mode);
                const position=browser.history.state?.catalogGroupBrowse;const revision=++this.searchRevision;this.searchText=query.search;
                await this.navigate(query,{history:'none'});
                if(!this.error && revision===this.searchRevision)this.restorePosition(position);
            } catch { this.generation++;this.loading=false;this.error=this.labels.invalid || 'Invalid catalog state.'; }
        },
        persistPosition() {
            if(!this.criteria)return;
            const focus=browser.document?.activeElement?.dataset?.catalogFocus || null;
            const position={query:JSON.stringify(this.criteria),scroll:Math.max(0,browser.scrollY || 0),focus};
            browser.history.replaceState({...browser.history.state,catalogGroupBrowse:position},'',browser.location.href);
        },
        restorePosition(position) {
            if(!position || position.query!==JSON.stringify(this.criteria))return;
            this.$nextTick(()=>{
                if(!this.active)return;
                if(typeof position.focus==='string' && position.focus.length<=200)[...this.root.querySelectorAll('[data-catalog-focus]')].find(el=>el.dataset.catalogFocus===position.focus)?.focus({preventScroll:true});
                if(Number.isFinite(position.scroll) && position.scroll>=0)browser.scrollTo({top:Math.min(position.scroll,10000000),behavior:'instant'});
            });
        },
        writeHistory(kind) {
            const url=new URL(browser.location.href);for(const key of [...url.searchParams.keys()])if(key==='g' || key.startsWith('g[') || key==='r' || key.startsWith('r['))url.searchParams.delete(key);
            const q=this.criteria;url.searchParams.set('g[version]','2');
            if(q.branchId!==null)url.searchParams.set('g[branch]',String(q.branchId));
            if(q.search)url.searchParams.set('g[q]',q.search);
            Object.entries(q.attributeValues).forEach(([key,values])=>values.forEach(value=>url.searchParams.append(`g[attributes][${key}][]`,value)));
            q.tagValueIds.forEach(id=>url.searchParams.append('g[tags][]',String(id)));
            url.searchParams.set('g[mode]',q.mode);if(q.page!==1)url.searchParams.set('g[page]',String(q.page));
            if(url.href!==browser.location.href)browser.history[kind==='replace'?'replaceState':'pushState'](browser.history.state,'',url.href);
        },
        async toggleTree(row) {
            this.expanded={...this.expanded,[row.id]:!this.expanded[row.id]};
            if(this.expanded[row.id] && !this.treePages[row.id])await this.loadTree(row.id,1);
        },
        moreTree(parent=null) { const key=parent??'root';const next=this.treePages[key]?.nextPage;if(next)return this.loadTree(parent,next); },
        async loadTree(parent,page) {
            const key=parent??'root';if(this.treeRequests[key])return;
            const generation=this.treeGeneration;const version=this.projection.treeVersion;const id=this.id();this.treeRequests={...this.treeRequests,[key]:id};this.treeErrors={...this.treeErrors,[key]:''};
            try {
                const reply=await this.$wire.loadTreeChildren(parent,page,version,id);
                if(!this.active || generation!==this.treeGeneration || this.treeRequests[key]!==id || version!==this.projection.treeVersion)return;
                if(reply?.requestId!==id || reply.parentId!==parent || reply.page!==page)throw new Error('Wrong tree response');
                if(reply.status==='refresh_required') {
                    if(this.treeRefreshes++===0){this.seedTree();await this.recoverTree();}
                    else throw new Error('Tree keeps changing');
                    return;
                }
                if(reply.status!=='ready' || reply.treeVersion!==version || !Array.isArray(reply.rows) || reply.rows.length>50 || typeof reply.hasMore!=='boolean'
                    || reply.rows.some(row=>row.parentId!==parent || !Number.isSafeInteger(row.id)))throw new Error('Invalid tree page');
                const rows=[...(page===1?[]:this.treePages[key]?.rows || []),...reply.rows];
                this.treePages={...this.treePages,[key]:{rows:[...new Map(rows.map(row=>[row.id,row])).values()],page,nextPage:reply.nextPage,hasMore:reply.hasMore}};
            }catch{
                if(this.active && generation===this.treeGeneration)this.treeErrors={...this.treeErrors,[key]:this.labels.treeChanged || 'Could not load branch. Retry.'};
            }finally{
                if(this.treeRequests[key]===id){const next={...this.treeRequests};delete next[key];this.treeRequests=next;}
            }
        },
        retryTree(parent) { this.treeRefreshes=0;const key=parent??'root';return this.loadTree(parent,this.treePages[key]?.nextPage || 1); },
        openCriteria() {
            this.criteriaOpen=!this.criteriaOpen;
            if(this.criteriaOpen && this.drawer)this.$nextTick(()=>this.$refs.criteriaDialog?.querySelector('button,input')?.focus());
        },
        closeCriteria() {
            const opener=this.$refs.criteriaTrigger;this.criteriaOpen=false;
            this.$nextTick(()=>{
                if(!this.active || this.criteriaOpen || !opener?.isConnected || opener.disabled || opener.getAttribute?.('aria-disabled')==='true'
                    || !opener.getClientRects().length || opener.closest('[inert]'))return;
                const style=browser.getComputedStyle?.(opener);
                if(style?.visibility==='hidden' || style?.visibility==='collapse' || style?.display==='none')return;
                opener.focus({preventScroll:true});
            });
        },
        trapCriteria(event) {
            if(!this.drawer || !this.criteriaOpen || event.key!=='Tab')return;
            const items=[...this.$refs.criteriaDialog.querySelectorAll('button:not([disabled]),input:not([disabled]),a[href]')].filter(el=>el.getClientRects().length);
            if(!items.length)return;const first=items[0],last=items.at(-1);
            if(event.shiftKey && event.target===first){event.preventDefault();last.focus();}else if(!event.shiftKey && event.target===last){event.preventDefault();first.focus();}
        },
        destroy() {
            this.active=false;this.generation++;this.treeGeneration++;this.cancelSearch();
            browser.removeEventListener('popstate',this.onPop);browser.removeEventListener('pageshow',this.onPageShow);browser.removeEventListener('pagehide',this.onPageHide);browser.removeEventListener('resize',this.onResize);
        },
    };
}
