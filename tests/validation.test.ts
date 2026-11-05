import {test} from 'node:test';
import assert from 'node:assert/strict';
import {questionData,id,email} from '../server/validation.js';
import {parseTagged} from '../server/imports.js';
test('question validation rejects invalid keys, types, and weights',()=>{
 const base={text:'Pertanyaan',type:'pilgan',options:{A:'Satu',B:'Dua'},correctAnswer:'A'};
 assert.equal(questionData(base).weight,1);
 assert.throws(()=>questionData({...base,correctAnswer:'Z'}));
 assert.throws(()=>questionData({...base,weight:-1}));
 assert.throws(()=>questionData({...base,type:'invalid'}));
 assert.throws(()=>questionData({text:'Pasangan',type:'jodohkan',options:{premises:{p1:'A'},targets:{t1:'B'}},correctAnswer:{p1:'t9'}}));
 assert.throws(()=>id(1.5));assert.throws(()=>email('bad'));
});
test('JSON encoded legacy scalar options and answer keys are normalized',()=>{
 const result=questionData({question_text:'Pertanyaan',type:'pilgan',options_data:'{"A":"Satu","B":"Dua"}',correct_answer:'"A"',score_weight:2});
 assert.equal(result.correctAnswer,'A');assert.deepEqual(result.options,{A:'Satu',B:'Dua'});
});
test('tagged Word/text import preserves all six question types',()=>{
 const text=`[PILGAN]\n[SOAL] Pilih satu\n[A] Satu\n[B] Dua\n[KUNCI] A\n[KOMPLEKS]\n[SOAL] Pilih banyak\n[A] Satu\n[B] Dua\n[C] Tiga\n[KUNCI] A,C\n[JODOHKAN]\n[SOAL] Pasangkan\n[PREMIS 1] Kiri => [TARGET 2] Kanan\n[BENARSALAH]\n[SOAL] Tentukan\n[PERNYATAAN 1] Pernyataan => [KUNCI 1] BENAR\n[ISIAN]\n[SOAL] Isi\n[KUNCI] Jakarta,DKI\n[URAIAN]\n[SOAL] Jelaskan\n[RUBRIK] Penjelasan lengkap`;
 const parsed=parseTagged(text);assert.equal(parsed.length,6);
 for(const q of parsed)assert.doesNotThrow(()=>questionData(q));
 assert.deepEqual(parsed[1].correctAnswer,['A','C']);
 assert.deepEqual(parsed[2].correctAnswer,{p1:'t2'});
 assert.deepEqual(parsed[3].correctAnswer,{s1:'B'});
 assert.equal(parsed[5].correctAnswer,'Penjelasan lengkap');
});
test('current template supports multiline text and explicit JSON options',()=>{
 const [q]=parseTagged('[SOAL] Baris satu\nBaris dua\n[TIPE] benarsalah\n[OPSI] {"statements":{"s1":"Pernyataan"}}\n[KUNCI] {"s1":"S"}\n[BOBOT] 2');
 assert.equal(q.text,'Baris satu\nBaris dua');
 assert.deepEqual(q.correctAnswer,{s1:'S'});assert.equal(q.weight,2);
});
test('legacy complex choice and matching option arrays import into the new schema',()=>{
 const complex=questionData({question_text:'Pilih',type:'kompleks',options_data:JSON.stringify([{key:'A',text:'Satu'},{key:'B',text:'Dua'}]),correct_answer:'["A"]'});
 assert.deepEqual(complex.options,{A:'Satu',B:'Dua'});
 const matching=questionData({text:'Pasangkan',type:'jodohkan',options:{premises:[{id:'p1',text:'Kiri'}],targets:[{id:'t1',text:'Kanan'}]},correctAnswer:{p1:'t1'}});
 assert.deepEqual(matching.options.premises,{p1:'Kiri'});
 const truth=questionData({text:'Tentukan',type:'benarsalah',options:[{id:'s1',text:'Pernyataan'}],correctAnswer:{s1:'B'}});
 assert.deepEqual(truth.options.statements,{s1:'Pernyataan'});
});
