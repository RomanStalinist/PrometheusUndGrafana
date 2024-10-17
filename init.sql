CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    gender ENUM('male', 'female') NOT NULL
);

INSERT INTO users (name, password_hash, gender) VALUES 
('John Doe', 'hashed_password_1', 'male'),
('Jane Smith', 'hashed_password_2', 'female'),
('Alice Brown', 'hashed_password_3', 'female'),
('Bob Johnson', 'hashed_password_4', 'male');