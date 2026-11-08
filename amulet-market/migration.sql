
-- 1) สถานะใหม่: ซ่อนประกาศ / ยกเลิกคำสั่งซื้อ (เก็บประวัติแทนการลบแถว)
ALTER TABLE amulets MODIFY Stock_Status ENUM('พร้อมเช่า','จองแล้ว','ปล่อยเช่าแล้ว','ซ่อนประกาศ') NOT NULL DEFAULT 'พร้อมเช่า';
ALTER TABLE orders  MODIFY Payment_Status ENUM('รอชำระ','โอนแล้ว','โอนเงินผ่านระบบตัวกลาง','ยกเลิก') NOT NULL DEFAULT 'รอชำระ';

-- 2) ที่อยู่จัดส่งต่อคำสั่งซื้อ
ALTER TABLE orders
  ADD COLUMN Ship_Name VARCHAR(120) DEFAULT NULL,
  ADD COLUMN Ship_Phone VARCHAR(20) DEFAULT NULL,
  ADD COLUMN Ship_Address TEXT,
  ADD INDEX idx_pay_date (Payment_Status, Order_Date);

-- 3) index + กันรีวิวซ้ำต่อคำสั่งซื้อ (ถ้า error "Duplicate entry" แปลว่ามีรีวิวซ้ำอยู่ ให้ลบแถวที่ซ้ำก่อน)
ALTER TABLE amulets ADD INDEX idx_created (Created_At);
ALTER TABLE reviews_disputes ADD UNIQUE KEY uq_review_order (Order_ID);

-- 4) ตารางใหม่
CREATE TABLE IF NOT EXISTS favorites (
  User_ID INT NOT NULL,
  Amulet_ID INT NOT NULL,
  Created_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (User_ID, Amulet_ID),
  FOREIGN KEY (User_ID) REFERENCES users(User_ID) ON DELETE CASCADE,
  FOREIGN KEY (Amulet_ID) REFERENCES amulets(Amulet_ID) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  Attempt_ID INT AUTO_INCREMENT PRIMARY KEY,
  Identifier VARCHAR(120) NOT NULL,
  IP VARCHAR(45) NOT NULL,
  Attempted_At DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lookup (Identifier, IP, Attempted_At)
) ENGINE=InnoDB;

-- 5) หมวด "พระกรุ" ตามเทมเพลต (เพิ่มเฉพาะถ้ายังไม่มี)
INSERT INTO categories (Category_Name)
SELECT 'พระกรุ' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE Category_Name = 'พระกรุ');
