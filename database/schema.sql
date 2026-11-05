-- Schema Database Aplikasi CBT (Computer-Based Test)
-- Support 6 Jenis Soal: Pilgan, PG Kompleks, Menjodohkan, Benar/Salah, Isian Singkat, Uraian
-- Roles: Admin, Guru, Operator, Siswa

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `exam_logs`;
DROP TABLE IF EXISTS `student_answers`;
DROP TABLE IF EXISTS `exam_participations`;
DROP TABLE IF EXISTS `exam_tokens`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `question_banks`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;

-- 1. Table Users (Admin, Guru, Operator)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('admin', 'guru', 'operator') NOT NULL DEFAULT 'guru',
  `nip` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(25) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table Classes (Kelas & Rombel)
CREATE TABLE `classes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `level` VARCHAR(20) NOT NULL,
  `major` VARCHAR(50) DEFAULT 'Umum',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table Subjects (Mata Pelajaran)
CREATE TABLE `subjects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table Students (Siswa Peserta)
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nisn` VARCHAR(30) NOT NULL UNIQUE,
  `exam_number` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `class_id` INT NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `gender` ENUM('L', 'P') DEFAULT 'L',
  `is_logged_in` TINYINT(1) DEFAULT 0,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table Question Banks (Bank Soal)
CREATE TABLE `question_banks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(200) NOT NULL,
  `subject_id` INT NOT NULL,
  `teacher_id` INT NOT NULL,
  `class_level` VARCHAR(20) DEFAULT 'Semua',
  `total_questions` INT DEFAULT 0,
  `kkm` DECIMAL(5,2) DEFAULT 75.00,
  `question_settings` LONGTEXT NULL, -- JSON komposisi jumlah soal yang ditampilkan dan bobot nilai per tipe
  `status` ENUM('active', 'draft') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table Questions (Soal Ujian - 6 Tipe Soal)
-- type: 'pilgan', 'kompleks', 'jodohkan', 'benarsalah', 'isian', 'uraian'
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_id` INT NOT NULL,
  `question_number` INT NOT NULL,
  `type` ENUM('pilgan', 'kompleks', 'jodohkan', 'benarsalah', 'isian', 'uraian') NOT NULL,
  `question_text` LONGTEXT NOT NULL,
  `question_image` VARCHAR(255) NULL,
  `options_data` LONGTEXT NULL, -- JSON untuk opsi pilihan, premis-target, atau pernyataan B/S
  `correct_answer` LONGTEXT NULL, -- Kunci jawaban (JSON atau teks)
  `score_weight` DECIMAL(5,2) DEFAULT 1.00,
  `explanation` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table Exams (Jadwal & Paket Ujian)
CREATE TABLE `exams` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `bank_id` INT NOT NULL,
  `class_ids` TEXT NULL, -- comma separated atau JSON kelas yang berhak ikut
  `session_name` VARCHAR(50) DEFAULT 'Sesi 1',
  `room` VARCHAR(50) DEFAULT 'Ruang 01',
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 60,
  `current_token` VARCHAR(10) DEFAULT 'ABCXYZ',
  `is_token_active` TINYINT(1) DEFAULT 1,
  `randomize_questions` TINYINT(1) DEFAULT 1,
  `randomize_options` TINYINT(1) DEFAULT 1,
  `show_results_immediately` TINYINT(1) DEFAULT 0,
  `anti_cheat_enabled` TINYINT(1) DEFAULT 1,
  `status` ENUM('draft', 'active', 'finished') DEFAULT 'active',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bank_id`) REFERENCES `question_banks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table Exam Tokens (Riwayat & Token Dinamis Operator)
CREATE TABLE `exam_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `token` VARCHAR(10) NOT NULL,
  `generated_by` INT NOT NULL,
  `valid_until` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table Exam Participations (Sesi Siswa Mengerjakan Ujian)
CREATE TABLE `exam_participations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `start_time` DATETIME NULL,
  `end_time` DATETIME NULL,
  `remaining_seconds` INT NOT NULL DEFAULT 3600,
  `status` ENUM('not_started', 'in_progress', 'submitted', 'blocked') DEFAULT 'not_started',
  `score_total` DECIMAL(5,2) DEFAULT 0.00,
  `score_pilgan` DECIMAL(5,2) DEFAULT 0.00,
  `score_kompleks` DECIMAL(5,2) DEFAULT 0.00,
  `score_jodohkan` DECIMAL(5,2) DEFAULT 0.00,
  `score_benarsalah` DECIMAL(5,2) DEFAULT 0.00,
  `score_isian` DECIMAL(5,2) DEFAULT 0.00,
  `score_uraian` DECIMAL(5,2) DEFAULT 0.00,
  `is_graded` TINYINT(1) DEFAULT 0,
  `violation_count` INT DEFAULT 0,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `question_order` TEXT NULL, -- Acakan urutan soal khusus peserta
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Table Student Answers (Jawaban Siswa Per Soal)
CREATE TABLE `student_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `participation_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `student_answer` LONGTEXT NULL, -- JSON atau teks jawaban
  `is_marked_doubtful` TINYINT(1) DEFAULT 0, -- Status ragu-ragu (1 = ragu, 0 = yakin)
  `is_correct` TINYINT(1) NULL, -- 1 = benar, 0 = salah, NULL = belum diperiksa (uraian)
  `awarded_score` DECIMAL(5,2) DEFAULT 0.00,
  `teacher_feedback` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`participation_id`) REFERENCES `exam_participations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Table Exam Logs (Monitoring & Log Anti-Curang Operator)
CREATE TABLE `exam_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `participation_id` INT NULL,
  `student_id` INT NOT NULL,
  `exam_id` INT NOT NULL,
  `event_type` VARCHAR(50) NOT NULL, -- 'login', 'start', 'tab_switch', 'fullscreen_exit', 'save_answer', 'reset_login', 'time_extend'
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Table Settings (Pengaturan Aplikasi)
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
