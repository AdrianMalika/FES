-- Operator Management Schema for FES
-- Run these statements in your MySQL database (fes_db)

-- Table to store operator skills
CREATE TABLE IF NOT EXISTS operator_skills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    skill_level ENUM('beginner', 'intermediate', 'advanced', 'expert') NOT NULL DEFAULT 'intermediate',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_operator_skills_operator_id (operator_id),
    CONSTRAINT fk_operator_skills_user
        FOREIGN KEY (operator_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table to store operator weekly availability slots
CREATE TABLE IF NOT EXISTS operator_availability (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operator_id INT NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL, -- 0 = Sunday ... 6 = Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_availability_operator_day (operator_id, day_of_week),
    CONSTRAINT fk_operator_availability_user
        FOREIGN KEY (operator_id) REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

