<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล — แก้ให้ตรงกับเครื่องของคุณ
const DB_HOST = 'localhost';
const DB_NAME = 's68160212';
const DB_USER = 's68160212';
const DB_PASS = 'aySkYpnc';

const SITE_NAME = 'ตลาดพระเครื่อง';
const UPLOAD_DIR = __DIR__ . '/uploads/';
const UPLOAD_URL = 'uploads/';
const MAX_UPLOAD_BYTES = 3 * 1024 * 1024; // 3MB

// --- ค่าเพื่อความเสถียร ---
const APP_DEBUG = false;        // true เฉพาะตอนพัฒนา (แสดง error บนหน้าเว็บ) — ขึ้นเว็บจริงต้องเป็น false
const TIMEZONE = 'Asia/Bangkok'; // PHP และ MySQL ใช้เวลาเดียวกัน กัน "x ชั่วโมงที่แล้ว" เพี้ยน
const DB_TZ_OFFSET = '+07:00';
const PER_PAGE = 12;            // จำนวนพระต่อหน้าในตลาด
const ORDER_HOLD_HOURS = 48;    // คำสั่งซื้อที่ยังไม่ชำระเกินกี่ชั่วโมงให้ปล่อยพระกลับมาขายอัตโนมัติ
