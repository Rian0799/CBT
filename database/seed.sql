-- Seed Data Aplikasi CBT
-- Password default untuk testing:
-- admin: admin123
-- guru: guru123
-- operator: operator123
-- siswa: siswa123

-- 1. Insert Users (Password hash bcrypt)
-- admin123 -> $2y$10$eE0mQYv0uV1z6q2NqB8zXeZJvXyZtO2kUjZlP8p2/1xVj1N2u0kGe
-- Untuk fleksibilitas dan kompatibilitas maksimal:
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `nip`, `email`, `phone`, `status`) VALUES
('admin', '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'Administrator Utama', 'admin', '198501012010011001', 'admin@sekolah.sch.id', '081234567890', 'active'),
('guru', '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'Budi Santoso, S.Kom., M.Pd.', 'guru', '198805122015021003', 'budi.santoso@sekolah.sch.id', '081234567891', 'active'),
('operator', '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'Rian Proktor & Operator', 'operator', '199507202022031005', 'operator@sekolah.sch.id', '081234567892', 'active');

-- 2. Insert Classes (Contoh Multi-Jenjang: SD/MI, SMP/MTs, SMA/SMK/MA)
INSERT INTO `classes` (`name`, `level`, `major`) VALUES
('5 SD Negeri 1', '5', 'Tematik / Umum'),
('8 MTs Unggulan', '8', 'Umum'),
('X RPL 1', '10', 'Rekayasa Perangkat Lunak'),
('XI RPL 2', '11', 'Rekayasa Perangkat Lunak'),
('XII MIPA 1', '12', 'MIPA');

-- 3. Insert Subjects (Contoh Berbagai Jenjang)
INSERT INTO `subjects` (`code`, `name`) VALUES
('WEB-01', 'Pemrograman Web dan Perangkat Bergerak'),
('BIN-01', 'Bahasa Indonesia'),
('MAT-01', 'Matematika Terapan'),
('IPA-08', 'Ilmu Pengetahuan Alam (IPA Terpadu)'),
('TEM-05', 'Tematik Terpadu & IPAS');

-- 4. Insert Students (password: siswa123)
INSERT INTO `students` (`nisn`, `exam_number`, `full_name`, `class_id`, `password`, `gender`) VALUES
('0061234501', 'UJN-2026-001', 'Ahmad Fadillah', 1, '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'L'),
('0061234502', 'UJN-2026-002', 'Bella Safitri', 1, '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'P'),
('0061234503', 'UJN-2026-003', 'Dimas Prasetyo', 1, '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'L'),
('0061234504', 'UJN-2026-004', 'Eka Nuraini', 1, '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'P'),
('0061234505', 'UJN-2026-005', 'Fauzan Akbar', 2, '$2y$10$wT0lVqKz3jRzQx7gE6aN3.f7H5rF0bZ8Yq9mD4oP1uL6kS2vT7y6S', 'L');

-- 5. Insert Question Bank
INSERT INTO `question_banks` (`code`, `title`, `subject_id`, `teacher_id`, `class_level`, `total_questions`, `kkm`, `question_settings`, `status`) VALUES
('BANK-WEB-2026', 'Asesmen Standar Kompetensi Pemrograman Web & Aplikasi', 1, 2, '10', 6, 75.00, '{"is_configured":1,"pilgan":{"active":1,"count":1,"weight":15},"kompleks":{"active":1,"count":1,"weight":20},"jodohkan":{"active":1,"count":1,"weight":20},"benarsalah":{"active":1,"count":1,"weight":15},"isian":{"active":1,"count":1,"weight":10},"uraian":{"active":1,"count":1,"weight":20},"total_questions":6,"total_weight":100}', 'active');

-- 6. Insert 6 Different Question Types
-- Soal 1: Pilihan Ganda (Pilgan)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 1, 'pilgan', 
'<p>Protokol jaringan yang digunakan secara standar untuk mentransfer dokumen web (hypertext) secara terenkripsi dan aman adalah...</p>', 
'{"A":"FTP (File Transfer Protocol)","B":"HTTP (Hypertext Transfer Protocol)","C":"HTTPS (Hypertext Transfer Protocol Secure)","D":"SMTP (Simple Mail Transfer Protocol)","E":"SSH (Secure Shell)"}', 
'"C"', 
15.00, 
'HTTPS menggunakan enkripsi SSL/TLS untuk mengamankan komunikasi data antara browser klien dan server web.');

-- Soal 2: Pilihan Ganda Kompleks (Kompleks)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 2, 'kompleks', 
'<p>Manakah dari teknologi berikut yang dieksekusi secara native di sisi klien (Client-Side) di dalam peramban web? <em>(Pilih semua jawaban yang benar)</em></p>', 
'[{"key":"A","text":"JavaScript"},{"key":"B","text":"PHP"},{"key":"C","text":"HTML & CSS"},{"key":"D","text":"Python Django"}]', 
'["A","C"]', 
20.00, 
'HTML, CSS, dan JavaScript dieksekusi langsung oleh engine peramban (client-side), sedangkan PHP dan Python dieksekusi di web server (server-side).');

-- Soal 3: Menjodohkan (Matching)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 3, 'jodohkan', 
'<p>Pasangkanlah teknologi web di kolom kiri dengan fungsi utamanya yang tepat di kolom kanan!</p>', 
'{"premises":[{"id":"p1","text":"HTML5"},{"id":"p2","text":"CSS3"},{"id":"p3","text":"MySQL"}],"targets":[{"id":"t1","text":"Mengatur tata letak, warna, dan tampilan visual"},{"id":"t2","text":"Menyimpan dan mengelola basis data relasional"},{"id":"t3","text":"Menyusun struktur dan semantik konten halaman web"},{"id":"t4","text":"Mengatur routing IP address jaringan"}]}', 
'{"p1":"t3","p2":"t1","p3":"t2"}', 
20.00, 
'HTML menyusun struktur dokumen, CSS memperindah tampilan styling, dan MySQL mengelola penyimpanan data relasional.');

-- Soal 4: Pilihan Benar / Salah (BenarSalah)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 4, 'benarsalah', 
'<p>Tentukan apakah setiap pernyataan berikut mengenai arsitektur web bernilai <strong>Benar</strong> atau <strong>Salah</strong>!</p>', 
'[{"id":"s1","text":"Apache adalah web server yang berfungsi menerima permintaan HTTP dari klien dan mengirimkan kembali responnya."},{"id":"s2","text":"Kode program PHP dapat dilihat langsung oleh pengguna melalui fitur Inspect Element pada browser."},{"id":"s3","text":"Database MySQL dapat diakses dan dikelola secara visual menggunakan phpMyAdmin."}]', 
'{"s1":"B","s2":"S","s3":"B"}', 
15.00, 
'Kode PHP diproses di server dan hanya menghasilkan output HTML/JSON ke browser, sehingga kode aslinya tidak dapat dilihat melalui Inspect Element.');

-- Soal 5: Isian Singkat (Isian)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 5, 'isian', 
'<p>Tuliskan nama tag HTML dasar yang digunakan untuk membuat tautan atau hyperlink ke halaman lain <em>(tuliskan huruf tag-nya saja tanpa kurung siku, contoh: p)</em>:</p>', 
'{"case_sensitive":false,"placeholder":"Ketik jawaban singkat disini..."}', 
'["a","tag a","<a>"]', 
10.00, 
'Tag <a> (anchor) digunakan bersama atribut href untuk mendefinisikan pranala atau hyperlink.');

-- Soal 6: Uraian / Essay (Uraian)
INSERT INTO `questions` (`bank_id`, `question_number`, `type`, `question_text`, `options_data`, `correct_answer`, `score_weight`, `explanation`) VALUES
(1, 6, 'uraian', 
'<p>Jelaskan secara komprehensif apa yang dimaksud dengan <strong>Model-View-Controller (MVC)</strong> dalam rekayasa perangkat lunak web, dan sebutkan fungsi masing-masing komponennya!</p>', 
'{"max_score":20,"rubric":"Penjelasan konsep MVC (5 poin), Peran Model (5 poin), Peran View (5 poin), Peran Controller (5 poin)."}', 
'"Jawaban ideal memuat: MVC adalah pola arsitektur perangkat lunak. Model: mengelola data dan logika bisnis. View: menyajikan tampilan antarmuka ke user. Controller: perantara pengendali alur request dari view ke model dan sebaliknya."', 
20.00, 
'Koreksi uraian dinilai manual oleh Guru berdasarkan kelengkapan penjabaran Model, View, dan Controller.');

-- 7. Insert Active Exam
INSERT INTO `exams` (`title`, `bank_id`, `class_ids`, `session_name`, `room`, `start_time`, `end_time`, `duration_minutes`, `current_token`, `is_token_active`, `randomize_questions`, `randomize_options`, `show_results_immediately`, `anti_cheat_enabled`, `status`, `created_by`) VALUES
('Penilaian Akhir Semester (PAS) - Pemrograman Web', 1, '["1","2"]', 'Sesi 1 Pagi', 'Lab Komputer 1', NOW() - INTERVAL 1 HOUR, NOW() + INTERVAL 8 HOUR, 60, 'SMART1', 1, 1, 1, 1, 1, 'active', 1);

-- 8. Insert Initial Token
INSERT INTO `exam_tokens` (`exam_id`, `token`, `generated_by`, `valid_until`) VALUES
(1, 'SMART1', 3, NOW() + INTERVAL 4 HOUR);

-- 9. Insert Initial Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('school_name', 'Lembaga Pendidikan Unggulan Nusantara'),
('app_title', 'CBT SMART EXAM PRO'),
('institution_level', 'all'), -- all (Semua Jenjang SD/MI, SMP/MTs, SMA/SMK/MA), sd_mi, smp_mts, sma_ma, smk
('academic_year', '2025/2026'),
('semester', 'Genap'),
('anti_cheat_strictness', 'medium'), -- low, medium, high
('max_tab_violations', '3'),
('token_auto_refresh_minutes', '15'),
('allow_student_review', '1');
