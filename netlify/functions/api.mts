import type {Config} from '@netlify/functions';
import {getUser,admin} from '@netlify/identity';
import {and,eq,inArray,sql,asc} from 'drizzle-orm';
import {randomInt} from 'node:crypto';
import {database} from '../../db/index.js';
import * as s from '../../db/schema.js';
import {grade,totalScore,analyze,types, type Question} from '../../server/grading.js';
import {HttpError,assert,str,num,id,date,choice,email,questionData} from '../../server/validation.js';

function shuffled<T>(values:T[]):T[]{const a=[...values];for(let i=a.length-1;i>0;i--){const j=randomInt(i+1);[a[i],a[j]]=[a[j],a[i]];}return a;}
function safeQuestions(snapshot:any[]){return snapshot.map(({correctAnswer,explanation,...q})=>({...q,options:['isian','uraian'].includes(q.type)?{}:q.options}));}
export function createHandler(deps: {getUser:typeof getUser;admin:typeof admin;database:typeof database} = {getUser,admin,database}) {
 const {getUser,admin,database}=deps;
 return async (req:Request) => {
 const reply=(body:any,status=200)=>Response.json(body,{status,headers:{'Cache-Control':'no-store'}});
 try{
  const url=new URL(req.url), resource=url.pathname.split('/').filter(Boolean)[1], method=req.method;
  assert(['GET','POST','PUT','DELETE'].includes(method),'Metode tidak didukung.',405);
  if(method!=='GET')assert(req.headers.get('origin')===url.origin,'Permintaan harus berasal dari situs ini.',403);
  const user=await getUser();assert(user,'Silakan masuk terlebih dahulu.',401);
  // Refresh roles from Identity, so deleted users and changed roles take effect immediately.
  const current=await admin.getUser(user.id);
  const roles=current.roles??(current.appMetadata?.roles as string[]|undefined)??[];
  const role=['admin','guru','operator'].find(r=>roles.includes(r))??'siswa';
  const db=database();
  const body=method==='GET'?{}:await (async()=>{assert(Number(req.headers.get('content-length')??0)<4000000,'Berkas terlalu besar.',413);const text=await req.text();assert(text.length<4000000,'Berkas terlalu besar.',413);try{const data=JSON.parse(text);assert(data&&typeof data==='object'&&!Array.isArray(data),'Data harus objek JSON.');return data;}catch{throw new HttpError(400,'Data JSON tidak valid.');}})();
  const staff=()=>assert(role!=='siswa','Akses petugas diperlukan.',403);
  const allow=(...allowed:string[])=>assert(allowed.includes(role),'Anda tidak memiliki izin.',403);
  const one=async(table:any,n:number)=>{const [row]=await db.select().from(table).where(eq(table.id,n));assert(row,'Data tidak ditemukan.',404);return row as any;};
  const bankAccess=async(n:number)=>{allow('admin','guru');const bank=await one(s.banks,n);assert(role==='admin'||bank.teacherId===user.id,'Bank soal milik guru lain.',403);return bank;};
  const examAccess=async(n:number)=>{staff();const exam=await one(s.exams,n);if(role==='guru')await bankAccess(exam.bankId);return exam;};
  const student=async()=>{const [row]=await db.select().from(s.students).where(eq(s.students.email,current.email?.toLowerCase()??''));assert(row,'Akun belum terdaftar sebagai siswa. Hubungi administrator.',403);return row;};
  const sid=()=>str(req.headers.get('x-exam-session'),'Sesi perangkat',100);
  const log=async(event:string,pid:number|null,details='')=>db.insert(s.logs).values({actor:user.id,event,participationId:pid,details});
  const recalculate=async(tx:any,p:any)=>{
   const rows=await tx.select().from(s.answers).where(eq(s.answers.participationId,p.id));
   const result=totalScore(p.snapshot,rows);
   await tx.update(s.participations).set(result).where(eq(s.participations.id,p.id));return result;
  };
  const finish=async(tx:any,p:any)=>{
   const saved=await tx.select().from(s.answers).where(eq(s.answers.participationId,p.id));
   const values=(p.snapshot as Question[]).map(q=>({participationId:p.id,questionId:q.id,...grade(q,saved.find((a:any)=>a.questionId===q.id)?.answer)}));
   await tx.insert(s.answers).values(values).onConflictDoUpdate({target:[s.answers.participationId,s.answers.questionId],set:{awarded:sql`excluded.awarded`,correct:sql`excluded.correct`}});
   await tx.update(s.participations).set({status:'submitted',endTime:new Date()}).where(eq(s.participations.id,p.id));
   return recalculate(tx,p);
  };
  if(resource==='me'&&method==='GET'){
   const [settings]=await db.select().from(s.settings);
   let profile=null;if(role==='siswa'){const [p]=await db.select().from(s.students).where(eq(s.students.email,current.email?.toLowerCase()??''));profile=p?(({sessionId,...safe})=>safe)(p):null;}
   return reply({id:user.id,email:current.email,name:current.name??current.userMetadata?.full_name??current.email,role,profile,settings:settings??{schoolName:'CBT SMART EXAM PRO',maxViolations:3}});
  }
  if(resource==='users'){
   allow('admin');
   if(method==='GET'){
    const all=[];for(let page=1;page<=100;page++){const rows=await admin.listUsers({page,perPage:100});all.push(...rows.map(u=>({id:u.id,email:u.email,name:u.name??u.userMetadata?.full_name,roles:u.roles??u.appMetadata?.roles??[]})));if(rows.length<100)break;}
    return reply(all);
   }
   const targetRole=choice(body.role,['admin','guru','operator','siswa']);
   const metadata={roles:targetRole==='siswa'?[]:[targetRole]};
   if(method==='POST'){
    assert(typeof body.password==='string'&&body.password.length>=10,'Kata sandi minimal 10 karakter.');
    const account=await admin.createUser({email:email(body.email),password:str(body.password,'Kata sandi',200),data:{app_metadata:metadata,user_metadata:{full_name:str(body.name,'Nama')}}});
    await admin.updateUser(account.id,{confirm:true});return reply({id:account.id},201);
   }
   const uid=str(body.id,'ID akun');assert(uid!==user.id,'Ubah akun administrator sendiri melalui dashboard Netlify.');
   if(method==='PUT'){if(body.password)assert(typeof body.password==='string'&&body.password.length>=10,'Kata sandi minimal 10 karakter.');await admin.updateUser(uid,{app_metadata:metadata,...(body.password?{password:str(body.password,'Kata sandi',200)}:{})});return reply({ok:true});}
   if(method==='DELETE'){await admin.deleteUser(uid);return reply({ok:true});}
  }
  if(resource==='settings'){
   allow('admin');
   if(method==='PUT'){
    const data={schoolName:str(body.schoolName,'Nama sekolah'),semester:choice(body.semester,['Ganjil','Genap']),academicYear:str(body.academicYear,'Tahun ajaran',30),maxViolations:Math.floor(num(body.maxViolations,'Batas pelanggaran',1,100))};
    await db.insert(s.settings).values({id:1,...data}).onConflictDoUpdate({target:s.settings.id,set:data});return reply(data);
   }
  }
  const tables:Record<string,any>={classes:s.classes,subjects:s.subjects,students:s.students,banks:s.banks,questions:s.questions,exams:s.exams};
  if(Object.hasOwn(tables,resource)){
   const table=tables[resource];staff();
   if(method==='GET'){
    if(resource==='questions'){
     const bankId=id(url.searchParams.get('bankId'));await bankAccess(bankId);
     return reply(await db.select().from(s.questions).where(eq(s.questions.bankId,bankId)).orderBy(asc(s.questions.number)));
    }
    if(resource==='banks'){allow('admin','guru');return reply(await db.select().from(s.banks).where(role==='guru'?eq(s.banks.teacherId,user.id):undefined));}
    if(resource==='exams'){
     let rows=await db.select().from(s.exams);
     if(role==='guru'){const own=await db.select().from(s.banks).where(eq(s.banks.teacherId,user.id));rows=rows.filter(e=>own.some(b=>b.id===e.bankId));}
     return reply(rows);
    }
    if(resource==='students'){allow('admin','operator');return reply((await db.select().from(s.students)).map(({sessionId,...r})=>({...r,loggedIn:!!sessionId})));}
    return reply(await db.select().from(table));
   }
   const n=method==='POST'?null:id(body.id);let existing:any=null;
   if(n)existing=await one(table,n);
   if(['classes','subjects','students'].includes(resource))allow('admin');else allow('admin','guru');
   if(resource==='banks'&&n)await bankAccess(n!);
   if(resource==='questions')await bankAccess(existing?.bankId??id(body.bankId));
   if(resource==='exams'&&n)await examAccess(n!);
   // Active attempts use immutable snapshots, but structural exam changes are blocked once an exam has begun.
   if(resource==='exams'&&n){const used=await db.select({id:s.participations.id}).from(s.participations).where(eq(s.participations.examId,n)).limit(1);assert(!used.length,'Ujian sudah dimulai. Buat jadwal baru untuk perubahan.');}
   if(method==='DELETE'){await db.delete(table).where(eq(table.id,n));return reply({ok:true});}
   let data:any;
   if(resource==='classes')data={name:str(body.name,'Nama kelas'),level:str(body.level,'Tingkat',30),major:str(body.major??'Umum','Jurusan')};
   if(resource==='subjects')data={code:str(body.code,'Kode',30),name:str(body.name,'Mata pelajaran')};
   if(resource==='students'){
    const classId=id(body.classId);await one(s.classes,classId);
    data={email:email(body.email),nisn:str(body.nisn,'NISN',30),examNumber:str(body.examNumber,'Nomor peserta',50),fullName:str(body.fullName,'Nama siswa'),classId,gender:choice(body.gender??'L',['L','P'])};
   }
   if(resource==='banks'){
    await one(s.subjects,id(body.subjectId));
    const composition=body.composition??{};assert(typeof composition==='object'&&!Array.isArray(composition),'Komposisi tidak valid.');
    for(const [t,v]of Object.entries(composition) as [string,any][]){choice(t,types);id(v.count);num(v.weight,'Bobot',.01,10000);}
    data={code:str(body.code,'Kode'),title:str(body.title,'Judul'),subjectId:id(body.subjectId),teacherId:existing?.teacherId??user.id,classLevel:str(body.classLevel??'Semua','Tingkat'),kkm:num(body.kkm??75,'KKM',0,100),status:choice(body.status??'draft',['draft','active']),composition};
   }
   if(resource==='questions')data={...questionData(body),bankId:existing?.bankId??id(body.bankId)};
   if(resource==='exams'){
    const bank=await bankAccess(id(body.bankId));
    const startTime=date(body.startTime),endTime=date(body.endTime);assert(endTime>startTime,'Waktu selesai harus setelah waktu mulai.');
    assert(Array.isArray(body.classIds)&&body.classIds.length>0,'Pilih minimal satu kelas.');
    const classIds=[...new Set(body.classIds.map(id))] as number[];const existingClasses=await db.select().from(s.classes).where(inArray(s.classes.id,classIds));assert(existingClasses.length===classIds.length,'Kelas tidak ditemukan.');
    if(body.status==='active'){const qs=await db.select().from(s.questions).where(eq(s.questions.bankId,bank.id));assert(qs.length>0,'Bank soal masih kosong.');}
    data={title:str(body.title,'Judul'),bankId:bank.id,classIds,sessionName:str(body.sessionName??'Sesi 1','Sesi'),room:str(body.room??'Ruang 01','Ruang'),startTime,endTime,duration:Math.floor(num(body.duration,'Durasi',1,1440)),randomizeQuestions:!!body.randomizeQuestions,randomizeOptions:!!body.randomizeOptions,showResults:!!body.showResults,antiCheat:!!body.antiCheat,status:choice(body.status??'draft',['draft','active','finished']),createdBy:existing?.createdBy??user.id};
   }
   const rows=n?await db.update(table).set(data).where(eq(table.id,n)).returning():await db.insert(table).values(data).returning();return reply(rows[0],n?200:201);
  }
  if(resource==='import-questions'&&method==='POST'){
   const bankId=id(body.bankId);await bankAccess(bankId);assert(Array.isArray(body.questions)&&body.questions.length>0&&body.questions.length<=500,'Impor 1–500 soal sekaligus.');
   const data=body.questions.map((q:any)=>({...questionData(q),bankId}));
   await db.transaction(async tx=>{await tx.insert(s.questions).values(data);});return reply({count:data.length});
  }
  if(resource==='import-students'&&method==='POST'){
   allow('admin');assert(Array.isArray(body.students)&&body.students.length>0&&body.students.length<=500,'Impor 1–500 siswa sekaligus.');
   const data=body.students.map((r:any)=>({email:email(r.email),nisn:str(r.nisn,'NISN',30),examNumber:str(r.examNumber,'Nomor peserta',50),fullName:str(r.fullName,'Nama siswa'),classId:id(r.classId),gender:choice(r.gender??'L',['L','P'])}));
   await db.transaction(async tx=>{for(const row of data)await tx.insert(s.students).values(row).onConflictDoUpdate({target:s.students.nisn,set:row});});return reply({count:data.length});
  }
  if(resource==='token'&&method==='POST'){
   allow('admin','operator');const exam=await examAccess(id(body.examId));
   const alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
   const token=Array.from({length:6},()=>alphabet[randomInt(alphabet.length)]).join('');
   const tokenExpires=new Date(Date.now()+num(body.minutes??60,'Masa berlaku',1,1440)*60000);
   await db.update(s.exams).set({token,tokenExpires,tokenActive:body.active!==false}).where(eq(s.exams.id,exam.id));await log('token',null,`exam:${exam.id}`);return reply({token,tokenExpires});
  }
  if(resource==='available'&&method==='GET'){
   allow('siswa');const st=await student();
   const available=(await db.select().from(s.exams).where(eq(s.exams.status,'active'))).filter(e=>e.classIds.includes(st.classId));
   const mine=await db.select().from(s.participations).where(eq(s.participations.studentId,st.id));
   return reply(available.map(({token,tokenExpires,createdBy,...e})=>({...e,participation:mine.find(p=>p.examId===e.id)?((p)=>({id:p.id,status:p.status,score:e.showResults&&p.status==='submitted'?p.score:null,graded:p.graded}))(mine.find(p=>p.examId===e.id)!):null})));
  }
  if(resource==='claim'&&method==='POST'){
   allow('siswa');const st=await student(), sessionId=sid();
   const claimed=await db.update(s.students).set({sessionId,identityId:user.id}).where(and(eq(s.students.id,st.id),sql`(${s.students.sessionId} IS NULL OR ${s.students.sessionId} = ${sessionId})`)).returning();
   assert(claimed.length,'Akun aktif di perangkat lain. Minta operator mereset login.',409);return reply({ok:true});
  }
  if(resource==='release'&&method==='POST'){
   allow('siswa');const st=await student();await db.update(s.students).set({sessionId:null}).where(and(eq(s.students.id,st.id),eq(s.students.sessionId,sid())));return reply({ok:true});
  }
  if(resource==='start'&&method==='POST'){
   allow('siswa');const st=await student();assert(st.sessionId===sid(),'Sesi tidak aktif. Masuk kembali.',409);
   const examId=id(body.examId);
   const result=await db.transaction(async tx=>{
    const [exam]=await tx.select().from(s.exams).where(eq(s.exams.id,examId)).for('update');
    assert(exam,'Ujian tidak ditemukan.',404);assert(exam.classIds.includes(st.classId),'Ujian bukan untuk kelas Anda.',403);
    const [old]=await tx.select().from(s.participations).where(and(eq(s.participations.examId,exam.id),eq(s.participations.studentId,st.id)));
    if(old){assert(old.status==='in_progress','Ujian selesai atau diblokir.');await tx.update(s.participations).set({sessionId:sid()}).where(eq(s.participations.id,old.id));return {id:old.id};}
    assert(exam.status==='active'&&new Date()>=exam.startTime&&new Date()<exam.endTime,'Ujian belum dimulai atau telah berakhir.');
    assert(exam.tokenActive&&exam.tokenExpires&&new Date()<exam.tokenExpires&&String(body.token??'').toUpperCase()===exam.token,'Token salah atau kedaluwarsa.');
    const bank=await one(s.banks,exam.bankId);let qs=await tx.select().from(s.questions).where(eq(s.questions.bankId,bank.id)).orderBy(asc(s.questions.number));
    assert(qs.length,'Bank soal kosong.');
    if(Object.keys(bank.composition).length){const selected:any[]=[];for(const [t,v]of Object.entries(bank.composition) as [string,any][]){const pool=qs.filter(q=>q.type===t);assert(pool.length>=v.count,`Jumlah soal ${t} belum mencukupi.`);selected.push(...shuffled(pool).slice(0,v.count).map(q=>({...q,weight:v.weight})));}qs=selected;}
    if(exam.randomizeQuestions)qs=shuffled(qs);
    if(exam.randomizeOptions)qs=qs.map(q=>['pilgan','kompleks'].includes(q.type)?{...q,options:Object.fromEntries(shuffled(Object.entries(q.options)))}:q);
    const deadline=new Date(Math.min(Date.now()+exam.duration*60000,exam.endTime.getTime()));
    const [p]=await tx.insert(s.participations).values({examId:exam.id,studentId:st.id,deadline,snapshot:qs,sessionId:sid()}).returning();return {id:p.id};
   });await log('start',result.id);return reply(result);
  }
  if(resource==='attempt'){
   allow('siswa');const st=await student(),pid=id(method==='GET'?url.searchParams.get('id'):body.id);
   const response=await db.transaction(async tx=>{
    const [p]=await tx.select().from(s.participations).where(eq(s.participations.id,pid)).for('update');
    assert(p&&p.studentId===st.id,'Sesi ujian tidak ditemukan.',404);
    assert(st.sessionId===sid()&&p.sessionId===sid(),'Sesi direset. Masuk kembali.',409);
    const exam=await one(s.exams,p.examId);
    if(['in_progress','blocked'].includes(p.status)&&new Date()>=p.deadline){await finish(tx,p);p.status='submitted';}
    if(method==='GET'){
     const rows=await tx.select().from(s.answers).where(eq(s.answers.participationId,pid));
     const result=totalScore(p.snapshot,rows);
     return {id:p.id,title:exam.title,deadline:p.deadline,status:p.status,antiCheat:exam.antiCheat,violations:p.violations,questions:p.status==='submitted'?[]:safeQuestions(p.snapshot),answers:rows.map(a=>({questionId:a.questionId,answer:a.answer,doubtful:a.doubtful})),score:exam.showResults&&p.status==='submitted'?result.score:null,graded:result.graded};
    }
    assert(method==='POST','Metode tidak didukung.',405);
    if(p.status==='submitted')return {status:'submitted'};
    assert(p.status==='in_progress','Ujian diblokir. Hubungi operator.',409);
    if(body.action==='save'){
     const q=p.snapshot.find((q:any)=>q.id===id(body.questionId));assert(q,'Soal tidak ditemukan.');
     assert(JSON.stringify(body.answer??null).length<=30000,'Jawaban terlalu panjang.');
     await tx.insert(s.answers).values({participationId:pid,questionId:q.id,answer:body.answer??null,doubtful:!!body.doubtful}).onConflictDoUpdate({target:[s.answers.participationId,s.answers.questionId],set:{answer:body.answer??null,doubtful:!!body.doubtful,updatedAt:new Date()}});
    }else if(body.action==='submit'){await finish(tx,p);return {status:'submitted'};}
    else if(body.action==='violation'&&exam.antiCheat){
     const [settings]=await tx.select().from(s.settings);const violations=p.violations+1;
     await tx.update(s.participations).set({violations,status:violations>=(settings?.maxViolations??3)?'blocked':'in_progress'}).where(eq(s.participations.id,pid));
     await tx.insert(s.logs).values({actor:user.id,participationId:pid,event:'tab_switch'});return {status:violations>=(settings?.maxViolations??3)?'blocked':'in_progress',violations};
    }else throw new HttpError(400,'Tindakan tidak valid.');
    return {status:'in_progress',saved:true};
   });return reply(response);
  }
  if(resource==='monitor'&&method==='GET'){
   allow('admin','operator');const exam=await examAccess(id(url.searchParams.get('examId')));
   let ps=await db.select().from(s.participations).where(eq(s.participations.examId,exam.id));
   for(const p of ps.filter(p=>['in_progress','blocked'].includes(p.status)&&p.deadline<=new Date()))await db.transaction(async tx=>{const [locked]=await tx.select().from(s.participations).where(eq(s.participations.id,p.id)).for('update');if(locked.status!=='submitted')await finish(tx,locked);});
   ps=await db.select().from(s.participations).where(eq(s.participations.examId,exam.id));
   const ids=ps.map(p=>p.id), rows=ids.length?await db.select().from(s.answers).where(inArray(s.answers.participationId,ids)):[];
   const sts=(await db.select().from(s.students)).filter(st=>exam.classIds.includes(st.classId));
   return reply(sts.map(st=>{const p=ps.find(p=>p.studentId===st.id);return {studentId:st.id,fullName:st.fullName,nisn:st.nisn,examNumber:st.examNumber,loggedIn:!!st.sessionId,id:p?.id,status:p?.status??'not_started',deadline:p?.deadline,violations:p?.violations??0,total:p?.snapshot.length??0,answered:rows.filter(a=>a.participationId===p?.id&&a.answer!=null&&a.answer!=='').length};}));
  }
  if(resource==='control'&&method==='POST'){
   allow('admin','operator');const action=choice(body.action,['reset','extend','unblock','submit']);
   if(action==='reset'){const st=await one(s.students,id(body.studentId));await db.update(s.students).set({sessionId:null}).where(eq(s.students.id,st.id));await log('reset_login',null,`student:${st.id}`);return reply({ok:true});}
   const pid=id(body.id);await db.transaction(async tx=>{
    const [p]=await tx.select().from(s.participations).where(eq(s.participations.id,pid)).for('update');assert(p,'Sesi tidak ditemukan.',404);assert(p.status!=='submitted','Ujian sudah selesai.');
    if(action==='extend'){const minutes=num(body.minutes,'Tambahan waktu',1,240);await tx.update(s.participations).set({deadline:new Date(Math.max(Date.now(),p.deadline.getTime())+minutes*60000)}).where(eq(s.participations.id,pid));}
    if(action==='unblock')await tx.update(s.participations).set({status:'in_progress',violations:0}).where(eq(s.participations.id,pid));
    if(action==='submit')await finish(tx,p);
    await tx.insert(s.logs).values({actor:user.id,participationId:pid,event:action});
   });return reply({ok:true});
  }
  if(resource==='results'&&method==='GET'){
   allow('admin','guru');const exam=await examAccess(id(url.searchParams.get('examId'))),bank=await bankAccess(exam.bankId);
   const ps=await db.select().from(s.participations).where(and(eq(s.participations.examId,exam.id),eq(s.participations.status,'submitted')));
   const sts=await db.select().from(s.students);const pids=ps.map(p=>p.id);
   const rows=pids.length?await db.select().from(s.answers).where(inArray(s.answers.participationId,pids)):[];
   const qs=Array.from(new Map(ps.flatMap(p=>p.snapshot).map(q=>[q.id,q])).values());
   return reply({exam,kkm:bank.kkm,participants:ps.map(({snapshot,sessionId,...p})=>({...p,student:sts.find(st=>st.id===p.studentId)?.fullName,examNumber:sts.find(st=>st.id===p.studentId)?.examNumber,passed:p.score>=bank.kkm})),essays:rows.filter(a=>qs.some(q=>q.id===a.questionId&&q.type==='uraian')).map(a=>({...a,text:qs.find(q=>q.id===a.questionId)?.text,rubric:qs.find(q=>q.id===a.questionId)?.correctAnswer,weight:ps.find(p=>p.id===a.participationId)?.snapshot.find(q=>q.id===a.questionId)?.weight,student:sts.find(st=>st.id===ps.find(p=>p.id===a.participationId)?.studentId)?.fullName})),analysis:analyze(qs,ps,rows)});
  }
  if(resource==='grade'&&method==='POST'){
   allow('admin','guru');const a=await one(s.answers,id(body.id)),p=await one(s.participations,a.participationId);assert(p.status==='submitted','Ujian belum selesai.');await examAccess(p.examId);
   const q=p.snapshot.find((q:any)=>q.id===a.questionId);assert(q?.type==='uraian','Hanya uraian dikoreksi manual.');
   await db.transaction(async tx=>{await tx.select().from(s.participations).where(eq(s.participations.id,p.id)).for('update');await tx.update(s.answers).set({awarded:num(body.awarded,'Skor',0,q.weight),correct:Number(body.awarded)===q.weight,feedback:typeof body.feedback==='string'?body.feedback.slice(0,10000):''}).where(eq(s.answers.id,a.id));await recalculate(tx,p);});return reply({ok:true});
  }
  throw new HttpError(404,'Endpoint tidak ditemukan.');
 }catch(error:any){
  if(error instanceof HttpError)return reply({error:error.message},error.status);
  if(error?.code==='23505'||error?.cause?.code==='23505')return reply({error:'Data duplikat. Periksa kode, email, NISN, atau nomor peserta.'},409);
  if(error?.code==='23503'||error?.cause?.code==='23503')return reply({error:'Data masih digunakan atau referensi tidak ditemukan.'},409);
  // Do not include provider errors: they can contain connection details or personal data.
  return reply({error:'Layanan belum tersedia. Periksa konfigurasi Identity dan migrasi database di Netlify.'},503);
 }
};
}
export default async function handler(req:Request){return createHandler()(req);}
export const config:Config={path:'/api/*'};
