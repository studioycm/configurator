import {test} from 'node:test';
import assert from 'node:assert/strict';
import {ruleWritingLogic} from '../../resources/js/configurator-rule-writing-logic.js';
const refs={attributes:[{key:'A',label:'Body',values:[{semantic_id:'iron',label:'Iron'},{semantic_id:'steel',label:'Steel'}]},{key:'B',label:'Bolts',values:[{semantic_id:'steel',label:'Steel'},{semantic_id:'ss',label:'Stainless'}]}],product_fields:[{key:'Connection Type',label:'Connection Type'}]};
test('Mapping renders complete prefix, grouped conditions, two distinct cases and per-case other behavior',()=>{
 const source='/when all {\n $"Connection Type" = "Flange"\n any {\n @A = #iron\n @A = #steel\n }\n}\n/pair @A -> @B\n/case #iron -> #steel | #ss\n/other hide\n/case #steel -> #ss\n/other disable\n/case #';
 const view=ruleWritingLogic({source,references:refs,activeByteOffset:source.indexOf('/case #iron')+4});
 assert.equal(view.mapping.cases.length,2);assert.equal(view.mapping.source.label,'Body');assert.equal(view.mapping.cases[0].behavior,'Hide');assert.equal(view.mapping.cases[1].behavior,'Disable');assert.equal(view.mapping.cases[0].active,true);assert.equal(view.conditions[1].operator,'Any');assert.equal(view.complete,false);
});
test('Advanced displays every effect command, scopes and literal multiline text without execution',()=>{
 const source='/allow @A #iron\n/exclude @A #steel\n/hide @B\n/hide @A #steel\n/disable @B #ss\n/set-label @A "New Body"\n/set-display-value @B #ss [[Two\nlines /hide @A]]\n/set-hint @A [[Hint]]';
 const v=ruleWritingLogic({source,references:refs,activeByteOffset:0});assert.equal(v.effects.length,8);assert.deepEqual(v.effects.map(x=>x.kind),['allow','exclude','hide','hide','disable','set-label','set-display-value','set-hint']);assert.equal(v.effects[2].scope,'Attribute');assert.equal(v.effects[6].text,'Two\nlines /hide @A');assert.equal(v.effects[6].scope,'Options');
});
test('unresolved or duplicate references are displayed honestly without choosing an arbitrary record',()=>{
 const v=ruleWritingLogic({source:'/hide @missing #none',references:refs});assert.equal(v.effects[0].target.unresolved,true);assert.equal(v.effects[0].values[0].unresolved,true);
 const duplicate=ruleWritingLogic({source:'/hide @A',references:{attributes:[refs.attributes[0],refs.attributes[0]]}});assert.equal(duplicate.effects[0].target.unresolved,true);
});

test('partial flat Or retains its known Any grouping, exact spans and completed outcomes',async()=>{
 const {parseRuleWriting}=await import('../../resources/js/configurator-rule-writing-model.js');
 const prefix='/when @A = #iron\n/or @A = #steel\n/hide @B',source=prefix+'\n/set-hint @B "';
 const strict=parseRuleWriting(source),partial=parseRuleWriting.inspectSyntax(source),view=ruleWritingLogic({source,references:refs});
 assert.deepEqual(strict.statements,[]);assert(strict.diagnostics.length);assert(partial.diagnostics.length);
 assert.equal(partial.statements[0].kind,'group');assert.equal(partial.statements[0].operator,'Any');assert.equal(partial.statements[0].children.length,2);
 assert.equal(partial.statements[0].start,0);assert.equal(partial.statements[0].end,new TextEncoder().encode('/when @A = #iron\n/or @A = #steel').length);
 assert.deepEqual(partial.statements,parseRuleWriting(prefix).statements);assert.equal(view.conditions[0].operator,'Any');assert.equal(view.effects[0].kind,'hide');
 const andPrefix=prefix.replace('/or','/and'),andPartial=parseRuleWriting.inspectSyntax(andPrefix+'\n/set-hint @B "');
 assert.deepEqual(andPartial.statements,parseRuleWriting(andPrefix).statements);assert.equal(andPartial.statements[0].kind,'condition');
});
