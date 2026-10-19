-- =============================================================
--  College Quiz System — database schema + demo data
--  Works on XAMPP (MariaDB/MySQL) and InfinityFree (MySQL).
--
--  HOW TO IMPORT
--  1. Create an empty database first:
--       XAMPP:        phpMyAdmin > New > name it  college_quiz
--       InfinityFree: Control Panel > MySQL Databases > create one
--                     (it will be named like if0_12345678_college_quiz)
--  2. Select that database in phpMyAdmin, open the Import tab,
--     choose this file and click Import.
--
--  This file does NOT contain CREATE DATABASE / USE on purpose,
--  because InfinityFree does not allow them.
--
--  Demo logins (change these after your first login):
--     Admin   : ADMIN001  /  Admin@123
--     Student : STU001    /  Student@123
--     Student : STU002    /  Student@123
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `attempt_answers`;
DROP TABLE IF EXISTS `attempts`;
DROP TABLE IF EXISTS `materials`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- Users (admins and students)
-- -------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reg_number`    VARCHAR(50)  NOT NULL,
  `first_name`    VARCHAR(80)  NOT NULL,
  `last_name`     VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL DEFAULT '',
  `phone`         VARCHAR(30)  NOT NULL DEFAULT '',
  `role`          ENUM('admin','student') NOT NULL DEFAULT 'student',
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_reg` (`reg_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Courses (also drive the weekly timetable)
-- -------------------------------------------------------------
CREATE TABLE `courses` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(20)  NOT NULL,
  `title`         VARCHAR(150) NOT NULL,
  `lecturer`      VARCHAR(120) NOT NULL DEFAULT '',
  `class_day`     ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL DEFAULT 'Monday',
  `start_time`    TIME         NOT NULL DEFAULT '08:00:00',
  `end_time`      TIME         NOT NULL DEFAULT '10:00:00',
  `venue`         VARCHAR(100) NOT NULL DEFAULT '',
  `quiz_minutes`  SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `max_attempts`  SMALLINT UNSIGNED NOT NULL DEFAULT 3,   -- 0 = unlimited
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Quiz questions (belong to a course)
-- -------------------------------------------------------------
CREATE TABLE `questions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`      INT UNSIGNED NOT NULL,
  `question`       TEXT         NOT NULL,
  `option_a`       VARCHAR(255) NOT NULL,
  `option_b`       VARCHAR(255) NOT NULL,
  `option_c`       VARCHAR(255) NOT NULL,
  `option_d`       VARCHAR(255) NOT NULL,
  `correct_option` CHAR(1)      NOT NULL,                 -- 'A','B','C' or 'D'
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_questions_course` (`course_id`),
  CONSTRAINT `fk_questions_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Study materials (files live in uploads/materials/)
-- -------------------------------------------------------------
CREATE TABLE `materials` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id`     INT UNSIGNED NOT NULL,
  `title`         VARCHAR(150) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name`   VARCHAR(100) NOT NULL,
  `file_size`     INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by`   INT UNSIGNED NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_materials_course` (`course_id`),
  CONSTRAINT `fk_materials_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materials_user`   FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Quiz attempts — one row per student per sitting
-- -------------------------------------------------------------
CREATE TABLE `attempts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `course_id`     INT UNSIGNED NOT NULL,
  `started_at`    DATETIME     NOT NULL,
  `deadline_at`   DATETIME     NOT NULL,
  `submitted_at`  DATETIME     NULL,
  `score`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `total`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `late`          TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user`   (`user_id`),
  KEY `idx_attempts_course` (`course_id`),
  CONSTRAINT `fk_attempts_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_attempts_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Each question served in an attempt + the student's choice
-- -------------------------------------------------------------
CREATE TABLE `attempt_answers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id`    INT UNSIGNED NOT NULL,
  `question_id`   INT UNSIGNED NOT NULL,
  `position`      SMALLINT UNSIGNED NOT NULL,
  `chosen_option` CHAR(1)      NULL,
  `is_correct`    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_aa_question` (`question_id`),
  CONSTRAINT `fk_aa_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `attempts` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_aa_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
--  DEMO DATA
-- =============================================================

INSERT INTO `users` (`id`, `reg_number`, `first_name`, `last_name`, `email`, `phone`, `role`, `password_hash`) VALUES
(1, 'ADMIN001', 'Admin', 'User', 'admin@example.com', '', 'admin',   '$2a$12$w3Ygkf6I3m4tiP4Dt4YO8eUXI78Ypfn7kiSEPydcKgIU0eLjds2Vq'),
(2, 'STU001',   'Amara',  'Okafor',        'amara@example.com', '', 'student', '$2a$12$w3Ygkf6I3m4tiP4Dt4YO8eUXI78Ypfn7kiSEPydcKgIU0eLjds2Vq'),
(3, 'STU002',   'Tunde',  'Bello',         'tunde@example.com', '', 'student', '$2a$12$w3Ygkf6I3m4tiP4Dt4YO8eUXI78Ypfn7kiSEPydcKgIU0eLjds2Vq');

INSERT INTO `courses` (`id`, `code`, `title`, `lecturer`, `class_day`, `start_time`, `end_time`, `venue`, `quiz_minutes`, `max_attempts`) VALUES
(1, 'CSC101', 'Introduction to Computing',  'Mr. E. Ebong', 'Wednesday', '13:00:00', '15:00:00', 'Lab 2',          10, 3),
(2, 'BIO101', 'General Biology I',          'Mrs. A. Abu',  'Monday',    '08:00:00', '10:00:00', 'Lecture Hall A', 10, 3),
(3, 'CHM101', 'General Chemistry I',        'Mr. O. Ehis',  'Tuesday',   '14:00:00', '16:00:00', 'Lecture Hall B', 10, 3);

INSERT INTO `questions` (`course_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES
(1, 'What does PHP stand for?', 'Preprocessed Hypertext Page', 'Hypertext Markup Language', 'Hypertext Preprocessor', 'Hypertext Transfer Protocol', 'C'),
(1, 'Which of these is a server-side scripting language?', 'HTML', 'CSS', 'JavaScript (in the browser)', 'PHP', 'D'),
(1, 'Which PHP function returns a list of response headers sent (or ready to send)?', 'header()', 'headers_list()', 'headers_sent()', 'header_send()', 'B'),
(1, 'What does CPU stand for?', 'Central Processing Unit', 'Computer Personal Unit', 'Central Program Utility', 'Control Processing Unit', 'A'),
(1, 'Which of these is an example of system software?', 'Microsoft Word', 'Google Chrome', 'Windows 11', 'Adobe Photoshop', 'C'),
(1, 'How many bits make one byte?', '4', '8', '16', '32', 'B'),
(2, 'What is the basic unit of life?', 'Tissue', 'Organ', 'Cell', 'Molecule', 'C'),
(2, 'Which organelle is known as the powerhouse of the cell?', 'Nucleus', 'Mitochondrion', 'Ribosome', 'Golgi body', 'B'),
(2, 'Photosynthesis mainly takes place in the…', 'Roots', 'Stem', 'Chloroplasts of leaves', 'Flowers', 'C'),
(2, 'Which molecule carries genetic information in most living things?', 'ATP', 'DNA', 'Glucose', 'Protein', 'B'),
(2, 'Which blood cells help fight infection?', 'Red blood cells', 'Platelets', 'White blood cells', 'Plasma', 'C'),
(3, 'What is the chemical symbol for sodium?', 'S', 'So', 'Na', 'Sd', 'C'),
(3, 'What is the pH of pure water at 25 °C?', '0', '7', '10', '14', 'B'),
(3, 'Which particle carries a negative charge?', 'Proton', 'Neutron', 'Electron', 'Nucleus', 'C'),
(3, 'H2O is the chemical formula for…', 'Hydrogen peroxide', 'Water', 'Hydrochloric acid', 'Oxygen gas', 'B'),
(3, 'Which gas do plants absorb from the air for photosynthesis?', 'Oxygen', 'Nitrogen', 'Carbon dioxide', 'Helium', 'C');
