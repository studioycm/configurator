import { parseRuleWriting, ruleWritingByteOffset, ruleWritingEditorOffset } from './configurator-rule-writing-model.js';
import { projectWritingSource, writingHistory } from './configurator-rule-writing-editor.js';
import {ruleWritingContext} from './configurator-rule-writing-context.js';
import {ruleWritingLogic} from './configurator-rule-writing-logic.js';
import {ruleWritingDisplay, writingPalette, writingForeground, displayInspectionRecords} from './configurator-rule-writing-display.js';
const content = b => ({ source: b.source, label: b.label, description: b.description });
const same = (a, b) => a?.source === b?.source && a?.label === b?.label && a?.description === b?.description;
const safe = n => Number.isSafeInteger(n) && n >= 0;
const uuid = () => {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = bytes[6] & 15 | 64; bytes[8] = bytes[8] & 63 | 128;
    return [...bytes].map((v, i) => ([4, 6, 8, 10].includes(i) ? '-' : '') + v.toString(16).padStart(2, '0')).join('');
};
class RequestFailure extends Error {
    constructor(result) { super(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'The request could not be completed.'); this.result = result; }
}

export function ruleWritingWorkspace(config = {}, dependencies = {}) {
    let referenceGeneration=0,choiceGeneration=0,analysisCache=null,resizeCleanup=null;
    let destroyed = false, tail = Promise.resolve(), pending = 0, removeGuard, captureSequence = 0;
    const timers = new Map(), setTimer = dependencies.setTimer ?? setTimeout, clearTimer = dependencies.clearTimer ?? clearTimeout;
    const request = dependencies.request ?? (async (operation, input) => {
        const response = await fetch(config.operationUrl.replace('__operation__', operation), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf }, body: JSON.stringify(input) });
        let result;
        try { result = await response.json(); } catch { throw new RequestFailure({ type: 'unknown', message: 'The response could not be read. Inspect the current records before retrying.' }); }
        if (result?.ok === undefined) result = { ok: false, type: [400, 401, 403, 404, 413, 415, 419, 422].includes(response.status) ? 'rejected' : 'unknown', message: result.message, status: response.status };
        return result;
    });
    const stopTimer = id => { const entry = timers.get(id); if (entry) { clearTimer(entry.debounce); clearTimer(entry.maximum); timers.delete(id); } };
    return {
        references:null,referenceError:'',referencesLoading:false,choicesOpen:false,choiceMode:null,choiceIndex:0,editorAnchor:null,learningAction:null,textOpen:false,textAnchor:null,examplesAnchor:null,textMode:'block',textValue:'',examplesOpen:false,errorContext:null,noticeOwner:null,
        writingDisplay:ruleWritingDisplay({user:config.user,owner:config.owner,screen:'writing',storage:dependencies.storage}),
        dataDisplay:ruleWritingDisplay({user:config.user,owner:config.owner,screen:'data',storage:dependencies.storage}),
        buffers: {}, unopenedFailures: {}, currentId: null, selectedDrafts: {}, blocked: {}, drafts: [], listedDrafts: {}, rules: [], page: 1, hasMore: false, search: '', rulePage: 1, ruleMore: false, ruleSearch: '', picker: false,
        tab: 'writing', dataView: 'records', inspection: null, inspectedId: null, receipt: null, notice: '', error: '', failedRuleId: null, busy: false, composing: null, editContext: null, historyHandled: null,
        get selected() { return Object.keys(this.selectedDrafts); },
        set selected(ids) {
            const selection = {};
            for (const id of ids) selection[id] = this.selectedDrafts[id] ?? { id, revision: this.buffers[id]?.revision ?? this.listedDrafts[id]?.revision };
            this.selectedDrafts = selection;
        },
        get current() { return this.buffers[this.currentId] ?? null; },
        get pendingCount() { return Object.values(this.buffers).filter(b => this.dirty(b)).length; },
        get context() {
            const b=this.current;if(!b)return {choices:[],issues:[],nextGroups:[],toolbar:{},syntax:{statements:[],spans:[]},learning:{},line:1};
            const selection=b.caret??[projectWritingSource(b.source).text.length,projectWritingSource(b.source).text.length];
            const key=[b.id,b.source,b.sequence,...selection,this.references?.fingerprint,this.choiceMode,choiceGeneration];
            if(!analysisCache||key.some((v,i)=>v!==analysisCache.key[i])) {
                const value=ruleWritingContext({kind:b.kind,source:b.source,editorSelection:selection,references:this.references,picker:this.choiceMode});
                const stamp=action=>({...action,draftId:b.id,source:b.source,sequence:b.sequence,selection:[...selection],fingerprint:this.references?.fingerprint??null,generation:choiceGeneration});
                value.choices=value.choices.map(stamp);value.nextGroups=value.nextGroups.map(group=>({...group,actions:group.actions.map(stamp)}));
                analysisCache={key,value};
            }
            return analysisCache.value;
        },
        get logic() {return this.current?ruleWritingLogic({source:this.current.source,syntax:this.context.syntax,references:this.references,activeByteOffset:this.context.activeByte}):null;},
        get activeLearningAction() {
            const action=this.learningAction,b=this.current;if(!action||!b)return null;
            const selection=b.caret??[projectWritingSource(b.source).text.length,projectWritingSource(b.source).text.length];
            return action.draftId===b.id&&action.source===b.source&&action.sequence===b.sequence&&action.fingerprint===(this.references?.fingerprint??null)&&action.selection?.[0]===selection[0]&&action.selection?.[1]===selection[1]?action:null;
        },
        get workspaceError() {return this.errorContext?.workspace?this.error:'';},
        get activeError() {return this.current?.error?.message??'';},
        get offDraftFailures() {
            const failures={...this.unopenedFailures};
            for(const b of Object.values(this.buffers)){if(b.error)failures[b.id]=b.error;}
            return Object.entries(failures).filter(([id])=>id!==this.currentId).map(([id,error])=>({draftId:id,label:this.buffers[id]?.label||this.listedDrafts[id]?.label||id,operation:error.operation,message:error.message,unknown:Boolean(this.blocked[id]||this.buffers[id]?.unknown)}));
        },
        get activeNotice() {return !this.noticeOwner||this.noticeOwner===this.currentId?this.notice:'';},
        showNotice(message,owner=null) {this.notice=message;this.noticeOwner=owner;},
        get feedbackIssues() {
            const b=this.current;if(!b)return [];
            const issues=[...this.context.issues];
            if(b.error)issues.unshift({code:'operation',severity:!b.unknown&&!b.conflict&&Number.isSafeInteger(b.error.attempt?.sequence)&&b.error.attempt.sequence!==b.sequence?'previous-attempt':b.unknown?'unknown':b.conflict?'shared-conflict':b.error.errors?.draft||b.error.errors?.rule?'saved-rule-conflict':'failure',message:b.error.message,operation:b.error.operation,draftId:b.id});
            if(b.pendingText!==null&&!b.error)issues.unshift({code:'pending',severity:'incomplete',message:this.t('ambiguous_edit','Keep pending writing or undo the ambiguous edit.')});
            return issues;
        },
        async refreshReferences() {
            const generation=++referenceGeneration;this.referencesLoading=true;
            try {const value=await this.send('references',{});if(destroyed||generation!==referenceGeneration)return false;this.references=value;this.referenceError='';return true;}
            catch(error){if(!destroyed&&generation===referenceGeneration)this.referenceError=error.message;return false;}
            finally {if(!destroyed&&generation===referenceGeneration)this.referencesLoading=false;}
        },
        rememberEditor(element=null) {
            const b=this.current;if(!b)return null;
            element??=this.$refs?.source;if(!element||!element.isConnected)return null;
            const caret=b.caret??[projectWritingSource(b.source).text.length,projectWritingSource(b.source).text.length];
            if(caret[0]!==element.selectionStart||caret[1]!==element.selectionEnd)choiceGeneration++;
            b.caret=[element.selectionStart,element.selectionEnd];
            this.editorAnchor={element,draftId:b.id,source:b.source,sequence:b.sequence,fingerprint:this.references?.fingerprint??null,generation:choiceGeneration,selection:[...b.caret]};
            return this.editorAnchor;
        },
        sourceSelection(event) {if(!this.composing)this.rememberEditor(event.target);},
        openChoices(mode=null) {
            if(mode==='refresh')return this.refreshReferences();
            if(mode==='text'){this.openTextHelper();return;}
            if(!this.current||this.current.pendingText!==null||this.composing||this.current.refreshing)return false;
            this.rememberEditor();this.choiceMode=mode;this.choicesOpen=true;this.choiceIndex=0;this.learningAction=null;choiceGeneration++;return true;
        },
        closeChoices(restore=true) {
            const anchor=this.editorAnchor;this.choicesOpen=false;this.choiceMode=null;choiceGeneration++;resizeCleanup?.();
            if(restore&&anchor?.element.isConnected&&this.currentId===anchor.draftId)anchor.element.focus();
        },
        applyWritingAction(action) {
            const b=this.current,anchor=this.editorAnchor;
            if(!action||!b||b.pendingText!==null||this.composing||b.refreshing)return false;
            if(action.generation!==choiceGeneration||action.draftId!==b.id||action.source!==b.source||action.sequence!==b.sequence||action.fingerprint!==(this.references?.fingerprint??null)) {this.showNotice(this.t('choice_changed','Writing or references changed. Choose again.'),b.id);return false;}
            if(action.open)return this.openChoices(action.open);
            if(!anchor?.element.isConnected||anchor.draftId!==b.id)return false;
            const projection=projectWritingSource(b.source);
            if(anchor.element.value!==projection.text||projection.rawOffset(action.from)===null||projection.rawOffset(action.to)===null||action.from>action.to)return false;
            let insertText=action.insertText;
            if(action.category==='Group'&&insertText==='}') {
                const before=projection.text.slice(0,action.from).split('\n').at(-1),after=projection.text.slice(action.to).split('\n')[0];
                insertText=(before.trim()?'\n':'')+'}'+(after.trim()?'\n':'');
            }
            const text=projection.text.slice(0,action.from)+insertText+projection.text.slice(action.to),position=action.from+insertText.length,selection=[position,position];
            if(!this.changeSource(text,{start:action.from,end:action.to,inputType:'insertReplacementText'},selection))return false;
            const sequence=b.sequence,element=anchor.element;this.syncEditor(selection,element);this.textOpen=false;this.closeChoices(false);const paintGeneration=choiceGeneration;
            this.$nextTick?.(()=>{if(!destroyed&&element.isConnected&&this.currentId===b.id&&b.sequence===sequence&&choiceGeneration===paintGeneration&&b.caret?.[0]===selection[0]&&b.caret?.[1]===selection[1]&&!this.composing){this.syncEditor(selection,element);element.focus();this.fitEditor(element);}});
            return true;
        },
        insertLiteral(text,category='Condition',savedAnchor=null) {
            const b=this.current,anchor=savedAnchor??this.editorAnchor??this.rememberEditor();if(!b||!anchor)return false;
            return this.applyWritingAction({label:text,insertText:text,category,from:anchor.selection[0],to:anchor.selection[1],draftId:anchor.draftId,source:anchor.source,sequence:anchor.sequence,fingerprint:anchor.fingerprint,generation:anchor.generation??choiceGeneration});
        },
        closeConditionGroup() {
            if(!this.context.toolbar.closeGroup)return false;
            return this.insertLiteral('}','Group');
        },
        helperEnabled(group) {
            const b=this.current,c=this.context;if(!b||b.pendingText!==null||b.refreshing||this.composing||c.protectedText)return false;
            if(group==='compare')return c.phase==='operator';
            if(group==='condition')return c.phase==='command'&&c.canStartConditions||c.phase==='attribute'&&(c.groupDepth>0||['/when','/and','/or'].includes(c.command));
            if(group==='group')return c.groupDepth<2&&(c.phase==='command'&&c.canStartConditions||c.phase==='attribute'&&(c.groupDepth>0||c.command==='/when'));
            if(group==='join')return c.atLineEnd&&c.syntax.statements.flatMap(node=>node.kind==='group'?node.children:[node]).some(node=>node.kind==='condition'&&node.end===c.activeByte)&&(c.groupDepth>0||Boolean(c.syntax.context?.flat))&&!c.syntax.statements.some(node=>node.start<c.activeByte&&!['condition','group'].includes(node.kind));
            if(group==='mapping'&&!this.helperItems(group).length)return false;
            return c.groupDepth===0&&(c.phase==='command'||c.atLineEnd&&c.syntax.statements.some(node=>node.end===c.activeByte));
        },
        helperItems(group) {
            const c=this.context,b=this.current;
            if(group==='compare'&&b) return ruleWritingContext({kind:b.kind,source:b.source,editorSelection:b.caret,references:this.references,picker:'operators'}).choices.map(action=>({label:action.label,text:action.insertText,action:{...action,draftId:b.id,source:b.source,sequence:b.sequence,fingerprint:this.references?.fingerprint??null,generation:choiceGeneration}}));
            if(group==='mapping'&&b) {
                const text=projectWritingSource(b.source).text;
                return ruleWritingContext({kind:b.kind,source:b.source,editorSelection:b.caret,references:this.references,picker:'commands'}).choices.filter(action=>action.category==='Mapping').flatMap(action=>action.insertText.trim()==='/other'?['disable','hide'].map(behavior=>({...action,label:`Others ${behavior}`,insertText:`/other ${behavior}`})):[action]).map(action=>{
                    const prefix=action.from===action.to&&text.slice(0,action.from).split('\n').at(-1).trim()?'\n':'';
                    const proposal={...action,insertText:prefix+action.insertText,draftId:b.id,source:b.source,sequence:b.sequence,fingerprint:this.references?.fingerprint??null,generation:choiceGeneration};
                    return {label:proposal.label,text:proposal.insertText,action:proposal};
                });
            }
            const entries={condition:c.groupDepth>0||['/when','/and','/or'].includes(c.command)?[['Attribute condition','@'],['Product field condition','$']]:[['When','/when ']],group:c.groupDepth?[['All conditions','all {\n  \n}'],['Any condition','any {\n  \n}']]:[['All conditions',(c.command==='/when'?'all':'/when all')+' {\n  \n}'],['Any conditions',(c.command==='/when'?'all':'/when all')+' {\n  any {\n    \n  }\n}']],compare:[['Equals','='],['Not equals','!='],['Any of','in'],['None of','not-in'],...(c.toolbar.products?[['Contains text','contains']]:[])],join:c.groupDepth?[['Another condition','\n  ']]:c.syntax.context?.flat==='Any'?[['Or','\n/or ']]:c.syntax.context?.flat==='And'?[['And','\n/and ']]:[['And','\n/and '],['Or','\n/or ']],effects:['allow','exclude','hide','disable','set-label','set-display-value','set-hint'].map(key=>[key.replaceAll('-',' '),`\n/${key} `])};
            return (entries[group]??[]).map(([label,text])=>({label,text}));
        },
        openTextHelper() {this.textAnchor=this.rememberEditor();this.textOpen=!this.textOpen;},
        openExamples() {this.examplesAnchor=this.rememberEditor();this.examplesOpen=!this.examplesOpen;},
        insertText() {
            const text=this.textMode==='block'?'[['+this.textValue.replaceAll('\\','\\\\').replaceAll(']]','\\]]')+']]':JSON.stringify(this.textValue);
            return this.insertLiteral(text,'Text',this.textAnchor);
        },
        get exampleSource() {
            const a=this.references?.attributes?.find(x=>x.values.some(v=>v.semantic_id)),b=this.references?.attributes?.find(x=>x!==a&&x.values.some(v=>v.semantic_id));
            if(!a||!b)return this.t('references_for_examples','Load two included Attributes with semantic Values to see a current-reference example.');
            const av=a.values.find(v=>v.semantic_id).semantic_id,bv=b.values.find(v=>v.semantic_id).semantic_id;
            return this.current?.kind==='Mapping'?`/when @${a.key} = #${av}\n/pair @${a.key} -> @${b.key}\n/case #${av} -> #${bv}\n/other disable`:`/when all {\n  @${a.key} = #${av}\n}\n/allow @${b.key} #${bv}\n/set-hint @${b.key} [[Choose the matching specification.]]`;
        },
        insertExample() {return this.insertLiteral(this.exampleSource,'Mapping',this.examplesAnchor);},
        chooseKey(event) {
            if(!this.choicesOpen||this.composing||event.isComposing)return false;
            const choices=this.context.choices;if(!choices.length&&event.key!=='Escape')return false;
            if(event.key==='Escape'){event.preventDefault();this.closeChoices();return true;}
            if(['ArrowUp','ArrowDown'].includes(event.key)){event.preventDefault();this.choiceIndex=(this.choiceIndex+(event.key==='ArrowUp'?-1:1)+choices.length)%choices.length;this.learningAction=choices[this.choiceIndex];return true;}
            if(['Enter','Tab'].includes(event.key)){event.preventDefault();this.applyWritingAction(choices[Math.min(this.choiceIndex,choices.length-1)]);return true;}
            return false;
        },
        get topPanes() {return ['editor',...(this.choicesOpen?['choices']:[]),...(this.writingDisplay.value.visible.feedback?['feedback']:[])];},
        get paneWeights() {const panes=this.topPanes;return this.writingDisplay.value.ratios[panes.join('|')]??(panes.length===3?[50,25,25]:panes.length===2?[60,40]:[100]);},
        get paneGridStyle() {return this.writingDisplay.value.layout==='stacked'?'grid-template-columns:minmax(0,1fr)':`grid-template-columns:${this.topPanes.map((key,index)=>`${index?'8px ':''}minmax(${key==='editor'?20:14}rem,${this.paneWeights[index]}fr)`).join(' ')}`;},
        get display() {return this.tab==='data'?this.dataDisplay:this.writingDisplay;},
        setDisplay(screen,patch) {const element=this.current?this.$refs?.source:null;const profile=screen==='data'?this.dataDisplay:this.writingDisplay;profile.patch(patch);this.$nextTick?.(()=>{if(element?.isConnected)this.fitEditor(element);});},
        closePane(key) {resizeCleanup?.();this.setDisplay('writing',{visible:{[key]:false}});},
        resetDisplay() {this.display.reset();const element=this.current?this.$refs?.source:null;this.$nextTick?.(()=>this.fitEditor(element));},
        moveSection(key,delta) {this.display.move(key,delta);},
        get palette() {return writingPalette(this.display.value.colors);},
        paletteStyle(screen) {const p=screen==='data'?this.dataDisplay.value:this.writingDisplay.value;return Object.entries(writingPalette(p.colors)).map(([key,v])=>`--rw-${key}:${v.background};--rw-${key}-fg:${v.foreground}`).join(';')+`;--rw-light-bg:${p.backgroundLight};--rw-dark-bg:${p.backgroundDark};--rw-light-ink:${writingForeground(p.backgroundLight)};--rw-dark-ink:${writingForeground(p.backgroundDark)};--rw-code-size:${p.font}px`+['source','allowed','text'].map(key=>`;--rw-${key}-light-ink:${writingForeground(p.backgroundLight,p.colors[key])};--rw-${key}-dark-ink:${writingForeground(p.backgroundDark,p.colors[key])}`).join('');},
        adjustPane(index,delta) {
            const weights=[...this.paneWeights],a=weights[index],b=weights[index+1];if(b===undefined)return;
            const move=Math.max(10-a,Math.min(b-10,delta));weights[index]+=move;weights[index+1]-=move;this.writingDisplay.patch({ratios:{[this.topPanes.join('|')]:weights}});
        },
        resizeKey(event,index) {if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();this.adjustPane(index,(event.key==='ArrowLeft'?-1:1)*(event.shiftKey?6:2));},
        startResize(event,index) {
            if(event.button!==0)return;resizeCleanup?.();const handle=event.currentTarget,grid=handle.closest('.rw-top-grid'),width=grid?.getBoundingClientRect().width;if(!width)return;
            const combo=this.topPanes.join('|');let previous=event.clientX;handle.setPointerCapture(event.pointerId);
            const move=e=>{if(this.topPanes.join('|')!==combo){resizeCleanup?.();return;}this.adjustPane(index,(e.clientX-previous)/width*100);previous=e.clientX;};
            const stop=()=>{handle.removeEventListener('pointermove',move);handle.removeEventListener('pointerup',stop);handle.removeEventListener('pointercancel',stop);if(handle.hasPointerCapture?.(event.pointerId))handle.releasePointerCapture(event.pointerId);resizeCleanup=null;};
            resizeCleanup=stop;handle.addEventListener('pointermove',move);handle.addEventListener('pointerup',stop);handle.addEventListener('pointercancel',stop);
        },
        fitEditor(element=this.$refs?.source) {
            if(!element?.isConnected||!element.style)return;
            if(this.writingDisplay.value.editor.autoHeight){element.style.height='auto';element.style.height=Math.max(180,element.scrollHeight+2)+'px';}
            else element.style.height=this.writingDisplay.value.editor.height+'px';
        },
        saveEditorHeight(event) {if(!this.writingDisplay.value.editor.autoHeight)this.writingDisplay.patch({editor:{height:Math.round(event.target.getBoundingClientRect().height)}});},
        sourceScroll(event) {const mirror=event.target.parentElement?.querySelector('.rw-source-mirror');if(mirror){mirror.scrollTop=event.target.scrollTop;mirror.scrollLeft=event.target.scrollLeft;}},
        get highlightedLines() {
            if(!this.current)return [];
            const projection=projectWritingSource(this.current.pendingText??this.current.source),text=projection.text;
            if(this.current.pendingText!==null)return text.split('\n').map((line,index)=>({number:index+1,parts:[{text:line,type:'plain'}]}));
            const segments=[];let previous=0;
            for(const span of this.context.syntax.spans??[]) {
                const a=ruleWritingEditorOffset(this.current.source,span.start),b=ruleWritingEditorOffset(this.current.source,span.end);if(a===null||b===null)continue;
                const from=projection.editorOffset(a),to=projection.editorOffset(b);if(from===null||to===null)continue;
                if(from>previous)segments.push({text:text.slice(previous,from),type:'plain'});segments.push({text:text.slice(from,to),type:span.type});previous=to;
            }
            if(previous<text.length)segments.push({text:text.slice(previous),type:'plain'});
            const lines=[[]];for(const segment of segments){const parts=segment.text.split('\n');parts.forEach((part,index)=>{if(index)lines.push([]);lines.at(-1).push({text:part,type:segment.type});});}
            return lines.map((parts,index)=>({number:index+1,parts:parts.length?parts:[{text:' ',type:'plain'}]}));
        },
        get inspectionSections() {return displayInspectionRecords(this.inspection,this.dataDisplay.value);},
        recordCells(row) {return row.cells.filter(c=>(this.dataDisplay.value.technical||!/(^id$|_id$|_at$|fingerprint|revision)/.test(c.name))&&(this.dataDisplay.value.semantic||!/(label|semantic|display_value|hint|key)/.test(c.name)));},
        t(key, fallback) { return config.labels?.[key] ?? fallback; },
        dirty(b) { return Boolean(b && (b.pendingText !== null || !same(b, b.ack) || b.sequence !== b.ack?.sequence)); },
        hasUnsent() { return Boolean(this.composing || Object.values(this.buffers).some(b => this.dirty(b) || b.unknown)); },
        async init() {
            if (typeof window !== 'undefined') {
                const unload = event => { if (this.hasUnsent()) { event.preventDefault(); event.returnValue = ''; } };
                const navigate = event => { if (!event.defaultPrevented && this.hasUnsent() && !window.confirm(this.t('leave', 'Leave with unsent writing? Copy it before leaving.'))) event.preventDefault(); };
                window.addEventListener('beforeunload', unload); document.addEventListener('livewire:navigate', navigate);
                removeGuard = () => { window.removeEventListener('beforeunload', unload); document.removeEventListener('livewire:navigate', navigate); };
            }
            if(config.owner)this.refreshReferences();
            await this.refreshList();
            if (config.requestedRule && !destroyed) await this.openRule(config.requestedRule);
        },
        destroy() { destroyed = true; referenceGeneration++; choiceGeneration++; resizeCleanup?.(); removeGuard?.(); for (const id of timers.keys()) stopTimer(id); },
        async send(operation, input) {
            if (destroyed) throw new Error('Writing workspace closed.');
            let result;
            try { result = await request(operation, input); } catch (error) { throw error instanceof RequestFailure ? error : new RequestFailure({ type: 'unknown', message: this.t('outcome_unknown', 'The outcome is unknown. Keep your writing and inspect current records.') }); }
            if (destroyed) throw new Error('Writing workspace closed.');
            if (!result?.ok) throw new RequestFailure(result ?? { type: 'unknown' });
            return result.data;
        },
        enqueue(work, buffer = null, operation = '') {
            pending++; this.busy = true;
            const attempt=buffer?{draftId:buffer.id,sequence:buffer.sequence}:null;
            const next = tail.then(async () => { if (destroyed) return false; try { return await work(); } catch (error) { return this.problem(buffer, error, operation, attempt); } finally { pending--; if (!destroyed) this.busy = pending > 0; } });
            tail = next.catch(() => {}); return next;
        },
        problem(b, error, operation, attempt=null) {
            if (destroyed) return false;
            const result = error.result ?? { type: 'unknown' };
            this.error = error.message;
            const owner={draftId:b?.id??null,sequence:b?.sequence??null,...attempt,...error.attempt};
            this.errorContext={draftIds:owner.draftIds??(owner.draftId?[owner.draftId]:[]),operation,attempt:owner.sequence,workspace:!b&&!owner.draftId||[401,403,419].includes(result.status)};
            if (operation === 'open-rule' && result.errors?.rule) this.failedRuleId = error.ruleId ?? null;
            if(!b&&owner.draftId)this.unopenedFailures[owner.draftId]={...result,message:error.message,operation,attempt:owner};
            if (b && (!error.draftId || error.draftId === b.id)) {
                b.error = { ...result, message: error.message, operation, attempt:owner };
                if (operation === 'refresh-sections') { b.capture = null; b.captureAttempt = null; }
                if (result.errors?.draft_revision || result.errors?.[`drafts.${b.id}.revision`]) { b.conflict = true; b.capture = null; b.captureAttempt = null; stopTimer(b.id); }
                if (result.type === 'unknown' || result.type === 'failure' && !result.rolledBack) { b.unknown = true; b.capture = null; b.captureAttempt = null; this.blocked[b.id] = true; stopTimer(b.id); }
            }
            return false;
        },
        acceptOpened(result, replace = false, focus = true) {
            if (destroyed) return null;
            const d = result.draft, previous = this.buffers[d.id];
            if (previous && !replace) {
                if (previous.revision !== d.revision) { previous.latest = d; previous.conflict = true; }
            } else {
                const failure=replace?null:this.unopenedFailures[d.id]??null;
                this.buffers[d.id] = { ...d, sequence: result.acknowledgement?.sequence ?? 0, ack: { ...content(d), sequence: result.acknowledgement?.sequence ?? 0 }, history: writingHistory(d.source), diagnostics: parseRuleWriting(d.source).diagnostics, error: failure, unknown: !replace&&Boolean(this.blocked[d.id]), conflict: Boolean(failure?.errors?.draft_revision||failure?.errors?.[`drafts.${d.id}.revision`]), latest: null, capture: null, refreshing: false, paused: false, pendingText: null, caret: null };
                delete this.unopenedFailures[d.id];
                if(replace)delete this.blocked[d.id];
            }
            if (focus) this.focusDraft(d.id);
            return this.buffers[d.id];
        },
        focusDraft(id) {
            const previous = this.current, sourceElement = previous ? this.$refs?.source : null;
            if (previous) { previous.capture = null; previous.captureAttempt = null; }
            if(this.currentId!==id)this.learningAction=null;
            this.currentId = id; this.tab = 'writing'; this.closeChoices(false); this.textOpen=false; this.examplesOpen=false; this.editorAnchor=null;
            this.$nextTick?.(() => { if (this.currentId === id) this.syncEditor(null, sourceElement); });
        },
        syncEditor(selection = null, sourceElement = null) {
            const b = this.current; if (!b) return;
            const element = sourceElement ?? this.$refs?.source;
            if (!element || sourceElement && !sourceElement.isConnected) return;
            const text = b.pendingText ?? projectWritingSource(b.source).text;
            if (element.value !== text) element.value = text;
            if(element.style)this.fitEditor(element);
            const range = selection ?? b.caret;
            if (range) element.setSelectionRange(...range);
        },
        changeSource(text, context = {}, caret = null) {
            const b = this.current;
            if (!b || b.refreshing) return false;
            b.capture = null;
            const result = b.history.edit(text, context, caret);
            if (!result.ok) { b.pendingText = text; b.error = { type: 'ambiguous_edit', message: this.t('ambiguous_edit', 'The edit location is ambiguous. Copy the pending text or undo this edit.') }; stopTimer(b.id); return false; }
            b.pendingText = null; b.caret = caret;
            if (result.patch) { b.source = result.source; this.changed(b); }
            return true;
        },
        changeMetadata(key, value) {
            const b = this.current;
            if (!b || b.refreshing || !['label', 'description'].includes(key) || b[key] === value) return;
            b[key] = value; this.changed(b);
        },
        changed(b) { b.sequence++; b.capture = null; b.captureAttempt = null; b.diagnostics = parseRuleWriting(b.source).diagnostics; if (!b.conflict && !b.unknown) b.error = null; this.schedule(b); },
        beforeInput(event) {
            this.historyHandled = null;
            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                if (event.cancelable) { event.preventDefault(); this.history(event.inputType === 'historyUndo' ? 'undo' : 'redo'); this.historyHandled = event.inputType; }
                return;
            }
            this.editContext = { start: event.target.selectionStart, end: event.target.selectionEnd, inputType: event.inputType, data: event.data };
        },
        input(event) {
            if (event.inputType === 'historyUndo' || event.inputType === 'historyRedo') {
                if (this.historyHandled !== event.inputType) this.history(event.inputType === 'historyUndo' ? 'undo' : 'redo');
                this.historyHandled = null; this.syncEditor(); return;
            }
            this.historyHandled = null;
            if (this.composing || event.isComposing) return;
            this.changeSource(event.target.value, this.editContext ?? {}, [event.target.selectionStart, event.target.selectionEnd]); this.editContext = null;
            this.rememberEditor(event.target);
            if(!this.context.protectedText&&(/[\/@#$]$/.test(event.target.value.slice(0,event.target.selectionStart))||this.choicesOpen))this.openChoices();
        },
        compositionStart(event) { this.closeChoices(false); this.composing = { id: this.currentId, start: event.target.selectionStart, end: event.target.selectionEnd, inputType: 'insertCompositionText' }; stopTimer(this.currentId); },
        compositionEnd(event) { const context = this.composing; this.composing = null; if (context?.id === this.currentId) this.changeSource(event.target.value, context, [event.target.selectionStart, event.target.selectionEnd]); this.editContext = null; },
        keydown(event) { if(this.chooseKey(event))return; if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z' && !event.isComposing) { event.preventDefault(); this.history(event.shiftKey ? 'redo' : 'undo'); } },
        history(direction) {
            const b = this.current; if (!b || b.refreshing || this.composing) return;
            if (b.pendingText !== null) { b.pendingText = null; b.error = null; this.syncEditor(); return; }
            const result = b.history[direction](); if (!result) return;
            b.source = result.source; b.caret = result.selection; this.changed(b); this.syncEditor(result.selection);
        },
        schedule(b) {
            if (destroyed || b.paused || b.conflict || b.unknown || b.pendingText !== null || this.composing?.id === b.id) return;
            const entry = timers.get(b.id) ?? { maximum: setTimer(() => this.flush(b.id), 3000) };
            clearTimer(entry.debounce); entry.debounce = setTimer(() => this.flush(b.id), 700);
            entry.maximum?.unref?.(); entry.debounce?.unref?.(); timers.set(b.id, entry);
        },
        snapshot(b) { return { ...content(b), sequence: b.sequence }; },
        async persist(b, snapshot = this.snapshot(b)) {
            if (b.conflict || b.unknown || b.pendingText !== null || ruleWritingByteOffset(snapshot.source, 0) === null) throw new RequestFailure({ type: 'validation', message: 'Resolve this buffer before saving its draft.' });
            if (same(snapshot, b.ack) && snapshot.sequence === b.ack.sequence) return b.revision;
            try {
                const result = await this.send('update-draft', { id: b.id, expected_revision: b.revision, authoring: content(snapshot), sequence: snapshot.sequence });
                this.acceptAck(b, result, snapshot); return b.revision;
            } catch (error) { error.draftId = b.id; error.attempt={draftId:b.id,sequence:snapshot.sequence}; throw error; }
        },
        acceptAck(b, result, snapshot) {
            const ack = result.acknowledgement;
            if (result.draft?.id !== b.id || !ack || result.draft.revision !== ack.revision || !same(result.draft, ack) || ack.sequence !== snapshot.sequence || !safe(ack.revision) || ack.revision < 1 || !same(ack, snapshot)) throw new RequestFailure({ type: 'unknown', message: 'The acknowledgement does not match the submitted writing.' });
            if (b.capture && (ack.revision !== b.revision || ack.sequence !== b.ack.sequence)) { b.capture = null; b.captureAttempt = null; }
            b.revision = ack.revision; b.ack = { ...content(ack), sequence: ack.sequence }; b.origin_rule_id = result.draft.origin_rule_id; b.error = null;
        },
        flush(id = this.currentId) {
            const b = this.buffers[id]; stopTimer(id);
            if (!b || b.paused || b.conflict || b.unknown || this.composing?.id === id) return Promise.resolve(false);
            return this.enqueue(async () => { await this.persist(b); if (this.dirty(b)) this.schedule(b); return true; }, b, 'update-draft');
        },
        refreshList() { return this.enqueue(async () => { const data = await this.send('draft-summaries', { page: this.page, search: this.search }); this.drafts = data.rows; for (const row of data.rows) this.listedDrafts[row.id] = { ...row }; this.hasMore = data.has_more; return true; }); },
        openDraft(id) {
            if (this.composing) return Promise.resolve(false);
            if (this.buffers[id]) { this.focusDraft(id); return Promise.resolve(true); }
            return this.enqueue(async () => { this.acceptOpened(await this.send('open-draft', { id })); return true; }, null, 'open-draft');
        },
        createDraft(kind, authoring = {}) {
            if (this.composing) return Promise.resolve(false);
            const id = (dependencies.uuid ?? uuid)();
            return this.enqueue(async () => { this.acceptOpened(await this.send('create-draft', { id, authoring: { kind, source: '', label: null, description: null, ...authoring }, sequence: 0 })); return true; }, null, 'create-draft');
        },
        duplicate() { const b = this.current; if (!b || b.pendingText !== null) return Promise.resolve(false); return this.createDraft(b.kind, content(b)); },
        openRule(ruleId) {
            if (this.composing) return Promise.resolve(false);
            const newId = (dependencies.uuid ?? uuid)();
            return this.enqueue(async () => { try { this.acceptOpened(await this.send('open-rule', { rule_id: String(ruleId), new_draft_id: newId })); this.failedRuleId = null; this.picker = false; return true; } catch (error) { error.ruleId = String(ruleId); throw error; } }, null, 'open-rule');
        },
        showRules() { this.picker = true; return this.enqueue(async () => { const data = await this.send('saved-rule-summaries', { page: this.rulePage, search: this.ruleSearch }); this.rules = data.rows; this.ruleMore = data.has_more; }); },
        saveCurrent() { return this.saveSelected([this.currentId]); },
        saveSelected(ids = [...this.selected]) {
            if (!ids.length || ids.some(id => !id || this.blocked[id] || this.buffers[id]?.unknown || this.buffers[id]?.pendingText !== null && this.buffers[id]?.pendingText !== undefined || this.composing?.id === id)) return Promise.resolve(false);
            const snapshots = new Map(ids.map(id => [id, this.buffers[id] ? this.snapshot(this.buffers[id]) : null]));
            const selectedRevisions = new Map(ids.map(id => [id, this.selectedDrafts[id]?.revision]));
            for (const id of ids) { stopTimer(id); if (this.buffers[id]) this.buffers[id].paused = true; }
            return this.enqueue(async () => {
                let result;
                try {
                    const selection = [];
                    for (const id of ids) {
                        const b = this.buffers[id], revision = selectedRevisions.get(id);
                        if (!b && (!safe(revision) || revision < 1)) throw new RequestFailure({ type: 'validation', message: 'Select this draft again before saving.' });
                        if (b) await this.persist(b, snapshots.get(id));
                        selection.push({ id, expected_revision: b?.revision ?? revision, sequence: snapshots.get(id)?.sequence ?? 0 });
                    }
                    result = await this.send('save-selected', { selection });
                    if (typeof result.receipt_json !== 'string' || result.items?.length !== ids.length || new Set(result.items.map(item => item.draft?.id)).size !== ids.length || result.items.some(item => !ids.includes(item.draft?.id))) throw new RequestFailure({ type: 'unknown', message: 'Incomplete save acknowledgement.' });
                    for (const item of result.items) {
                        const snapshot = snapshots.get(item.draft.id);
                        if (snapshot && (!same(item.acknowledgement, snapshot) || item.acknowledgement.sequence !== snapshot.sequence)) throw new RequestFailure({ type: 'unknown', message: 'Save acknowledgement does not match this writing.' });
                    }
                    this.receipt = result.receipt_json;
                    for (const item of result.items) {
                        const b = this.buffers[item.draft.id] ?? this.acceptOpened(item, false, false);
                        this.acceptAck(b, item, snapshots.get(b.id) ?? { ...content(item.acknowledgement), sequence: 0 });
                    }
                    this.showNotice(this.t('saved_rules', 'Selected rules saved.')); this.error = '';
                    if (typeof window !== 'undefined') window.Livewire?.dispatch('configurator-updated');
                    try { const id = ids.includes(this.currentId) ? this.currentId : ids[0]; this.inspection = await this.send('inspect-draft', { id }); this.inspectedId = id; } catch { this.showNotice(this.t('saved_refresh_failed', 'Rules saved. Inspection refresh failed; refresh records separately.')); }
                    return true;
                } catch (error) {
                    for (const id of ids) { this.problem(this.buffers[id], error, 'save-selected', {draftIds:ids,draftId:id,sequence:snapshots.get(id)?.sequence??null}); if ((!error.draftId || error.draftId === id) && (error.result?.type === 'unknown' || error.result?.type === 'failure' && !error.result.rolledBack)) this.blocked[id] = true; }
                    return false;
                } finally {
                    for (const id of ids) { const b = this.buffers[id]; if (b) { b.paused = false; const submitted = snapshots.get(id); const later = submitted && (b.sequence !== submitted.sequence || !same(b, submitted)); if (this.dirty(b) && (!b.error || later)) this.schedule(b); } }
                }
            });
        },
        inspect(id = this.currentId) { if (!id) return Promise.resolve(false); return this.enqueue(async () => { this.inspection = await this.send('inspect-draft', { id }); this.inspectedId = id; return true; }, null, 'inspect-draft'); },
        showData() { this.tab = 'data'; if (this.currentId && this.inspectedId !== this.currentId) return this.inspect(); },
        compareShared() { const b = this.current; if (!b) return; return this.enqueue(async () => { b.latest = (await this.send('open-draft', { id: b.id })).draft; return true; }, b, 'open-draft'); },
        loadShared() { const b = this.current; if (!b?.latest || !(dependencies.confirm ?? globalThis.confirm)(this.t('replace_local', 'Replace local writing with the displayed shared draft? Copy your writing first.'))) return; stopTimer(b.id); this.acceptOpened({ draft: b.latest }, true); this.showNotice(this.t('loaded_shared', 'Current shared draft loaded. This is not a receipt for a previous uncertain save.'),b.id); },
        captureLatest() {
            const b = this.current; if (!b?.origin_rule_id || b.unknown || b.conflict || this.composing) return Promise.resolve(false);
            const attempt = ++captureSequence, snapshot = this.snapshot(b); b.captureAttempt = attempt; b.capture = null; stopTimer(b.id);
            return this.enqueue(async () => {
                await this.persist(b, snapshot);
                if (this.dirty(b) || b.captureAttempt !== attempt) { this.showNotice('Writing changed. Save the current draft and refresh again.'); return false; }
                const result = await this.send('capture-latest', { id: b.id, expected_revision: b.revision });
                if (b.captureAttempt !== attempt || this.dirty(b) || b.sequence !== snapshot.sequence || !same(b, snapshot) || result.draft.revision !== b.revision) return false;
                b.capture = { ...result, sequence: b.sequence, revision: b.revision, mine: content(b), sections: ['label', 'conditions', b.kind === 'Mapping' ? 'mapping' : 'effects'] }; return true;
            }, b, 'capture-latest');
        },
        cancelCapture() { if (this.current) { this.current.capture = null; this.current.captureAttempt = null; } },
        refreshSections() {
            const b = this.current, capture = b?.capture;
            if (!capture || b.conflict || b.unknown || this.blocked[b.id] || this.dirty(b) || b.sequence !== capture.sequence || !same(b, capture.mine)) return Promise.resolve(false);
            b.refreshing = true;
            return this.enqueue(async () => {
                try {
                    if (b.capture !== capture || b.conflict || b.unknown || this.blocked[b.id] || this.dirty(b) || b.revision !== capture.revision) return false;
                    const result = await this.send('refresh-sections', { id: b.id, expected_revision: capture.revision, snapshot_json: capture.snapshot_json, sections: [...capture.sections], sequence: b.sequence + 1 });
                    this.acceptOpened(result, true, this.currentId === b.id); this.showNotice(this.t('draft_refreshed', 'Draft sections updated. Save the rule separately when ready.')); return true;
                } finally { b.refreshing = false; }
            }, b, 'refresh-sections');
        },
        diagnosticLine(item) { const offset = ruleWritingEditorOffset(this.current?.source ?? '', item.start ?? 0); return offset === null ? '?' : projectWritingSource(this.current.source.slice(0, offset)).text.split('\n').length; },
        goToDiagnostic(item) { const b = this.current, raw = ruleWritingEditorOffset(b.source, item.start ?? 0); if (raw === null) return; const offset = projectWritingSource(b.source).editorOffset(raw); if (offset !== null) { this.$refs?.source?.focus(); this.$refs?.source?.setSelectionRange(offset, offset); } },
        ruleEditUrl(id = this.current?.origin_rule_id) { if (!id || !config.backUrl) return null; const url = new URL(config.backUrl, globalThis.location?.href ?? 'http://localhost'); url.searchParams.set('rule', id); return url.href; },
        async copy(text) { try { await navigator.clipboard.writeText(text); this.showNotice(this.t('copied', 'Copied.')); } catch { this.error = this.t('copy_unavailable', 'Clipboard is unavailable. Select the source text and copy it manually.');this.errorContext={workspace:true,operation:'copy'}; } },
        draftStatus(b = this.current) { if (!b) return ''; if (b.unknown) return this.t('unknown', 'Outcome unknown'); if (b.conflict) return this.t('conflict', 'Shared draft changed'); if (b.error || b.pendingText !== null) return this.t('retained', 'Local writing retained'); return this.dirty(b) ? this.t('draft_pending', 'Draft not yet saved') : this.t('draft_saved', 'Draft saved'); },
    };
}
