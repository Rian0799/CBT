export const types = ['pilgan','kompleks','jodohkan','benarsalah','isian','uraian'];
export type Question = {id:number;type:string;correctAnswer:any;options:any;weight:number;[key:string]:any};
export function grade(q: Question, answer: any): {correct:boolean|null;awarded:number} {
 if(q.type === 'uraian') return {correct:null,awarded:0};
 if(answer == null || answer === '') return {correct:false,awarded:0};
 const norm=(v:any) => String(v ?? '').trim().toLocaleLowerCase();
 let fraction=0;
 if(q.type==='pilgan') fraction=norm(answer)===norm(q.correctAnswer)?1:0;
 if(q.type==='isian') {
  const keys=Array.isArray(q.correctAnswer)?q.correctAnswer:[q.correctAnswer];
  fraction=keys.some((k:any) => q.options?.case_sensitive?String(k).trim()===String(answer).trim():norm(k)===norm(answer))?1:0;
 }
 if(q.type==='kompleks') {
  const keys=Array.isArray(q.correctAnswer)?q.correctAnswer:[];
  const picked=[...new Set(Array.isArray(answer)?answer:[])];
  fraction=keys.length?Math.max(0,(picked.filter(v=>keys.includes(v)).length-0.5*picked.filter(v=>!keys.includes(v)).length)/keys.length):0;
 }
 if(['jodohkan','benarsalah'].includes(q.type)) {
  const pairs=Object.entries(q.correctAnswer ?? {});
  fraction=pairs.length?pairs.filter(([k,v])=>answer?.[k]!=null && norm(answer[k])===norm(v)).length/pairs.length:0;
 }
 fraction=Math.min(1,fraction);
 return {correct:fraction===1,awarded:Math.round(fraction*q.weight*100)/100};
}
export function totalScore(qs:Question[], rows:{questionId:number;awarded:number;correct:boolean|null}[]) {
 const weight=qs.reduce((n,q)=>n+q.weight,0);
 const score=qs.reduce((n,q)=>n+(rows.find(a=>a.questionId===q.id)?.awarded ?? 0),0);
 return {score:weight?Math.round(score/weight*10000)/100:0, graded:qs.every(q=>q.type!=='uraian'||rows.some(a=>a.questionId===q.id&&a.correct!==null))};
}
export function analyze(qs:Question[], parts:{id:number;score:number}[], rows:any[]) {
 const ranked=[...parts].sort((a,b)=>b.score-a.score), n=parts.length, size=Math.floor(n/2);
 const upper=ranked.slice(0,size), lower=ranked.slice(-size);
 return qs.map(q=>{
  const correct=(id:number)=>rows.find(a=>a.participationId===id&&a.questionId===q.id)?.correct===true;
  const p=n?parts.filter(a=>correct(a.id)).length/n:null;
  const d=size?(upper.filter(a=>correct(a.id)).length-lower.filter(a=>correct(a.id)).length)/size:null;
  const distribution:Record<string,number>={};
  for(const a of rows.filter(a=>a.questionId===q.id)) {const k=typeof a.answer==='string'?a.answer:'Lainnya';distribution[k]=(distribution[k]??0)+1;}
  return {id:q.id,number:q.number,type:q.type,p,d,category:p===null?'Belum ada data':p>=.7?'Mudah':p>=.3?'Sedang':'Sukar',distribution};
 });
}
