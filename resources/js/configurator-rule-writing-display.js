import {browserProfile} from './admin-profile.js';
const object = v=>v!==null&&typeof v==='object'&&!Array.isArray(v);
const palettes={and:'#0f766e',or:'#7c3aed',source:'#1d4ed8',allowed:'#15803d',excluded:'#b45309',hidden:'#64748b',text:'#9333ea'};
const dataOrder=['rule','conditions','sets','effects','references','source'];
const hex = value => typeof value==='string'&&/^#[0-9a-f]{6}$/i.test(value);
const choose=(v,allowed,fallback)=>allowed.includes(v)?v:fallback;
const number=(v,min,max,fallback)=>Number.isFinite(v)&&v>=min&&v<=max?v:fallback;
const order=(value,keys)=>[...new Set([...(Array.isArray(value)?value:[]).filter(v=>keys.includes(v)),...keys])];
export function normalizeWritingDisplay(input,screen='writing') {
    const v=object(input)?input:{}, data=screen==='data';
    const colors=Object.fromEntries(Object.entries(palettes).map(([k,def])=>[k,hex(v.colors?.[k])?v.colors[k]:def]));
    const visibleKeys=data?dataOrder:['feedback','logic','learning'];
    const visible=Object.fromEntries(visibleKeys.map(k=>[k,typeof v.visible?.[k]==='boolean'?v.visible[k]:true]));
    const collapsed=Object.fromEntries(visibleKeys.map(k=>[k,v.collapsed?.[k]===true]));
    const ratios={};
    for(const [key,weights] of Object.entries(object(v.ratios)?v.ratios:{})) {
        const keys=key.split('|');
        if(keys[0]!=='editor'||keys.length<2||keys.length>3||!keys.every(k=>['editor','choices','feedback'].includes(k))||new Set(keys).size!==keys.length||!Array.isArray(weights)||weights.length!==keys.length||!weights.every(n=>Number.isFinite(n)&&n>=10&&n<=90))continue;
        const sum=weights.reduce((a,b)=>a+b,0);ratios[key]=weights.map(n=>n/sum*100);
    }
    return {theme:choose(v.theme,['inherit','light','dark'],'inherit'),style:choose(v.style,['outline','bands'],'outline'),backgroundLight:hex(v.backgroundLight)?v.backgroundLight:'#ffffff',backgroundDark:hex(v.backgroundDark)?v.backgroundDark:'#111827',colors,visible,collapsed,ratios,layout:choose(v.layout,['columns','stacked'],'columns'),lowerOrder:order(v.lowerOrder,['logic','learning']),order:order(v.order,dataOrder),hideEmpty:v.hideEmpty!==false,technical:v.technical!==false,semantic:v.semantic!==false,wrap:v.wrap!==false,font:number(v.font,11,22,13),editor:{font:number(v.editor?.font,11,22,14),lineNumbers:v.editor?.lineNumbers!==false,highlight:v.editor?.highlight!==false,wrap:v.editor?.wrap!==false,autoHeight:v.editor?.autoHeight!==false,height:number(v.editor?.height,180,1600,400)}};
}
export function ruleWritingDisplay({user,owner,screen,storage}) {
    const profile=typeof user==='string'&&user&&typeof owner==='string'&&owner?browserProfile(user,`rule-writing:${owner}:${screen}`,storage):null;
    return {key:profile?.key??null,value:normalizeWritingDisplay(profile?.read(),screen),patch(patch){const next={...this.value,...patch};for(const key of ['colors','visible','collapsed','editor','ratios']) if(object(patch[key]))next[key]={...this.value[key],...patch[key]};this.value=normalizeWritingDisplay(next,screen);profile?.save(this.value);return this.value;},reset(){profile?.clear();this.value=normalizeWritingDisplay(null,screen);return this.value;},move(key,delta){const name=screen==='data'?'order':'lowerOrder',items=[...this.value[name]],i=items.indexOf(key),j=i+delta;if(i<0||j<0||j>=items.length)return;[items[i],items[j]]=[items[j],items[i]];this.patch({[name]:items});}};
}
export function writingForeground(background,preferred=null) {
    const luminance = color => {
        const rgb=[1,3,5].map(i=>parseInt(color.slice(i,i+2),16)/255).map(c=>c<=0.04045?c/12.92:((c+0.055)/1.055)**2.4);
        return rgb[0]*0.2126+rgb[1]*0.7152+rgb[2]*0.0722;
    };
    const value=luminance(hex(background)?background:'#ffffff');
    const contrast=color=>{const other=luminance(color);return (Math.max(value,other)+0.05)/(Math.min(value,other)+0.05);};
    if(hex(preferred)&&contrast(preferred)>=4.5)return preferred;
    const white=contrast('#ffffff'),dark=contrast('#111827');
    return Math.max(white,dark)<4.5?'#000000':white>dark?'#ffffff':'#111827';
}
export function writingPalette(colors={}) {
    return Object.fromEntries(Object.entries(palettes).map(([key,fallback])=>{
        const background=hex(colors[key])?colors[key]:fallback;
        return [key,{label:{and:'And',or:'Or',source:'Source / condition',allowed:'Allowed',excluded:'Disabled / excluded',hidden:'Hidden',text:'Text changes'}[key],background,foreground:writingForeground(background)}];
    }));
}
export function displayInspectionRecords(inspection,prefs) {
    if(!inspection)return [];
    const buckets=inspection.records??[], mapping={rule:['rule'],conditions:['condition_groups','conditions'],sets:['mapping_sets'],effects:['effects'],references:['condition_references','effect_references','mapping_sources','mapping_targets'],source:[]};
    const known=new Set(Object.values(mapping).flat()), groups=[];
    for(const key of prefs.order) {
        if(!prefs.visible[key])continue;
        const rows=buckets.filter(b=>mapping[key].includes(b.key));
        const blocks=key==='source'?[{key:'draft',label:'Shared draft',json:inspection.draft_json},{key:'capture',label:'Original last Writing capture',json:inspection.capture_json}]:[];
        if(prefs.hideEmpty&&!blocks.length&&!rows.some(b=>b.rows.length))continue;
        groups.push({key,label:{rule:'Rule',conditions:'Conditions',sets:'Mapping sets',effects:'Effects',references:'Reference links',source:'Writing records'}[key],buckets:rows,blocks});
    }
    const remaining=buckets.filter(b=>!known.has(b.key));if(remaining.length)groups.push({key:'other',label:'Other record buckets',buckets:remaining,blocks:[]});
    return groups;
}
