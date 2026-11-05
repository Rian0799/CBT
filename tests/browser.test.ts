import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInContext} from 'node:vm';
import {randomUUID} from 'node:crypto';
import {JSDOM} from 'jsdom';
import {parseTagged} from '../server/imports.js';
async function waitFor(check:()=>boolean){for(let i=0;i<100;i++){if(check())return;await new Promise(r=>setTimeout(r,5));}throw new Error('UI did not settle');}
function browser(){
 const dom=new JSDOM('<div id="app"></div><div id="toast"></div><dialog id="modal"></dialog>',{url:'https://exam.test',runScripts:'outside-only'});
 const w=dom.window as any;
 w.HTMLDialogElement.prototype.showModal=function(){this.setAttribute('open','');};
 w.HTMLDialogElement.prototype.close=function(){this.removeAttribute('open');};
 w.crypto.randomUUID=randomUUID;w.structuredClone=structuredClone;w.getUser=async()=>({id:'student'});w.parseTagged=parseTagged;
 w.handleAuthCallback=async()=>null;w.login=async()=>({});w.logout=async()=>{};
 const calls:{path:string;body:any}[]=[];let submitted=false,failSave=false;const saved:any[]=[];
 const questions=[{id:11,type:'pilgan',text:'Pilih jawaban',options:{A:'Satu',B:'Dua'},weight:1},{id:12,type:'uraian',text:'Jelaskan alasan',options:{},weight:4}];
 w.fetch=async(url:string,options:any)=>{
  const path=String(url).replace('/api/',''),body=options.body?JSON.parse(options.body):null;calls.push({path,body});let data:any;
  if(path==='me')data={id:'student',email:'peserta@example.test',name:'Peserta',role:'siswa',profile:{id:1,fullName:'Peserta Ujian',nisn:'00101',examNumber:'U-01'},settings:{schoolName:'Sekolah',maxViolations:3}};
  else if(path==='claim')data={ok:true};
  else if(path==='available')data=[{id:1,title:'Asesmen IPA',status:'active',startTime:new Date().toISOString(),endTime:new Date(Date.now()+3600000).toISOString(),duration:60,sessionName:'Sesi 1',room:'Ruang 01',antiCheat:true}];
  else if(path==='start')data={id:7};
  else if(path.startsWith('attempt?id='))data={id:7,title:'Asesmen IPA',deadline:new Date(Date.now()+3600000).toISOString(),status:submitted?'submitted':'in_progress',antiCheat:true,violations:0,questions:submitted?[]:questions,answers:saved,score:submitted?20:null,graded:false};
  else if(path==='attempt'){
   if(body.action==='save'){if(failSave)return {ok:false,json:async()=>({error:'Koneksi terputus'})};saved.push({questionId:body.questionId,answer:body.answer,doubtful:body.doubtful});data={status:'in_progress',saved:true};}
   if(body.action==='submit'){submitted=true;data={status:'submitted'};}
  }
  return {ok:true,json:async()=>data};
 };
 const source=readFileSync('src/app.js','utf8').replace(/^import .*;\n/gm,'').replace(/\ninit\(\);\s*$/,'');
 runInContext(source+'\nglobalThis.ui={loadSession,enterExam,handleAction,queueSave,getAttempt:()=>attempt,getSaveFailed:()=>saveFailed};',dom.getInternalVMContext());
 return {dom,w,calls,ui:w.ui,setFailure:(v:boolean)=>{failSave=v;}};
}
test('student can start, autosave, navigate, and submit through the browser screens',async()=>{
 const {dom,w,calls,ui}=browser();try{
  await ui.loadSession();assert.match(w.document.body.textContent,/Ujian saya/);assert.doesNotMatch(w.document.querySelector('nav').textContent,/Akun pengguna/);
  await ui.enterExam(1);const start=w.document.querySelector('#start-form');start.elements.token.value='TOKEN';start.dispatchEvent(new w.Event('submit',{cancelable:true}));
  await waitFor(()=>!!w.document.querySelector('#answer-input'));
  const radio=w.document.querySelector('input[name=answer][value=A]');radio.checked=true;radio.dispatchEvent(new w.Event('change',{bubbles:true}));
  await waitFor(()=>calls.some(c=>c.body?.action==='save'));
  await ui.handleAction(w.document.querySelector('[data-action=next-question]'));
  assert.match(w.document.querySelector('.question-text').textContent,/Jelaskan alasan/);
  const answer=w.document.querySelector('textarea');answer.value='Penjelasan peserta';answer.dispatchEvent(new w.Event('input',{bubbles:true}));
  await ui.handleAction(w.document.querySelector('[data-action=submit-attempt]'));
  const submit=w.document.querySelector('#submit-form');submit.dispatchEvent(new w.Event('submit',{cancelable:true}));
  await waitFor(()=>w.document.querySelector('#content').textContent.includes('Ujian telah selesai'));
  const saves=calls.filter(c=>c.body?.action==='save');assert.equal(saves.length,2);assert.equal(saves[1].body.answer,'Penjelasan peserta');assert.ok(calls.some(c=>c.body?.action==='submit'));
 }finally{dom.window.close();}
});
test('a later successful save cannot hide an earlier unsaved answer',async()=>{
 const {dom,w,ui,setFailure}=browser();try{
  await ui.loadSession();await ui.enterExam(1);const form=w.document.querySelector('#start-form');form.elements.token.value='TOKEN';form.dispatchEvent(new w.Event('submit',{cancelable:true}));await waitFor(()=>!!w.document.querySelector('#answer-input'));
  setFailure(true);ui.queueSave({questionId:11,answer:'A',doubtful:false});await waitFor(()=>ui.getSaveFailed());
  setFailure(false);ui.queueSave({questionId:12,answer:'Jawaban',doubtful:false});await new Promise(r=>setTimeout(r,20));assert.equal(ui.getSaveFailed(),true);
  await assert.rejects(()=>ui.handleAction(w.document.querySelector('[data-action=next-question]')),/Simpan kembali/);
  ui.queueSave({questionId:11,answer:'A',doubtful:false});await waitFor(()=>!ui.getSaveFailed());
 }finally{dom.window.close();}
});
