import {test} from 'node:test';
import assert from 'node:assert/strict';
import {grade,totalScore,analyze,type Question} from '../server/grading.js';
const q=(type:string,key:any,weight=4,options:any={}):Question=>({id:1,type,correctAnswer:key,weight,options});
test('single-choice and short-answer normalization respects case setting',()=>{
 assert.equal(grade(q('pilgan','A'),' a ').awarded,4);
 assert.equal(grade(q('isian',['Jakarta','DKI']),' jakarta ').awarded,4);
 assert.equal(grade(q('isian','Jakarta',4,{case_sensitive:true}),'jakarta').awarded,0);
 assert.equal(grade(q('pilgan','A'),null).awarded,0);
});
test('complex choice applies partial credit, wrong-choice penalty, and deduplicates',()=>{
 assert.deepEqual(grade(q('kompleks',['A','C']),['A','C']),{correct:true,awarded:4});
 assert.equal(grade(q('kompleks',['A','C']),['A','B']).awarded,1);
 assert.equal(grade(q('kompleks',['A','C']),['A','A','A']).awarded,2);
 assert.equal(grade(q('kompleks',['A','C']),['B','D']).awarded,0);
});
test('matching and true/false award proportional scores for completed entries',()=>{
 assert.equal(grade(q('jodohkan',{p1:'t2',p2:'t1'}),{p1:'t2'}).awarded,2);
 assert.deepEqual(grade(q('benarsalah',{s1:'B',s2:'S'}),{s1:'b',s2:'s'}),{correct:true,awarded:4});
 assert.equal(grade(q('benarsalah',{s1:'B',s2:'S'}),{}).awarded,0);
});
test('essays remain pending until manually graded and totals include unanswered questions',()=>{
 const qs=[q('pilgan','A'),{...q('uraian','rubric',6),id:2}];
 assert.equal(grade(qs[1],'answer').correct,null);
 assert.deepEqual(totalScore(qs,[{questionId:1,awarded:4,correct:true}]),{score:40,graded:false});
 assert.deepEqual(totalScore(qs,[{questionId:1,awarded:4,correct:true},{questionId:2,awarded:3,correct:false}]),{score:70,graded:true});
});
test('analysis handles absent and insufficient cohorts without fabricated discrimination',()=>{
 assert.equal(analyze([q('pilgan','A')],[],[])[0].p,null);
 assert.equal(analyze([q('pilgan','A')],[{id:1,score:100}],[])[0].d,null);
 const result=analyze([q('pilgan','A')],[{id:1,score:100},{id:2,score:0}],[{participationId:1,questionId:1,correct:true,answer:'A'},{participationId:2,questionId:1,correct:false,answer:'B'}])[0];
 assert.equal(result.p,.5);assert.equal(result.d,1);assert.deepEqual(result.distribution,{A:1,B:1});
});
