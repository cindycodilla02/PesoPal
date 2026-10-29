CREATE DATABASE IF NOT EXISTS student_expense_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE student_expense_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    student_number VARCHAR(20) NULL UNIQUE,
    course VARCHAR(100) NULL,
    year_level VARCHAR(10) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users
    MODIFY first_name VARCHAR(100) NOT NULL,
    MODIFY last_name VARCHAR(100) NOT NULL,
    MODIFY year_level VARCHAR(10) NULL;

CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

ALTER TABLE categories
    MODIFY category_name VARCHAR(100) NOT NULL;

CREATE TABLE IF NOT EXISTS allowances (
    allowance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    allowance_date DATE NOT NULL,
    allowance_type ENUM('Daily', 'Weekly', 'Monthly') NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_allowances_user_date (user_id, allowance_date),
    CONSTRAINT fk_allowances_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expenses_user_date (user_id, expense_date),
    INDEX idx_expenses_category (category_id),
    CONSTRAINT fk_expenses_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_expenses_category FOREIGN KEY (category_id)
        REFERENCES categories(category_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    budget_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_budgets_user_dates (user_id, start_date, end_date),
    CONSTRAINT fk_budgets_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    report_type ENUM('Daily', 'Weekly', 'Monthly') NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_income DECIMAL(10,2) NOT NULL,
    total_expense DECIMAL(10,2) NOT NULL,
    remaining_balance DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity VARCHAR(255) NOT NULL,
    activity_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    INDEX idx_activity_user_date (user_id, activity_date),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (category_name, description) VALUES
    ('Food', 'Meals and snacks'),
    ('Transportation', 'Commuting and travel'),
    ('School Supplies', 'Books, notebooks, and stationery'),
    ('Projects', 'School project materials and costs'),
    ('Internet', 'Mobile data and internet'),
    ('Others', 'Other student expenses');
