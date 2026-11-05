import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createHandler} from '../netlify/functions/api.mjs';
function request(path:string,body?:any,origin='https://exam.test'){
 return new Request('https://exam.test/api/'+path,{method:body?'POST':'GET',headers:body?{'Content-Type':'application/json',Origin:origin}:{},body:body?JSON.stringify(body):undefined});
}
function handler(role:string|null){return createHandler({getUser:async()=>role?{id:'test-user'}:null,admin:{getUser:async()=>({id:'test-user',email:'test@example.test',roles:role?[role]:[]})},database:()=>({})} as any);}
test('unauthenticated requests are rejected before database access',async()=>{
 const r=await handler(null)(request('exams'));assert.equal(r.status,401);assert.equal(r.headers.get('Cache-Control'),'no-store');
});
test('cross-origin mutations are rejected before identity or data access',async()=>{
 const r=await handler('admin')(request('classes',{name:'A'},'https://untrusted.test'));assert.equal(r.status,403);
});
test('students cannot read staff records or answer keys',async()=>{
 for(const route of ['classes','students','questions?bankId=1','exams','users','results?examId=1']){
  const r=await handler('siswa')(request(route));assert.equal(r.status,403,route);
 }
});
test('operators cannot edit question banks, create accounts, or grade answers',async()=>{
 for(const [path,body]of [['banks',{title:'Bank'}],['users',{email:'x@example.test'}],['grade',{id:1,awarded:99}]] as const){
  const r=await handler('operator')(request(path,body));assert.equal(r.status,403,path);
 }
 const r=await handler('operator')(request('questions?bankId=1'));assert.equal(r.status,403);
});
