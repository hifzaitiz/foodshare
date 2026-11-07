CREATE DATABASE IF NOT EXISTS food_share CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE food_share;

DROP TABLE IF EXISTS post_status_logs, claims, food_posts, food_categories, ngo_members, ngos, users, locations;

CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    address VARCHAR(255) NOT NULL,
    area VARCHAR(100),
    city VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','donor','ngo','volunteer') NOT NULL,
    organization_name VARCHAR(150) NULL,          -- donors only (restaurant, wedding hall...)
    is_verified TINYINT(1) NOT NULL DEFAULT 0,    -- volunteers only: admin approves
    rejected_at DATETIME NULL,                    -- volunteers only: set when admin rejects
    location_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ngos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    registration_no VARCHAR(50) NOT NULL UNIQUE,
    location_id INT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    rejected_at DATETIME NULL,                    -- set when admin rejects the NGO
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ngo_members (                        -- volunteers belong to NGOs (many-to-many)
    ngo_id INT NOT NULL,
    user_id INT NOT NULL,
    member_role ENUM('manager','volunteer') NOT NULL DEFAULT 'volunteer',
    PRIMARY KEY (ngo_id, user_id),
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE food_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE food_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    category_id INT NULL,
    pickup_location_id INT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    quantity INT NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'plates',
    pickup_from DATETIME NOT NULL,
    pickup_until DATETIME NOT NULL,
    status ENUM('available','claimed','collected','withdrawn') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES food_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (pickup_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL UNIQUE,                  -- one post is claimed once
    claimed_by INT NOT NULL,                      -- NGO user or volunteer
    ngo_id INT NULL,                              -- set when claimed on behalf of an NGO
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    collected_at DATETIME NULL,
    FOREIGN KEY (post_id) REFERENCES food_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (claimed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES ngos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE post_status_logs (                   -- history: available -> claimed -> collected
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    changed_by INT NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES food_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
