CREATE TABLE "student_answers" (
	"id" serial PRIMARY KEY,
	"participation_id" integer NOT NULL,
	"question_id" integer NOT NULL,
	"answer" jsonb,
	"doubtful" boolean DEFAULT false NOT NULL,
	"correct" boolean,
	"awarded" double precision DEFAULT 0 NOT NULL,
	"feedback" text DEFAULT '' NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "question_banks" (
	"id" serial PRIMARY KEY,
	"code" text NOT NULL UNIQUE,
	"title" text NOT NULL,
	"subject_id" integer NOT NULL,
	"teacher_id" text NOT NULL,
	"class_level" text DEFAULT 'Semua' NOT NULL,
	"kkm" double precision DEFAULT 75 NOT NULL,
	"composition" jsonb DEFAULT '{}' NOT NULL,
	"status" text DEFAULT 'draft' NOT NULL
);
--> statement-breakpoint
CREATE TABLE "classes" (
	"id" serial PRIMARY KEY,
	"name" text NOT NULL,
	"level" text NOT NULL,
	"major" text DEFAULT 'Umum' NOT NULL
);
--> statement-breakpoint
CREATE TABLE "exams" (
	"id" serial PRIMARY KEY,
	"title" text NOT NULL,
	"bank_id" integer NOT NULL,
	"class_ids" jsonb DEFAULT '[]' NOT NULL,
	"session_name" text DEFAULT 'Sesi 1' NOT NULL,
	"room" text DEFAULT 'Ruang 01' NOT NULL,
	"start_time" timestamp with time zone NOT NULL,
	"end_time" timestamp with time zone NOT NULL,
	"duration" integer DEFAULT 60 NOT NULL,
	"token" text,
	"token_expires" timestamp with time zone,
	"token_active" boolean DEFAULT false NOT NULL,
	"randomize_questions" boolean DEFAULT true NOT NULL,
	"randomize_options" boolean DEFAULT true NOT NULL,
	"show_results" boolean DEFAULT false NOT NULL,
	"anti_cheat" boolean DEFAULT true NOT NULL,
	"status" text DEFAULT 'draft' NOT NULL,
	"created_by" text NOT NULL
);
--> statement-breakpoint
CREATE TABLE "exam_logs" (
	"id" serial PRIMARY KEY,
	"participation_id" integer,
	"actor" text NOT NULL,
	"event" text NOT NULL,
	"details" text DEFAULT '' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "exam_participations" (
	"id" serial PRIMARY KEY,
	"exam_id" integer NOT NULL,
	"student_id" integer NOT NULL,
	"start_time" timestamp with time zone DEFAULT now() NOT NULL,
	"deadline" timestamp with time zone NOT NULL,
	"end_time" timestamp with time zone,
	"status" text DEFAULT 'in_progress' NOT NULL,
	"score" double precision DEFAULT 0 NOT NULL,
	"graded" boolean DEFAULT false NOT NULL,
	"violations" integer DEFAULT 0 NOT NULL,
	"snapshot" jsonb NOT NULL,
	"session_id" text NOT NULL
);
--> statement-breakpoint
CREATE TABLE "questions" (
	"id" serial PRIMARY KEY,
	"bank_id" integer NOT NULL,
	"number" integer NOT NULL,
	"type" text NOT NULL,
	"text" text NOT NULL,
	"image" text,
	"options" jsonb DEFAULT '{}' NOT NULL,
	"correct_answer" jsonb,
	"weight" double precision DEFAULT 1 NOT NULL,
	"explanation" text DEFAULT '' NOT NULL
);
--> statement-breakpoint
CREATE TABLE "settings" (
	"id" integer PRIMARY KEY DEFAULT 1,
	"school_name" text DEFAULT 'CBT SMART EXAM PRO' NOT NULL,
	"semester" text DEFAULT 'Ganjil' NOT NULL,
	"academic_year" text DEFAULT '2026/2027' NOT NULL,
	"max_violations" integer DEFAULT 3 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "students" (
	"id" serial PRIMARY KEY,
	"identity_id" text UNIQUE,
	"email" text NOT NULL UNIQUE,
	"nisn" text NOT NULL UNIQUE,
	"exam_number" text NOT NULL UNIQUE,
	"full_name" text NOT NULL,
	"class_id" integer NOT NULL,
	"gender" text DEFAULT 'L' NOT NULL,
	"session_id" text
);
--> statement-breakpoint
CREATE TABLE "subjects" (
	"id" serial PRIMARY KEY,
	"code" text NOT NULL UNIQUE,
	"name" text NOT NULL
);
--> statement-breakpoint
CREATE UNIQUE INDEX "answer_participation_question" ON "student_answers" ("participation_id","question_id");--> statement-breakpoint
CREATE UNIQUE INDEX "participation_exam_student" ON "exam_participations" ("exam_id","student_id");--> statement-breakpoint
ALTER TABLE "student_answers" ADD CONSTRAINT "student_answers_participation_id_exam_participations_id_fkey" FOREIGN KEY ("participation_id") REFERENCES "exam_participations"("id") ON DELETE CASCADE;--> statement-breakpoint
ALTER TABLE "question_banks" ADD CONSTRAINT "question_banks_subject_id_subjects_id_fkey" FOREIGN KEY ("subject_id") REFERENCES "subjects"("id");--> statement-breakpoint
ALTER TABLE "exams" ADD CONSTRAINT "exams_bank_id_question_banks_id_fkey" FOREIGN KEY ("bank_id") REFERENCES "question_banks"("id");--> statement-breakpoint
ALTER TABLE "exam_logs" ADD CONSTRAINT "exam_logs_participation_id_exam_participations_id_fkey" FOREIGN KEY ("participation_id") REFERENCES "exam_participations"("id");--> statement-breakpoint
ALTER TABLE "exam_participations" ADD CONSTRAINT "exam_participations_exam_id_exams_id_fkey" FOREIGN KEY ("exam_id") REFERENCES "exams"("id");--> statement-breakpoint
ALTER TABLE "exam_participations" ADD CONSTRAINT "exam_participations_student_id_students_id_fkey" FOREIGN KEY ("student_id") REFERENCES "students"("id");--> statement-breakpoint
ALTER TABLE "questions" ADD CONSTRAINT "questions_bank_id_question_banks_id_fkey" FOREIGN KEY ("bank_id") REFERENCES "question_banks"("id") ON DELETE CASCADE;--> statement-breakpoint
ALTER TABLE "students" ADD CONSTRAINT "students_class_id_classes_id_fkey" FOREIGN KEY ("class_id") REFERENCES "classes"("id");