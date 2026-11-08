-- ตลาดพระเครื่อง : โครงสร้างฐานข้อมูล MySQL (ตาม ER Diagram)
CREATE DATABASE IF NOT EXISTS amulet_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE amulet_market;

CREATE TABLE users (
  User_ID INT AUTO_INCREMENT PRIMARY KEY,
  Username VARCHAR(50) NOT NULL UNIQUE,
  Password_Hash VARCHAR(255) NOT NULL,
  Email VARCHAR(120) NOT NULL UNIQUE,
  Phone VARCHAR(20) DEFAULT NULL,
  Role ENUM('Buyer','Seller','Admin') NOT NULL DEFAULT 'Buyer',
  Full_Name VARCHAR(120) NOT NULL,
  Address TEXT,
  Bank_Account VARCHAR(100) DEFAULT NULL,
  Verification_Status ENUM('ยังไม่ยืนยัน','รอตรวจสอบ','ยืนยันแล้ว') NOT NULL DEFAULT 'ยังไม่ยืนยัน',
  Registration_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Rating_Score DECIMAL(3,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB;

CREATE TABLE categories (
  Category_ID INT AUTO_INCREMENT PRIMARY KEY,
  Category_Name VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE amulets (
  Amulet_ID INT AUTO_INCREMENT PRIMARY KEY,
  Seller_ID INT NOT NULL,
  Name VARCHAR(200) NOT NULL,
  Category_ID INT NOT NULL,
  Monk_Creator VARCHAR(150) DEFAULT NULL,
  Temple VARCHAR(150) DEFAULT NULL,
  Year_Created INT DEFAULT NULL,
  Material VARCHAR(100) DEFAULT NULL,
  Condition_Desc VARCHAR(100) DEFAULT NULL,
  Price DECIMAL(12,2) NOT NULL,
  Stock_Status ENUM('พร้อมเช่า','จองแล้ว','ปล่อยเช่าแล้ว','ซ่อนประกาศ') NOT NULL DEFAULT 'พร้อมเช่า',
  Certificate_Info VARCHAR(200) DEFAULT NULL,
  Guarantee_Policy VARCHAR(255) DEFAULT NULL,
  Description TEXT,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (Seller_ID) REFERENCES users(User_ID),
  FOREIGN KEY (Category_ID) REFERENCES categories(Category_ID),
  INDEX idx_status (Stock_Status),
  INDEX idx_created (Created_At)
) ENGINE=InnoDB;

CREATE TABLE amulet_images (
  Image_ID INT AUTO_INCREMENT PRIMARY KEY,
  Amulet_ID INT NOT NULL,
  Image_URL VARCHAR(255) NOT NULL,
  Is_Primary TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (Amulet_ID) REFERENCES amulets(Amulet_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  Order_ID INT AUTO_INCREMENT PRIMARY KEY,
  Buyer_ID INT NOT NULL,
  Amulet_ID INT NOT NULL,
  Order_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Total_Amount DECIMAL(12,2) NOT NULL,
  Payment_Status ENUM('รอชำระ','โอนแล้ว','โอนเงินผ่านระบบตัวกลาง','ยกเลิก') NOT NULL DEFAULT 'รอชำระ',
  Shipping_Status ENUM('รอดำเนินการ','จัดส่งแล้ว','ได้รับของแล้ว') NOT NULL DEFAULT 'รอดำเนินการ',
  Tracking_Number VARCHAR(60) DEFAULT NULL,
  Ship_Name VARCHAR(120) DEFAULT NULL,
  Ship_Phone VARCHAR(20) DEFAULT NULL,
  Ship_Address TEXT,
  INDEX idx_pay_date (Payment_Status, Order_Date),
  FOREIGN KEY (Buyer_ID) REFERENCES users(User_ID),
  FOREIGN KEY (Amulet_ID) REFERENCES amulets(Amulet_ID)
) ENGINE=InnoDB;

CREATE TABLE reviews_disputes (
  Review_ID INT AUTO_INCREMENT PRIMARY KEY,
  Order_ID INT NOT NULL,
  Rating TINYINT DEFAULT NULL,
  Comment TEXT,
  Dispute_Status ENUM('ไม่มี','แจ้งพระเก๊','ไม่ได้รับของ') NOT NULL DEFAULT 'ไม่มี',
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_order (Order_ID),
  FOREIGN KEY (Order_ID) REFERENCES orders(Order_ID)
) ENGINE=InnoDB;

CREATE TABLE posts (
  Post_ID INT AUTO_INCREMENT PRIMARY KEY,
  User_ID INT NOT NULL,
  Amulet_ID INT DEFAULT NULL,
  Post_Type ENUM('ลงขาย','โชว์พระ','สอบถามข้อมูล') NOT NULL DEFAULT 'โชว์พระ',
  Content TEXT,
  Image_URL VARCHAR(255) DEFAULT NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (User_ID) REFERENCES users(User_ID),
  FOREIGN KEY (Amulet_ID) REFERENCES amulets(Amulet_ID) ON DELETE SET NULL,
  INDEX idx_created (Created_At)
) ENGINE=InnoDB;

CREATE TABLE comments (
  Comment_ID INT AUTO_INCREMENT PRIMARY KEY,
  Post_ID INT NOT NULL,
  User_ID INT NOT NULL,
  Comment_Text TEXT NOT NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (Post_ID) REFERENCES posts(Post_ID) ON DELETE CASCADE,
  FOREIGN KEY (User_ID) REFERENCES users(User_ID)
) ENGINE=InnoDB;

-- 1 คน โหวต/ไลก์ได้ 1 ครั้งต่อโพสต์ (ป้องกันการปั่นยอด)
CREATE TABLE reactions (
  Reaction_ID INT AUTO_INCREMENT PRIMARY KEY,
  Post_ID INT NOT NULL,
  User_ID INT NOT NULL,
  Reaction_Type ENUM('like','real','fake') NOT NULL DEFAULT 'like',
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_post_user (Post_ID, User_ID),
  FOREIGN KEY (Post_ID) REFERENCES posts(Post_ID) ON DELETE CASCADE,
  FOREIGN KEY (User_ID) REFERENCES users(User_ID)
) ENGINE=InnoDB;

-- รายการโปรด (บันทึกพระที่สนใจ)
CREATE TABLE favorites (
  User_ID INT NOT NULL,
  Amulet_ID INT NOT NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (User_ID, Amulet_ID),
  FOREIGN KEY (User_ID) REFERENCES users(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Amulet_ID) REFERENCES amulets(Amulet_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

-- บันทึกการเข้าสู่ระบบที่ผิด (กันเดารหัสผ่าน)
CREATE TABLE login_attempts (
  Attempt_ID INT AUTO_INCREMENT PRIMARY KEY,
  Identifier VARCHAR(120) NOT NULL,
  IP VARCHAR(45) NOT NULL,
  Attempted_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lookup (Identifier, IP, Attempted_At)
) ENGINE=InnoDB;

INSERT INTO categories (Category_Name) VALUES ('พระเหรียญ'),('พระผง'),('พระกรุ'),('พระกริ่ง/พระบูชา'),('เครื่องราง'),('พระปิดตา'),('อื่นๆ');
