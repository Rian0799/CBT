// Shared by the browser importer and regression tests. No file is uploaded to a third party.
export function parseTagged(text:string):any[]{
 const result:any[]=[];let section='pilgan',q:any=null,tag='TEXT';
 const flush=()=>{
  if(!q)return;const f=q.fields;
  const parse=(v:string)=>{try{return JSON.parse(v.trim());}catch{return v?.trim();}};
  const type=(f.TIPE??q.type).trim().toLowerCase();
  let options:any=Object.fromEntries(['A','B','C','D','E'].filter(k=>f[k]).map(k=>[k,f[k].trim()]));
  let key:any=parse(f.KUNCI??'');
  if(type==='kompleks'&&!Array.isArray(key))key=String(key).split(',').map(s=>s.trim()).filter(Boolean);
  if(type==='isian'&&!Array.isArray(key))key=String(key).split(',').map(s=>s.trim()).filter(Boolean);
  if(type==='jodohkan'){
   options={premises:q.premises,targets:q.targets};key=Object.keys(q.pairs).length?q.pairs:key;
   if(typeof key==='string'&&key)key=Object.fromEntries(key.split(',').map(pair=>pair.trim().split('-').map((v,i)=>(i?'t':'p')+v.trim())));
  }
  if(type==='benarsalah'){options={statements:q.statements};if(Object.keys(q.truth).length)key=q.truth;}
  if(type==='isian'||type==='uraian')options={};
  if(f.OPSI)options=parse(f.OPSI);
  if(type==='uraian')key=f.RUBRIK?.trim()||key;
  result.push({number:result.length+1,type,text:(f.TEXT??'').trim(),weight:Number(f.BOBOT??1),options,correctAnswer:key,explanation:(f.PEMBAHASAN??'').trim()});
 };
 for(const line of text.split(/\r?\n/)){
  const type=line.trim().match(/^(?:\[(PILGAN|KOMPLEKS|JODOHKAN|BENARSALAH|ISIAN|URAIAN)\]|===\s*SOAL\s+(PILGAN|KOMPLEKS|JODOHKAN|BENARSALAH|ISIAN|URAIAN)\s*===)$/i);
  if(type){section=(type[1]??type[2]).toLowerCase();continue;}
  const start=line.match(/^\s*\[SOAL\]\s*(.*)/i);
  if(start){flush();q={type:section,fields:{TEXT:start[1]+'\n'},premises:{},targets:{},pairs:{},statements:{},truth:{}};tag='TEXT';continue;}
  if(!q)continue;
  const match=line.match(/^\s*\[PREMIS\s*(\d+)\]\s*(.*?)\s*=>\s*\[TARGET\s*(\d+)\]\s*(.*)/i);
  if(match){q.type='jodohkan';q.premises['p'+match[1]]=match[2];q.targets['t'+match[3]]=match[4];q.pairs['p'+match[1]]='t'+match[3];continue;}
  const truth=line.match(/^\s*\[PERNYATAAN\s*(\d+)\]\s*(.*?)\s*=>\s*\[KUNCI\s*\d+\]\s*(BENAR|SALAH|B|S)/i);
  if(truth){q.type='benarsalah';q.statements['s'+truth[1]]=truth[2];q.truth['s'+truth[1]]=truth[3].toUpperCase().startsWith('B')?'B':'S';continue;}
  const m=line.match(/^\s*\[([^\]]+)\]\s*(.*)/);
  if(m){tag=m[1].toUpperCase();q.fields[tag]=(q.fields[tag]??'')+m[2]+'\n';}
  else q.fields[tag]=(q.fields[tag]??'')+line+'\n';
 }
 flush();return result;
}
