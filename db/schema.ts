import { pgTable, serial, text, integer, boolean, timestamp, jsonb, doublePrecision, uniqueIndex } from 'drizzle-orm/pg-core';
export const classes = pgTable('classes', {
 id: serial().primaryKey(), name: text().notNull(), level: text().notNull(), major: text().notNull().default('Umum'),
});
export const subjects = pgTable('subjects', {
 id: serial().primaryKey(), code: text().notNull().unique(), name: text().notNull(),
});
export const students = pgTable('students', {
 id: serial().primaryKey(), identityId: text('identity_id').unique(), email: text().notNull().unique(), nisn: text().notNull().unique(),
 examNumber: text('exam_number').notNull().unique(), fullName: text('full_name').notNull(), classId: integer('class_id').notNull().references(() => classes.id),
 gender: text().notNull().default('L'), sessionId: text('session_id'),
});
export const banks = pgTable('question_banks', {
 id: serial().primaryKey(), code: text().notNull().unique(), title: text().notNull(), subjectId: integer('subject_id').notNull().references(() => subjects.id),
 teacherId: text('teacher_id').notNull(), classLevel: text('class_level').notNull().default('Semua'), kkm: doublePrecision().notNull().default(75),
 composition: jsonb().$type<Record<string, {count:number;weight:number}>>().notNull().default({}), status: text().notNull().default('draft'),
});
export const questions = pgTable('questions', {
 id: serial().primaryKey(), bankId: integer('bank_id').notNull().references(() => banks.id, {onDelete:'cascade'}),
 number: integer().notNull(), type: text().notNull(), text: text().notNull(), image: text(), options: jsonb().$type<any>().notNull().default({}),
 correctAnswer: jsonb('correct_answer').$type<any>(), weight: doublePrecision().notNull().default(1), explanation: text().notNull().default(''),
});
export const exams = pgTable('exams', {
 id: serial().primaryKey(), title: text().notNull(), bankId: integer('bank_id').notNull().references(() => banks.id),
 classIds: jsonb('class_ids').$type<number[]>().notNull().default([]), sessionName: text('session_name').notNull().default('Sesi 1'), room: text().notNull().default('Ruang 01'),
 startTime: timestamp('start_time',{withTimezone:true}).notNull(), endTime: timestamp('end_time',{withTimezone:true}).notNull(), duration: integer().notNull().default(60),
 token: text(), tokenExpires: timestamp('token_expires',{withTimezone:true}), tokenActive: boolean('token_active').notNull().default(false),
 randomizeQuestions: boolean('randomize_questions').notNull().default(true), randomizeOptions: boolean('randomize_options').notNull().default(true),
 showResults: boolean('show_results').notNull().default(false), antiCheat: boolean('anti_cheat').notNull().default(true),
 status: text().notNull().default('draft'), createdBy: text('created_by').notNull(),
});
export const participations = pgTable('exam_participations', {
 id: serial().primaryKey(), examId: integer('exam_id').notNull().references(() => exams.id), studentId: integer('student_id').notNull().references(() => students.id),
 startTime: timestamp('start_time',{withTimezone:true}).notNull().defaultNow(), deadline: timestamp({withTimezone:true}).notNull(),
 endTime: timestamp('end_time',{withTimezone:true}), status: text().notNull().default('in_progress'),
 score: doublePrecision().notNull().default(0), graded: boolean().notNull().default(false), violations: integer().notNull().default(0),
 snapshot: jsonb().$type<any[]>().notNull(), sessionId: text('session_id').notNull(),
}, t => [uniqueIndex('participation_exam_student').on(t.examId,t.studentId)]);
export const answers = pgTable('student_answers', {
 id: serial().primaryKey(), participationId: integer('participation_id').notNull().references(() => participations.id,{onDelete:'cascade'}),
 questionId: integer('question_id').notNull(), answer: jsonb().$type<any>(), doubtful: boolean().notNull().default(false),
 correct: boolean(), awarded: doublePrecision().notNull().default(0), feedback: text().notNull().default(''),
 updatedAt: timestamp('updated_at',{withTimezone:true}).notNull().defaultNow(),
}, t => [uniqueIndex('answer_participation_question').on(t.participationId,t.questionId)]);
export const logs = pgTable('exam_logs', {
 id: serial().primaryKey(), participationId: integer('participation_id').references(() => participations.id),
 actor: text().notNull(), event: text().notNull(), details: text().notNull().default(''), createdAt: timestamp('created_at',{withTimezone:true}).notNull().defaultNow(),
});
export const settings = pgTable('settings', {
 id: integer().primaryKey().default(1), schoolName: text('school_name').notNull().default('CBT SMART EXAM PRO'),
 semester: text().notNull().default('Ganjil'), academicYear: text('academic_year').notNull().default('2026/2027'),
 maxViolations: integer('max_violations').notNull().default(3),
});
