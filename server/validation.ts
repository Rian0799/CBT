import {types} from './grading.js';
export class HttpError extends Error { constructor(public status:number,message:string){super(message);} }
export function assert(value:any,message:string,status=400):asserts value {if(!value)throw new HttpError(status,message);}
export function str(value:any,label:string,max=250) {assert(typeof value==='string'&&value.trim().length>0&&value.length<=max,`${label} wajib diisi (maksimal ${max} karakter).`);return value.trim();}
export function num(value:any,label:string,min=1,max=2147483647){const n=Number(value);assert(Number.isFinite(n)&&n>=min&&n<=max,`${label} tidak valid.`);return n;}
export function id(value:any){const n=num(value,'ID');assert(Number.isInteger(n),'ID tidak valid.');return n;}
export function date(value:any){const d=new Date(value);assert(!isNaN(d.getTime()),'Tanggal tidak valid.');return d;}
export function choice(value:any,options:string[]){assert(options.includes(value),'Pilihan tidak valid.');return value;}
export function email(value:any){const e=str(value,'Email').toLowerCase();assert(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e),'Email tidak valid.');return e;}
export function questionData(b:any){
 const type=choice(b.type,types), weight=num(b.weight??b.score_weight??1,'Bobot',.01,10000);
 const options=b.options??b.options_data??{}, correctAnswer=b.correctAnswer??b.correct_answer;
 const parse=(v:any)=>{if(typeof v==='string'){try{return JSON.parse(v);}catch{return v;}}return v;};
 let opts=parse(options), key=parse(correctAnswer);
 const objectList=(v:any)=>Array.isArray(v)?Object.fromEntries(v.map((item:any)=>[item.id??item.key,item.text])):v;
 if(['pilgan','kompleks'].includes(type)&&Array.isArray(opts))opts=objectList(opts);
 if(type==='jodohkan'&&opts)opts={...opts,premises:objectList(opts.premises),targets:objectList(opts.targets)};
 if(type==='benarsalah'&&Array.isArray(opts))opts={statements:objectList(opts)};
 if(type==='uraian'&&opts?.rubric)key=opts.rubric;

 assert(opts&&typeof opts==='object'&&!Array.isArray(opts),'Format opsi harus objek JSON.');
 if(['pilgan','kompleks'].includes(type)){
  assert(Object.keys(opts).length>=2,'Masukkan minimal dua opsi.');
  const keys=type==='pilgan'?[key]:key;
  assert(Array.isArray(keys)&&keys.length>0&&keys.every(k=>typeof k==='string'&&Object.hasOwn(opts,k)),'Kunci harus sesuai opsi.');
 }
 if(['benarsalah','jodohkan'].includes(type)){
  const premises=type==='benarsalah'?(opts.statements??opts):opts.premises;
  assert(premises&&typeof premises==='object'&&!Array.isArray(premises),'Pernyataan/premis harus objek JSON.');
  assert(key&&typeof key==='object'&&!Array.isArray(key)&&Object.keys(key).length>0,'Kunci harus objek JSON.');
  assert(Object.keys(premises).length===Object.keys(key).length&&Object.keys(premises).every(k=>Object.hasOwn(key,k)),'Setiap pernyataan/premis membutuhkan kunci.');
  if(type==='benarsalah')assert(Object.values(key).every(k=>['B','S'].includes(String(k))),'Gunakan B atau S untuk kunci.');
  else assert(opts.targets&&Object.values(key).every(k=>Object.hasOwn(opts.targets,String(k))),'Target pasangan tidak valid.');
 }
 if(type==='isian')assert((typeof key==='string'&&key.trim())||(Array.isArray(key)&&key.length&&key.every(v=>typeof v==='string'&&v.trim())),'Kunci isian wajib diisi.');
 return {type,weight,options:opts,correctAnswer:key??null,text:str(b.text??b.question_text,'Teks soal',30000),number:id(b.number??b.question_number??1),image:b.image?str(b.image,'URL gambar',2000):null,explanation:typeof b.explanation==='string'?b.explanation.slice(0,10000):''};
}
