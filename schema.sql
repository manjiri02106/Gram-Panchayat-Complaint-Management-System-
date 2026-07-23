-- Gram Panchayat Complaint Management System (GPCMS)
-- Database Schema Script

CREATE DATABASE IF NOT EXISTS gp_complaint_db;
USE gp_complaint_db;

-- 1. Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Roles
INSERT INTO roles (id, role_name, display_name) VALUES 
(1, 'super_admin', 'Super Admin'),
(2, 'gp_admin', 'GP Admin / Gram Sevak'),
(3, 'field_officer', 'Field Officer / Maintenance'),
(4, 'citizen', 'Citizen')
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), display_name=VALUES(display_name);

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Complaint Categories Table
CREATE TABLE IF NOT EXISTS complaint_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Categories
INSERT INTO complaint_categories (category_name, description) VALUES
('Water Supply', 'Drinking water supply, leakage, pump failure, or quality issues'),
('Street Lights', 'Non-functional street lamps, wiring issues, or timer adjustments'),
('Sanitation & Garbage', 'Garbage collection, drainage cleaning, public toilet maintenance'),
('Roads & Drainage', 'Potholes, broken drainage lines, new road repairs, water logging'),
('Others', 'General grievances and miscellaneous village issues')
ON DUPLICATE KEY UPDATE category_name=VALUES(category_name), description=VALUES(description);

-- 4. Complaint Statuses Table
CREATE TABLE IF NOT EXISTS complaint_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Complaint Statuses
INSERT INTO complaint_statuses (id, status_name, display_name) VALUES
(1, 'pending', 'Pending'),
(2, 'assigned', 'Assigned'),
(3, 'in_progress', 'In Progress'),
(4, 'resolved', 'Resolved'),
(5, 'rejected', 'Rejected')
ON DUPLICATE KEY UPDATE status_name=VALUES(status_name), display_name=VALUES(display_name);

-- 5. Complaints Table
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(20) NOT NULL UNIQUE,
    citizen_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    after_image_path VARCHAR(255) DEFAULT NULL,
    status_id INT NOT NULL,
    assigned_officer_id INT DEFAULT NULL,
    admin_remarks TEXT DEFAULT NULL,
    officer_remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES complaint_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (status_id) REFERENCES complaint_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_officer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Public Announcements
INSERT INTO announcements (title, content) VALUES
('Gram Sabha Meeting on July 25th', 'All villagers are requested to attend the upcoming Gram Sabha meeting at the Panchayat Bhawan at 10:00 AM. We will discuss clean water access and new road developments.'),
('Free Medical Camp in Panchayat Hall', 'A free medical health checkup camp will be organized on Sunday, July 20th, from 9:00 AM to 4:00 PM. Specialist doctors will be available.'),
('Jal Jeevan Mission Pipeline Maintenance', 'Drinking water supply will be temporarily suspended tomorrow (July 17th) between 10:00 AM and 2:00 PM due to pipeline maintenance work near Ward 4.');

-- 7. Government Schemes Table
CREATE TABLE IF NOT EXISTS government_schemes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Government Schemes
INSERT INTO government_schemes (title, description, link) VALUES
('Pradhan Mantri Gram Sadak Yojana (PMGSY)', 'A nation-wide plan to provide good all-weather road connectivity to unconnected villages.', 'https://pmgsy.nic.in'),
('Swachh Bharat Mission (Grameen)', 'Focusing on rural sanitation and accelerating efforts to achieve universal sanitation coverage.', 'https://swachhbharatmission.gov.in'),
('Jal Jeevan Mission (Har Ghar Jal)', 'Aims to provide safe and adequate drinking water through individual household tap connections by 2024.', 'https://jaljeevanmission.gov.in'),
('Deendayal Antyodaya Yojana - NRLM', 'Aims to organize rural poor households into self-help groups and support them for livelihood improvement.', 'https://aajeevika.gov.in');

-- 8. Emergency Contacts Table
CREATE TABLE IF NOT EXISTS emergency_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    designation VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Emergency Contacts
INSERT INTO emergency_contacts (name, designation, phone_number) VALUES
('Mr. Ramesh Kumar', 'Gram Sevak (GP Secretary)', '+91 98765 43210'),
('Mrs. Sunita Devi', 'Sarpanch (Village Head)', '+91 98765 43211'),
('Mr. Anil Sharma', 'Primary Health Center', '+91 11 2345 6789'),
('Village Electricity Board', 'Junior Engineer', '+91 98765 43212'),
('Local Police Outpost', 'Station House Officer', '+91 11 2345 9999');

-- 9. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Feedback Table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL UNIQUE,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
