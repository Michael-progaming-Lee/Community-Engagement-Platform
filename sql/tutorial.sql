-- Drop database if exists and create new one
DROP DATABASE IF EXISTS community_engagement_db;
CREATE DATABASE community_engagement_db;
USE community_engagement_db;

-- Create user table
CREATE TABLE users(
    Id int PRIMARY KEY AUTO_INCREMENT,
    Username varchar(200),
    Email varchar(200),
    Age int,
    Parish varchar(50),
    Password varchar(200)
);

CREATE TABLE users_cart(
    Id int PRIMARY KEY AUTO_INCREMENT,
    --User Details
    UserID INT,

    --Product Details
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_category VARCHAR(255) NOT NULL,
    product_description TEXT NOT NULL,
    product_quantity INT,
    product_cost Numeric,
    product_img TEXT,
    product_total Numeric,
    FOREIGN KEY (UserID) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
);

-- Create Product table with foreign key linking to user
CREATE TABLE product (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_seller_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_category VARCHAR(255) NOT NULL,
    product_description TEXT NOT NULL,
    product_quantity INT,
    product_cost Numeric,
    product_img TEXT,
    FOREIGN KEY (product_seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create comments table with foreign key linking to product
CREATE TABLE IF NOT EXISTS product_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE price_Negotiation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    seller_id INT NOT NULL,
    original_price DECIMAL(10, 2) NOT NULL,
    proposed_price DECIMAL(10, 2) NOT NULL,
    seller_response ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    final_price DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id)
);
