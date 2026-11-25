-- P4 (vendor products), 24 Nov 2026
-- For anyone who ALREADY imported schema.sql before this change.
-- (A fresh import of schema.sql already has these columns, so you don't need this file then.)
-- Run once in phpMyAdmin (SQL tab) or:  mysql -u root campus_platform < database/migrations/2026_11_24_p4_vendor_products.sql

USE campus_platform;

-- items (owner: P4)
ALTER TABLE items
  ADD COLUMN item_condition ENUM('new','like_new','good','fair') NULL AFTER stock,
  ADD COLUMN allow_pickup   TINYINT(1) NOT NULL DEFAULT 1 AFTER item_condition,
  ADD COLUMN allow_delivery TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_pickup,
  MODIFY COLUMN status ENUM('active','hidden','removed','deleted') NOT NULL DEFAULT 'active';

-- vendors (owner: P2, agreed with P4): fields from the "Become a vendor" form
ALTER TABLE vendors
  ADD COLUMN vendor_type ENUM('student','off_campus') NOT NULL DEFAULT 'student' AFTER shop_name,
  ADD COLUMN location    VARCHAR(150) NULL AFTER vendor_type;
