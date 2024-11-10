CREATE DATABASE IF NOT EXISTS community_engagement_db;

-- Create user table
CREATE TABLE users(
    Id int PRIMARY KEY AUTO_INCREMENT,
    Username varchar(200),
    Email varchar(200),
    Age int,
    Password varchar(200)
);

-- Create discussion table with foreign key linking to user
CREATE TABLE discussion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
);

-- Create comments table with foreign key linking to discussion
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discussion_id INT,
    author VARCHAR(255),
    comment TEXT,
    FOREIGN KEY (discussion_id) REFERENCES discussion(id)
);
