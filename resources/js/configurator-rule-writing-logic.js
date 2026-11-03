import {inspectRuleWritingSyntax} from './configurator-rule-writing-context.js';
const labels = {'allow':'Allow','exclude':'Exclude','hide':'Hide','disable':'Disable','set-label':'Set label','set-display-value':'Display text','set-hint':'Hint'};
export function ruleWritingLogic({source, syntax = null, references, activeByteOffset = null}) {
    syntax ??= inspectRuleWritingSyntax(source);
    const attributes = references?.attributes ?? [];
    const attribute = key => {const candidates=attributes.filter(a=>a.key===key);return candidates.length===1&&!candidates[0].ambiguous ? {...candidates[0],unresolved:false} : {key,label:`@${key}`,values:[],unresolved:true};};
    const value = (key,id) => {const a=attribute(key), choices=a.values.filter(v=>v.semantic_id===id);return !a.unresolved&&choices.length===1&&!choices[0].ambiguous ? {key:id,label:choices[0].label,unresolved:false} : {key:id,label:`#${id}`,unresolved:true};};
    const active = node => activeByteOffset !== null && activeByteOffset >= node.start && activeByteOffset <= node.end;
    const condition = node => ({...node,active:active(node),field:node.source_kind==='SelectionOption'?attribute(node.reference):{key:node.reference,label:references?.product_fields?.find(p=>p.key===node.reference)?.label??node.reference,unresolved:false},comparison:{Equals:'=',NotEquals:'!=',In:'any of',NotIn:'none of',Contains:'contains'}[node.operator],values:node.source_kind==='SelectionOption'?node.values.map(id=>value(node.reference,id)):node.values.map(text=>({key:text,label:text,unresolved:false})),tone:'source'});
    const conditions=[],effects=[],mapping={source:null,target:null,cases:[]};
    for(const node of syntax.statements) {
        if(node.kind==='condition') conditions.push(condition(node));
        else if(node.kind==='group') conditions.push({...node,children:node.children.map(condition),active:active(node)});
        else if(node.kind==='pair') {mapping.source=attribute(node.source);mapping.target=attribute(node.target);}
        else if(node.kind==='case') mapping.cases.push({...node,active:active(node),source:node.source_values.map(id=>value(mapping.source?.key,id)),target:node.target_values.map(id=>value(mapping.target?.key,id)),behavior:'Disable'});
        else if(node.kind==='other') {const row=mapping.cases.at(-1);if(row){row.behavior=node.behavior;row.active ||= active(node);row.end=node.end;}}
        else if(Object.hasOwn(labels,node.kind)) { const effectKind={allow:'AllowOptions',exclude:'ExcludeOptions',hide:node.values.length?'HideOptions':'HideAttribute',disable:'DisableOptions','set-label':'SetLabel','set-display-value':'SetDisplayValue','set-hint':'SetHint'}[node.kind]; const meta=references?.presentation?.effects?.find(effect=>effect.value===effectKind); effects.push({...node,active:active(node),label:meta?.label??labels[node.kind],target:attribute(node.attribute),values:node.values.map(id=>value(node.attribute,id)),scope:node.values.length?'Options':'Attribute',tone:meta?.tone??(node.kind==='allow'?'allowed':node.kind.startsWith('set-')?'text':node.kind==='hide'?'hidden':'excluded')}); }
    }
    return {conditions,mapping,effects,complete:syntax.diagnostics.length===0,diagnostics:syntax.diagnostics};
}
