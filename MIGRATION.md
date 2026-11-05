# CBT SMART EXAM PRO on Netlify

The Netlify application uses a static browser interface, TypeScript Netlify Functions, Netlify Identity accounts, and managed Postgres through Netlify Database and Drizzle. Apache, PHP, and MySQL are no longer required for this deployment. The original PHP source and SQL remain in the repository for reference; only `public/` is published. Do not change the publish directory to the repository root.

## Deployment

`netlify.toml` supplies the build command (`npm run build`), publish directory (`public`), and functions directory. The browser bundle is generated from `src/app.js` during deployment. `public/index.html` and `public/styles.css` are committed, so the publish directory exists in a fresh checkout. The dependency lockfile makes installs reproducible.

Netlify Database provisions the database and applies the generated SQL migration in `netlify/database/migrations/` during deployment. Do not run the old PHP installer or import the MySQL schema into Postgres. Future schema changes belong in `db/schema.ts` and require a new generated migration.

Identity has been enabled for the project. The initial administrator must be configured through the Netlify dashboard:

1. Open **Identity**, invite the administrator's email, and accept the invitation on the deployed site.
2. Open that user in the Identity dashboard, add the **admin** role, and save.
3. Sign in to the application again. The administrator navigation becomes available.

There is no public registration form, default administrator password, or automatic first-user promotion. Roles are checked on the server against Identity. The invitation and password recovery callbacks are handled by the application.

## Set up a school

1. Save the school name, semester, academic year, and violation threshold in **Pengaturan**.
2. Add classes and subjects.
3. Create staff and student email accounts in **Akun pengguna**, assigning `admin`, `guru`, `operator`, or `siswa`. Share initial access through the school's usual private channel; users can recover their password by email.
4. Add student records in **Data siswa** using exactly the same email as the account. CSV import supports up to 500 rows per batch. NISN and participant numbers remain strings so leading zeros are retained. The template names are `email`, `nisn`, `examNumber`, `fullName`, `classId`, and `gender`; use the class ID in the database, and `L` or `P` for gender. CSV import updates an existing record by NISN and does not create Identity accounts.
5. Create a question bank as the teacher who owns it or as an administrator. Teachers can manage only their own banks and associated exams. Add questions using **Soal**, or import JSON, tagged text, or `.docx`.
6. Create an exam, choose its classes and time window, and activate it. Schedule inputs use the browser's local timezone; the server stores timestamps with timezone. An active exam needs questions and a valid class selection.
7. The administrator or operator selects the exam in **Token ujian**, then releases a token with an expiration time. Students enter that token to begin.

## Exam behavior

Each attempt stores an immutable snapshot of its selected questions, keys, weights, and randomized order. Bank edits therefore do not alter existing attempts. Optional bank composition selects a count per question type and overrides its weight. Scores are normalized to 0–100 using the total weight of the selected questions, including unanswered ones.

All six types are supported: single choice, complex choice, matching, true/false, short answer, and essay. Complex choice uses proportional credit with a half-point penalty per incorrect selection, bounded at zero. Matching and true/false award proportional credit. Short answers support alternatives and case sensitivity. Essays require manual grading.

Answers are saved to the database after a short typing pause. Navigation and submission flush pending saves; failures remain visible and can be retried. The countdown uses a server-issued deadline. Reloading restores saved answers and question order. Server checks reject answers after expiry, finalize expired attempts when accessed, and prevent edits after submission. Monitoring also finalizes expired attempts. Idle expired attempts are finalized the next time the student or monitoring accesses them.

A student account can claim one browser session. The operator can reset that claim after a device or browser problem. Tab visibility changes are recorded when monitoring is enabled; the configured threshold blocks further work until an operator opens access. This is browser activity monitoring, not an operating-system lockdown tool.

Monitoring refreshes every four seconds and includes reset login, extend time, unblock, and force submit. The results screen includes manual essay grading, CSV export, printing, difficulty, discrimination, and response distributions. Student scores are visible only when the exam's results setting allows them. Teacher answer keys and rubrics are excluded from student responses. Participant cards use email login and never print passwords.

## Moving existing data

This deployment starts with an empty database. It does not connect to the previous MySQL server, copy production data, or migrate existing passwords, attempts, and exam history automatically. Retain a backup of that system.

Export student records to the documented CSV format after creating their target classes. Recreate login accounts with email addresses in Identity; old username/NISN password logins do not carry over. Import question JSON arrays through each bank's editor. The importer accepts both the new field names (`text`, `options`, `correctAnswer`, `weight`, `number`) and the legacy names (`question_text`, `options_data`, `correct_answer`, `score_weight`, `question_number`), including JSON-encoded options and legacy option arrays. The editor provides a template for all six types.

Word imports support `.docx` and the original `[SOAL]`, type section, `[A]`–`[E]`, `[KUNCI]`, `[PREMIS N]`, `[TARGET N]`, `[PERNYATAAN N]`, and `[RUBRIK]` tags. The current text template also supports `[TIPE]`, `[BOBOT]`, and `[OPSI]` JSON. Convert binary `.doc` files to `.docx`, and Excel student spreadsheets to CSV, before importing. Imported content displays as plain text rather than executing legacy HTML. Question images use an HTTPS URL; uploaded assets on the old PHP host need to be moved to a reachable location and their URLs updated.

The new interface does not restore historical MySQL reports or custom PHP layouts. Keep the original installation available as an archive until the school has verified its imported question banks, participants, scoring, and schedules.

## Validation

Run `npm ci`, `npm run check`, and `npm test` for local checks. Tests cover role restrictions, cross-origin mutation rejection, scoring, imports, and simulated browser start/save/submit flows, including failed-save recovery. Netlify validates the production build automatically. A real end-to-end acceptance check after deployment still needs the initial administrator and test teacher, operator, and student accounts, plus the provisioned database.
