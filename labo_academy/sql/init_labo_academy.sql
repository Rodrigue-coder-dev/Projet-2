-- =====================================================
-- LABO ACADEMY - Base de Données Complète
-- =====================================================

DROP DATABASE IF EXISTS labo_academy;
CREATE DATABASE labo_academy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE labo_academy;

-- =====================================================
-- TABLE: users (Comptes utilisateurs)
-- =====================================================
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    code_unique VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','student') NOT NULL DEFAULT 'student',
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT,
    profile_image VARCHAR(255) DEFAULT 'default-avatar.png',
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_code UNIQUE (code_unique),
    CONSTRAINT uq_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: levels (Formations)
-- =====================================================
CREATE TABLE levels (
    id INT NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    cycle VARCHAR(50) NOT NULL,
    duration INT NOT NULL DEFAULT 12,
    description TEXT,
    image_path VARCHAR(255) DEFAULT 'default-formation.jpg',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_levels PRIMARY KEY (id),
    CONSTRAINT uq_level_code UNIQUE (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: services (Services du centre)
-- =====================================================
CREATE TABLE services (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_path VARCHAR(255) DEFAULT 'default-service.jpg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_services PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: students (Profils étudiants)
-- =====================================================
CREATE TABLE students (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    matricule VARCHAR(20) NOT NULL,
    level_id INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(100) NOT NULL,
    status ENUM('ACTIVE','SUSPENDED','GRADUATED') DEFAULT 'ACTIVE',
    inscription_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    inscription_paid TINYINT(1) NOT NULL DEFAULT 0,
    registration_date DATE NOT NULL,
    CONSTRAINT pk_students PRIMARY KEY (id),
    CONSTRAINT uq_matricule UNIQUE (matricule),
    CONSTRAINT fk_stud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_stud_level FOREIGN KEY (level_id) REFERENCES levels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: courses (Cours)
-- =====================================================
CREATE TABLE courses (
    id INT NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    credits INT NOT NULL DEFAULT 3,
    semester INT NOT NULL DEFAULT 1,
    level_id INT NOT NULL,
    teacher_id INT NOT NULL,
    image_path VARCHAR(255) DEFAULT 'default-course.jpg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_courses PRIMARY KEY (id),
    CONSTRAINT uq_course_code UNIQUE (code),
    CONSTRAINT fk_course_level FOREIGN KEY (level_id) REFERENCES levels(id),
    CONSTRAINT fk_course_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: timetables (Emplois du temps)
-- =====================================================
CREATE TABLE timetables (
    id INT NOT NULL AUTO_INCREMENT,
    course_id INT NOT NULL,
    day_of_week ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    classroom VARCHAR(50) NOT NULL,
    level_id INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    semester INT NOT NULL DEFAULT 1,
    CONSTRAINT pk_timetables PRIMARY KEY (id),
    CONSTRAINT fk_timetable_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_timetable_level FOREIGN KEY (level_id) REFERENCES levels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: payments (Paiements)
-- =====================================================
CREATE TABLE payments (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('CASH','MTN_MOMO','ORANGE_MONEY','BANK_TRANSFER') NOT NULL,
    reference VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    recorded_by INT NOT NULL,
    receipt_path VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_payments PRIMARY KEY (id),
    CONSTRAINT uq_payment_ref UNIQUE (reference),
    CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_payment_user FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: grades (Notes)
-- =====================================================
CREATE TABLE grades (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    grade_type ENUM('CC','TP','EXAM','RATTRAPAGE') NOT NULL DEFAULT 'EXAM',
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 100,
    coefficient INT NOT NULL DEFAULT 1,
    graded_by INT NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    semester INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_grades PRIMARY KEY (id),
    CONSTRAINT fk_grade_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_grade_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_grade_user FOREIGN KEY (graded_by) REFERENCES users(id),
    CONSTRAINT uk_grade_unique UNIQUE (student_id, course_id, grade_type, academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: documents (Documents pédagogiques)
-- =====================================================
CREATE TABLE documents (
    id INT NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    course_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_documents PRIMARY KEY (id),
    CONSTRAINT fk_doc_course FOREIGN KEY (course_id) REFERENCES courses(id),
    CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: inscriptions_service (Inscriptions aux services)
-- =====================================================
CREATE TABLE inscriptions_service (
    id INT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    service_id INT NOT NULL,
    inscription_date DATE NOT NULL,
    status ENUM('PENDING','CONFIRMED','CANCELLED') DEFAULT 'PENDING',
    amount DECIMAL(10,2) NOT NULL,
    payment_status TINYINT(1) DEFAULT 0,
    CONSTRAINT pk_inscriptions_service PRIMARY KEY (id),
    CONSTRAINT fk_ins_service_student FOREIGN KEY (student_id) REFERENCES students(id),
    CONSTRAINT fk_ins_service_service FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- INDEX pour optimisation
-- =====================================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_students_level ON students(level_id);
CREATE INDEX idx_students_matricule ON students(matricule);
CREATE INDEX idx_courses_level ON courses(level_id);
CREATE INDEX idx_courses_teacher ON courses(teacher_id);
CREATE INDEX idx_payments_student ON payments(student_id);
CREATE INDEX idx_payments_date ON payments(payment_date);
CREATE INDEX idx_grades_student ON grades(student_id, course_id);
CREATE INDEX idx_timetables_level ON timetables(level_id, academic_year);

-- =====================================================
-- VUES
-- =====================================================

-- Vue: Résumé des étudiants
CREATE VIEW v_student_summary AS
SELECT 
    s.id,
    s.matricule,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    l.name AS formation,
    l.code AS formation_code,
    s.status,
    s.inscription_paid,
    s.inscription_amount,
    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS total_paid,
    s.inscription_amount - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id), 0) AS remaining_balance,
    s.registration_date
FROM students s
JOIN users u ON u.id = s.user_id
JOIN levels l ON l.id = s.level_id;

-- Vue: Relevé de notes
CREATE VIEW v_grade_report AS
SELECT 
    g.student_id,
    s.matricule,
    g.course_id,
    c.name AS course_name,
    c.credits,
    g.grade_type,
    g.score,
    g.max_score,
    ROUND((g.score / g.max_score) * 20, 2) AS grade_over_20,
    g.coefficient,
    g.academic_year,
    g.semester
FROM grades g
JOIN students s ON s.id = g.student_id
JOIN courses c ON c.id = g.course_id;

-- Vue: Dashboard stats
CREATE VIEW v_dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM students WHERE status = 'ACTIVE') AS active_students,
    (SELECT COUNT(*) FROM courses WHERE is_active = 1) AS active_courses,
    (SELECT COUNT(*) FROM levels WHERE is_active = 1) AS total_formations,
    (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE())) AS total_payments,
    (SELECT COALESCE(SUM(s.inscription_amount - COALESCE(SUM(p.amount), 0)), 0) 
     FROM students s 
     LEFT JOIN payments p ON p.student_id = s.id 
     GROUP BY s.id) AS total_balance;

-- =====================================================
-- PROCÉDURES STOCKÉES
-- =====================================================

DELIMITER $$

-- Procédure: Inscription automatique étudiant
CREATE PROCEDURE sp_register_student(
    IN p_first_name VARCHAR(80),
    IN p_last_name VARCHAR(80),
    IN p_email VARCHAR(150),
    IN p_password VARCHAR(255),
    IN p_phone VARCHAR(20),
    IN p_level_id INT,
    IN p_amount DECIMAL(10,2)
)
BEGIN
    DECLARE v_uid INT;
    DECLARE v_code VARCHAR(20);
    DECLARE v_mat VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    
    START TRANSACTION;
    
    -- Génération du code unique
    SET v_code = CONCAT('ISP-', YEAR(NOW()), '-', LPAD((SELECT COUNT(*) + 1 FROM users), 4, '0'));
    
    -- Génération du matricule
    SET v_mat = CONCAT(YEAR(NOW()), '-LA-', LPAD((SELECT COUNT(*) + 1 FROM students), 4, '0'));
    
    -- Insertion dans users
    INSERT INTO users(code_unique, email, password, role, first_name, last_name, phone)
    VALUES(v_code, p_email, p_password, 'student', p_first_name, p_last_name, p_phone);
    
    SET v_uid = LAST_INSERT_ID();
    
    -- Insertion dans students
    INSERT INTO students(user_id, matricule, level_id, inscription_amount, registration_date, date_of_birth, place_of_birth)
    VALUES(v_uid, v_mat, p_level_id, p_amount, CURDATE(), '2000-01-01', 'Douala');
    
    COMMIT;
    
    SELECT v_uid AS user_id, v_code AS code_unique, v_mat AS matricule;
END$$

-- Procédure: Calcul moyenne semestrielle
CREATE PROCEDURE sp_calculate_semester_average(
    IN p_student_id INT,
    IN p_semester INT,
    IN p_academic_year VARCHAR(10)
)
BEGIN
    SELECT 
        ROUND(SUM((g.score / g.max_score) * 20 * g.coefficient) / SUM(g.coefficient), 2) AS semester_average,
        CASE 
            WHEN ROUND(SUM((g.score / g.max_score) * 20 * g.coefficient) / SUM(g.coefficient), 2) >= 16 THEN 'Très Bien'
            WHEN ROUND(SUM((g.score / g.max_score) * 20 * g.coefficient) / SUM(g.coefficient), 2) >= 14 THEN 'Bien'
            WHEN ROUND(SUM((g.score / g.max_score) * 20 * g.coefficient) / SUM(g.coefficient), 2) >= 12 THEN 'Assez Bien'
            WHEN ROUND(SUM((g.score / g.max_score) * 20 * g.coefficient) / SUM(g.coefficient), 2) >= 10 THEN 'Passable'
            ELSE 'Insuffisant'
        END AS mention
    FROM grades g
    WHERE g.student_id = p_student_id 
        AND g.semester = p_semester 
        AND g.academic_year = p_academic_year;
END$$

DELIMITER ;

-- =====================================================
-- DONNÉES DE TEST
-- =====================================================

-- Insertion admin
INSERT INTO users (code_unique, email, password, role, first_name, last_name, phone, is_active) VALUES
('ADMIN-001', 'admin@laboacademy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'LABO', '+237 600000000', 1);

-- Insertion formations
INSERT INTO levels (code, name, cycle, duration, description, price) VALUES
('BTS-GL', 'BTS Génie Logiciel', 'BTS', 24, 'Formation complète en développement logiciel', 450000),
('BTS-RS', 'BTS Réseaux et Systèmes', 'BTS', 24, 'Administration réseau et systèmes', 450000),
('LICENCE-INFO', 'Licence Informatique', 'Licence', 36, 'Formation universitaire en informatique', 650000),
('MASTER-DATA', 'Master Data Science', 'Master', 24, 'Spécialisation en science des données', 850000);

-- Insertion services
INSERT INTO services (name, description, price) VALUES
('Coaching Personnalisé', 'Accompagnement individuel par un expert', 75000),
('Préparation Certification', 'Préparation intensive aux certifications internationales', 150000),
('Stage Professionnel', 'Placement en stage professionnel garanti', 200000),
('Workshop Intensif', 'Ateliers pratiques intensifs (3 jours)', 50000);

-- Insertion formateurs
INSERT INTO users (code_unique, email, password, role, first_name, last_name, phone) VALUES
('TCH-001', 'kevin.fonkoua@laboacademy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Kevin', 'FONKOUA', '+237 690000001'),
('TCH-002', 'adam.mboumbouo@laboacademy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Adam', 'MBOUMBOUO', '+237 690000002');

-- Insertion cours
INSERT INTO courses (code, name, description, credits, semester, level_id, teacher_id) VALUES
('GL101', 'Programmation PHP', 'Développement web côté serveur avec PHP', 5, 1, 1, 2),
('GL102', 'Base de données MySQL', 'Conception et administration MySQL', 5, 1, 1, 2),
('GL103', 'HTML/CSS/JS', 'Développement front-end moderne', 4, 1, 1, 3),
('RS101', 'Réseaux TCP/IP', 'Fondamentaux des réseaux informatiques', 5, 1, 2, 3);

-- Insertion emploi du temps
INSERT INTO timetables (course_id, day_of_week, start_time, end_time, classroom, level_id, academic_year, semester) VALUES
(1, 'MONDAY', '08:00:00', '11:00:00', 'Salle A101', 1, '2025-2026', 1),
(2, 'TUESDAY', '09:00:00', '12:00:00', 'Lab Info 1', 1, '2025-2026', 1),
(3, 'WEDNESDAY', '14:00:00', '17:00:00', 'Salle A101', 1, '2025-2026', 1);

-- Insertion étudiant test
INSERT INTO users (code_unique, email, password, role, first_name, last_name, phone, is_active) VALUES
('STU-2025-0001', 'etudiant@laboacademy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Jean', 'NDONG', '+237 612345678', 1);

SET @user_id = LAST_INSERT_ID();
INSERT INTO students (user_id, matricule, level_id, academic_year, date_of_birth, place_of_birth, inscription_amount, registration_date) VALUES
(@user_id, '2025-LA-001', 1, '2025-2026', '2000-05-15', 'Douala', 450000, CURDATE());

-- Insertion paiement test
INSERT INTO payments (student_id, amount, payment_date, payment_method, reference, recorded_by) VALUES
(1, 150000, CURDATE(), 'CASH', CONCAT('PAY-', DATE_FORMAT(NOW(), '%Y%m%d'), '-001'), 1);

-- Insertion notes test
INSERT INTO grades (student_id, course_id, grade_type, score, max_score, coefficient, graded_by, academic_year, semester) VALUES
(1, 1, 'EXAM', 85, 100, 1, 3, '2025-2026', 1),
(1, 2, 'EXAM', 78, 100, 1, 3, '2025-2026', 1);

SELECT 'Base de données LABO ACADEMY créée avec succès !' AS message;