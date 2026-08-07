-- ============================================================
--  College Bill Generation System (BGS) — Full Database Schema
--  Government College of Engineering, Aurangabad
--  Import via phpMyAdmin > Import, or: mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS college_bill_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE college_bill_system;

-- ============================================================
-- TABLE 1: departments
-- Added by Admin. HOD + teachers are assigned to one dept.
-- ============================================================
CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150)    NOT NULL,
    short_name  VARCHAR(20)     NOT NULL,          -- e.g. CSE, IT, MECH
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 2: classes
-- Year + Semester combinations, linked to a department.
-- Admin manages these.
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED  NOT NULL,
    year          TINYINT       NOT NULL,           -- 1 to 4
    semester      TINYINT       NOT NULL,           -- 1 to 8
    label         VARCHAR(80)   NOT NULL,           -- e.g. "SY CSE Sem 3"
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY uq_class (department_id, year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 3: subjects
-- Admin creates subjects and links to class + mode.
-- Teachers are then assigned a subject.
-- ============================================================
CREATE TABLE IF NOT EXISTS subjects (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    class_id      INT UNSIGNED  NOT NULL,
    subject_name  VARCHAR(150)  NOT NULL,
    subject_code  VARCHAR(30)   NOT NULL,           -- e.g. CS301
    mode          ENUM('theory','practical','theory & practical') NOT NULL DEFAULT 'theory',
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 4: users
-- Covers all roles: admin, hod, teacher, student (Earn & Learn)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id                    INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(100)   NOT NULL,
    email                 VARCHAR(100)   NOT NULL UNIQUE,
    password              VARCHAR(255)   NOT NULL,
    role                  ENUM('admin','hod','teacher','student') NOT NULL,

    -- Department link (for HOD, teacher, student)
    department_id         INT UNSIGNED   DEFAULT NULL,

    -- Teacher-specific fields
    teacher_type          ENUM('regular','expert','sectional_expert','adjunct') DEFAULT NULL,
    teacher_mode          ENUM('theory','practical','theory & practical') DEFAULT NULL,
    subject_id            INT UNSIGNED   DEFAULT NULL,  -- assigned theory subject
    subject_id_2          INT UNSIGNED   DEFAULT NULL,  -- assigned practical subject (for Theory & Practical mode)
    appointment_order_no  VARCHAR(100)   DEFAULT NULL,
    rate_theory           DECIMAL(8,2)   DEFAULT 0.00,  -- rate per theory hour
    rate_practical        DECIMAL(8,2)   DEFAULT 0.00,  -- rate per practical hour
    rate_other            DECIMAL(8,2)   DEFAULT 0.00,  -- rate for other work

    -- Student Earn & Learn specific
    class_id              INT UNSIGNED   DEFAULT NULL,
    rate_per_hour         DECIMAL(8,2)   DEFAULT 0.00,

    -- Contact
    phone                 VARCHAR(20)    DEFAULT NULL,

    -- Bank details
    bank_name             VARCHAR(100)   DEFAULT NULL,
    account_no            VARCHAR(30)    DEFAULT NULL,
    ifsc                  VARCHAR(15)    DEFAULT NULL,
    pan                   VARCHAR(15)    DEFAULT NULL,

    -- Profile & KYC documents
    profile_photo         VARCHAR(255)   DEFAULT NULL,  -- path to uploaded photo
    pan_image             VARCHAR(255)   DEFAULT NULL,  -- path to PAN card scan
    aadhar_image          VARCHAR(255)   DEFAULT NULL,  -- path to Aadhar scan
    appointment_image     VARCHAR(255)   DEFAULT NULL,  -- path to Appointment Order letter scan

    is_active             TINYINT(1)     NOT NULL DEFAULT 1,
    created_at            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (subject_id)    REFERENCES subjects(id)    ON DELETE SET NULL,
    FOREIGN KEY (subject_id_2)  REFERENCES subjects(id)    ON DELETE SET NULL,
    FOREIGN KEY (class_id)      REFERENCES classes(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 5: timetable
-- HOD assigns teacher → class + subject + day/time slot
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED  NOT NULL,
    class_id      INT UNSIGNED  NOT NULL,
    subject_id    INT UNSIGNED  NOT NULL,
    teacher_id    INT UNSIGNED  NOT NULL,
    day_of_week   TINYINT       NOT NULL,           -- 1=Mon … 6=Sat
    time_slot     VARCHAR(20)   NOT NULL,           -- e.g. "09:00-10:00"
    mode          ENUM('theory','practical','theory & practical')        NOT NULL DEFAULT 'theory',
    academic_year VARCHAR(10)   NOT NULL,           -- e.g. "2025-26"
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)      REFERENCES classes(id)     ON DELETE CASCADE,
    FOREIGN KEY (subject_id)    REFERENCES subjects(id)    ON DELETE CASCADE,
    FOREIGN KEY (teacher_id)    REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 6: lectures
-- Teacher records each session (theory / practical / other hrs)
-- ============================================================
CREATE TABLE IF NOT EXISTS lectures (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    teacher_id      INT UNSIGNED  NOT NULL,
    subject_id      INT UNSIGNED  NOT NULL,
    class_id        INT UNSIGNED  DEFAULT NULL,
    lecture_date    DATE          NOT NULL,
    theory_hours    DECIMAL(4,1)  NOT NULL DEFAULT 0.0,
    practical_hours DECIMAL(4,1)  NOT NULL DEFAULT 0.0,
    other_hours     DECIMAL(4,1)  NOT NULL DEFAULT 0.0,
    notes           TEXT          DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 7: bills
-- One bill per teacher per month/semester submission
-- ============================================================
CREATE TABLE IF NOT EXISTS bills (
    id                  INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    teacher_id          INT UNSIGNED  NOT NULL,
    generated_by        INT UNSIGNED  DEFAULT NULL,  -- NULL = teacher self; HOD id = manual
    month_year          VARCHAR(20)   NOT NULL,      -- e.g. "March 2026"
    period_from         DATE          NOT NULL,
    period_to           DATE          NOT NULL,
    academic_year       VARCHAR(10)   DEFAULT NULL,  -- e.g. "2025-26"

    -- Computed hour totals
    total_theory_hrs    DECIMAL(6,1)  NOT NULL DEFAULT 0.0,
    total_practical_hrs DECIMAL(6,1)  NOT NULL DEFAULT 0.0,
    total_other_hrs     DECIMAL(6,1)  NOT NULL DEFAULT 0.0,

    -- Rates locked at time of bill generation
    rate_theory         DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    rate_practical      DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    rate_other          DECIMAL(8,2)  NOT NULL DEFAULT 0.00,

    -- Computed amounts
    theory_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    practical_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    other_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    status              ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
    rejection_reason    TEXT          DEFAULT NULL,

    submitted_at        TIMESTAMP     NULL DEFAULT NULL,
    reviewed_at         TIMESTAMP     NULL DEFAULT NULL,
    reviewed_by         INT UNSIGNED  DEFAULT NULL,

    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 8: bill_lectures
-- Links a bill to its specific lecture entries (many-to-many)
-- ============================================================
CREATE TABLE IF NOT EXISTS bill_lectures (
    bill_id    INT UNSIGNED NOT NULL,
    lecture_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (bill_id, lecture_id),
    FOREIGN KEY (bill_id)    REFERENCES bills(id)    ON DELETE CASCADE,
    FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 9: student_work
-- Earn & Learn student records daily work hours
-- ============================================================
CREATE TABLE IF NOT EXISTS student_work (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    student_id  INT UNSIGNED  NOT NULL,
    work_date   DATE          NOT NULL,
    hours       DECIMAL(4,1)  NOT NULL DEFAULT 0.0,
    description VARCHAR(255)  DEFAULT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 10: student_bills
-- Earn & Learn monthly bill (separate from teacher bills)
-- ============================================================
CREATE TABLE IF NOT EXISTS student_bills (
    id               INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    student_id       INT UNSIGNED  NOT NULL,
    month_year       VARCHAR(20)   NOT NULL,
    period_from      DATE          NOT NULL,
    period_to        DATE          NOT NULL,
    total_hours      DECIMAL(6,1)  NOT NULL DEFAULT 0.0,
    rate_per_hour    DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    total_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT          DEFAULT NULL,
    submitted_at     TIMESTAMP     NULL DEFAULT NULL,
    reviewed_at      TIMESTAMP     NULL DEFAULT NULL,
    reviewed_by      INT UNSIGNED  DEFAULT NULL,
    FOREIGN KEY (student_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 11: other_bills
-- HOD-created bills: practical exam, earn & learn batch, seminar
-- ============================================================
CREATE TABLE IF NOT EXISTS other_bills (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    bill_type     ENUM('practical','earn_learn','seminar') NOT NULL,
    created_by    INT UNSIGNED  NOT NULL,
    title         VARCHAR(200)  NOT NULL,
    claimant_name VARCHAR(150)  NOT NULL,
    department_id INT UNSIGNED  DEFAULT NULL,
    bill_date     DATE          NOT NULL,
    total_amount  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    bill_data     JSON          NOT NULL,             -- all form fields
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by)    REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 12: fund_requests
-- HOD requests funds from Admin
-- ============================================================
CREATE TABLE IF NOT EXISTS fund_requests (
    id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    hod_id        INT UNSIGNED  NOT NULL,
    department_id INT UNSIGNED  NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    purpose       TEXT          NOT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note    TEXT          DEFAULT NULL,
    reviewed_by   INT UNSIGNED  DEFAULT NULL,
    requested_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (hod_id)       REFERENCES users(id)       ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by)  REFERENCES users(id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE 13: activity_log
-- Audit trail for all key actions
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  DEFAULT NULL,
    action      VARCHAR(100)  NOT NULL,
    description TEXT          DEFAULT NULL,
    ip_address  VARCHAR(45)   DEFAULT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INDEXES for performance
-- ============================================================
CREATE INDEX idx_users_role         ON users(role);
CREATE INDEX idx_users_dept         ON users(department_id);
CREATE INDEX idx_lectures_teacher   ON lectures(teacher_id);
CREATE INDEX idx_lectures_date      ON lectures(lecture_date);
CREATE INDEX idx_bills_teacher      ON bills(teacher_id);
CREATE INDEX idx_bills_status       ON bills(status);
CREATE INDEX idx_other_bills_type   ON other_bills(bill_type);
CREATE INDEX idx_fund_req_status    ON fund_requests(status);
CREATE INDEX idx_activity_user      ON activity_log(user_id);
CREATE INDEX idx_activity_at        ON activity_log(created_at);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin account
INSERT INTO users (name, email, password, role) VALUES (
    'Super Admin',
    'admin@gcea.edu',
    '$2y$10$Vs8FtyKgr/xe4fmJqLZyC.XUczvb/3RYVX88m4qxAJegCy9lN8lsq',  -- admin@1234
    'admin'
);

-- 7 Departments
INSERT INTO departments (name, short_name) VALUES
('Civil Engineering',                  'CE'),
('Electrical Engineering',             'EE'),
('Mechanical Engineering',             'MECH'),
('Electronics & Telecommunication',    'ENTC'),
('Computer Science & Engineering',     'CSE'),
('Information Technology',             'IT'),
('Master in Computer Application',     'MCA');

-- -- Classes for CSE (department_id = 5)
-- INSERT INTO classes (department_id, year, semester, label) VALUES
-- (5, 1, 1, 'FY CSE Sem 1'),
-- (5, 1, 2, 'FY CSE Sem 2'),
-- (5, 2, 3, 'SY CSE Sem 3'),
-- (5, 2, 4, 'SY CSE Sem 4'),
-- (5, 3, 5, 'TY CSE Sem 5'),
-- (5, 3, 6, 'TY CSE Sem 6'),
-- (5, 4, 7, 'LY CSE Sem 7'),
-- (5, 4, 8, 'LY CSE Sem 8');

-- -- Subjects for SY CSE Sem 3 (class_id = 3)
-- INSERT INTO subjects (class_id, subject_name, subject_code, mode) VALUES
-- (3, 'Data Structures',        'CS301', 'theory'),
-- (3, 'Data Structures Lab',    'CS301P','practical'),
-- (3, 'Discrete Mathematics',   'CS302', 'theory'),
-- (3, 'Computer Organisation',  'CS303', 'theory'),
-- (3, 'Operating Systems',      'CS304', 'both');

-- -- HOD for CSE
-- INSERT INTO users (name, email, password, role, department_id) VALUES (
--     'Dr. Rajesh Sharma',
--     'hod.cse@gcea.edu',
--     '$2y$10$HgVigzGrirhdPlpxcqRezeJ.wkrBL7PIW5x9.8u0i4UXDYIS6Ohxi',  -- hod@1234
--     'hod',
--     5
-- );

-- -- Two sample teachers
-- INSERT INTO users (
--     name, email, password, role, department_id,
--     teacher_type, teacher_mode, subject_id,
--     rate_theory, rate_practical, rate_other,
--     appointment_order_no, phone
-- ) VALUES
-- (
--     'Prof. Anjali Mehta', 'anjali@gcea.edu',
--     '$2y$10$b5yE8qe.hEphrxbw/X0y4ORhUT.Lm9ZXbjkXFDm.soG35a0olPw.C',  -- teacher@1234
--     'teacher', 5,
--     'expert', 'theory', 1,
--     500.00, 0.00, 300.00,
--     'GCEA/APP/2024/001', '9876543210'
-- ),
-- (
--     'Prof. Ravi Kumar', 'ravi@gcea.edu',
--     '$2y$10$b5yE8qe.hEphrxbw/X0y4ORhUT.Lm9ZXbjkXFDm.soG35a0olPw.C',  -- teacher@1234
--     'teacher', 5,
--     'sectional_expert', 'practical', 2,
--     0.00, 450.00, 0.00,
--     'GCEA/APP/2024/002', '9123456780'
-- );

-- -- Sample Earn & Learn student
-- INSERT INTO users (
--     name, email, password, role, department_id, class_id, rate_per_hour, phone
-- ) VALUES (
--     'Rahul Patil', 'rahul@gcea.edu',
--     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- student@1234
--     'student', 5, 3, 80.00, '9988776655'
-- );

-- -- Sample lecture entries for Anjali (teacher_id = 3, subject_id = 1, class_id = 3)
-- INSERT INTO lectures (teacher_id, subject_id, class_id, lecture_date, theory_hours, practical_hours, other_hours) VALUES
-- (3, 1, 3, '2026-03-03', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-05', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-10', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-12', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-17', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-19', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-24', 2.0, 0.0, 0.0),
-- (3, 1, 3, '2026-03-26', 2.0, 0.0, 0.0);

-- ============================================================
-- QUICK LOGIN REFERENCE
-- ============================================================
-- Role         Email                   Password
-- Admin        admin@gcea.edu          admin@1234
-- HOD (CSE)    hod.cse@gcea.edu        hod@1234
-- Teacher      anjali@gcea.edu         teacher@1234
-- Teacher      ravi@gcea.edu           teacher@1234
-- Student      rahul@gcea.edu          student@1234
-- ============================================================
