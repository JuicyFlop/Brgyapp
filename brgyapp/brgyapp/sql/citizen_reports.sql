USE brgyapp;

CREATE TABLE IF NOT EXISTS citizen_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_code VARCHAR(30) NOT NULL UNIQUE,
    reporter_name VARCHAR(150) NULL,
    reporter_contact VARCHAR(50) NULL,
    incident_type VARCHAR(100) NOT NULL DEFAULT 'Other',
    description TEXT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    location_accuracy DECIMAL(10,2) NULL,
    status ENUM('Pending','Verified','Responding','Resolved','Rejected')
        NOT NULL DEFAULT 'Pending',
    incident_id INT UNSIGNED NULL,
    admin_notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_location (latitude, longitude)
);
