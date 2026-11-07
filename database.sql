-- Lab 01 - Student Registration
-- Import with: mysql -u root -p < database.sql

CREATE DATABASE IF NOT EXISTS lab01_student_registration
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab01_student_registration;

CREATE TABLE IF NOT EXISTS students (
  student_id     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  full_name      VARCHAR(80)   NOT NULL,
  email          VARCHAR(150)  NOT NULL,
  telephone      CHAR(9)       NOT NULL,           -- 9 local digits, e.g. 671234567
  date_of_birth  DATE          NOT NULL,
  programme      VARCHAR(60)   NOT NULL,
  level          VARCHAR(10)   NOT NULL,
  gender         VARCHAR(10)   NULL,
  policy_agreed  TINYINT(1)    NOT NULL DEFAULT 0,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_students_email (email)             -- rejects duplicate emails at DB level
) ENGINE=InnoDB;

-- Verification queries (run after submitting the form)
-- SELECT * FROM students ORDER BY student_id DESC;
-- SELECT email, COUNT(*) FROM students GROUP BY email HAVING COUNT(*) > 1;  -- must return 0 rows
