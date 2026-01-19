-- Database Schema for Ticketing System
-- Version: 1.0.0

CREATE DATABASE IF NOT EXISTS ticketing_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE ticketing_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'technician', 'user') NOT NULL DEFAULT 'user',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Ticket priorities
CREATE TABLE priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) UNIQUE NOT NULL,
    level INT NOT NULL,
    color VARCHAR(7) NOT NULL,
    INDEX idx_level (level)
) ENGINE=InnoDB;

-- Ticket categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Ticket statuses
CREATE TABLE statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) UNIQUE NOT NULL,
    type ENUM('open', 'in_progress', 'closed') NOT NULL,
    color VARCHAR(7) NOT NULL
) ENGINE=InnoDB;

-- Tickets table
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    user_id INT NOT NULL,
    assigned_to INT,
    priority_id INT NOT NULL,
    category_id INT NOT NULL,
    status_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (priority_id) REFERENCES priorities(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (status_id) REFERENCES statuses(id),
    INDEX idx_user_id (user_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status_id),
    INDEX idx_priority (priority_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Ticket comments
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Ticket history/audit log
CREATE TABLE ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    field_changed VARCHAR(50),
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Insert default priorities
INSERT INTO priorities (name, level, color) VALUES
('Low', 1, '#655883'),
('Medium', 2, '#8B7AA8'),
('High', 3, '#A67C52'),
('Critical', 4, '#C84B31');

-- Insert default statuses
INSERT INTO statuses (name, type, color) VALUES
('Open', 'open', '#655883'),
('In Progress', 'in_progress', '#8B7AA8'),
('Pending', 'in_progress', '#A67C52'),
('Resolved', 'closed', '#4A7C59'),
('Closed', 'closed', '#2C3E50');

-- Insert default categories
INSERT INTO categories (name, description, icon) VALUES
('Technical', 'Technical issues and bugs', 'tool'),
('Feature Request', 'New feature suggestions', 'star'),
('Support', 'General support questions', 'help-circle'),
('Bug', 'Software bugs and errors', 'alert-circle'),
('Documentation', 'Documentation related', 'book'),
('Other', 'Other requests', 'more-horizontal');

-- Insert default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES
('admin', 'admin@ticketing.local', '$2a$12$yDLtsgp9YYhj1llsF3RLv.Q/pdhbE09VR0SS0OW6.4C96RZnOO6t.', 'admin', 'System', 'Administrator');
