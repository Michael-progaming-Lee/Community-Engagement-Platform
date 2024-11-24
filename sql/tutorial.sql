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
    Username varchar(200),
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
    product_seller VARCHAR(200) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_category VARCHAR(255) NOT NULL,
    product_description TEXT NOT NULL,
    product_quantity INT,
    product_cost Numeric,
    product_img TEXT
);

-- Create comments table with foreign key linking to product
CREATE TABLE IF NOT EXISTS product_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE
);
