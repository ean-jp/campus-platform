-- Campus Platform schema (MySQL / MariaDB, InnoDB, utf8mb4)
-- Re-runnable: drops and recreates everything. Import in phpMyAdmin or:
--   mysql -u root < database/schema.sql
-- Owners: P1 users..login_attempts | P2 categories, vendors, resource_services, reports
--         P3 carts..order_items    | P4 items, item_images, notifications, reviews
--         P5 providers, time_slots, bookings
-- Owners: change your own tables here via your branch and tell the group chat.

CREATE DATABASE IF NOT EXISTS campus_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_platform;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reports, reviews, notifications, bookings, order_items, orders,
  cart_items, carts, item_images, items, time_slots, providers, vendors,
  resource_services, categories, login_attempts, verification_codes, user_emails, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------- P1
CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(190) NOT NULL,                 -- login email
  phone          VARCHAR(20)  NULL,
  password_hash  VARCHAR(255) NOT NULL,                 -- password_hash() output
  account_type   ENUM('ashesi','guest') NOT NULL,       -- set by the server from the email
  role           ENUM('user','admin') NOT NULL DEFAULT 'user',
  status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  remember_token_hash CHAR(64) NULL,                    -- hash of the random "remember me" token
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE user_emails (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  email       VARCHAR(190) NOT NULL,
  is_ashesi   TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_emails_email (email),
  KEY idx_user_emails_user (user_id),
  CONSTRAINT fk_user_emails_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE verification_codes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,
  email      VARCHAR(190) NOT NULL,
  purpose    ENUM('signup','link_email','password_reset') NOT NULL,
  code_hash  VARCHAR(255) NOT NULL,                     -- the 6-digit code, hashed
  attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,       -- max 5
  expires_at DATETIME NOT NULL,                         -- created + 10 minutes
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_codes_email_purpose (email, purpose),
  CONSTRAINT fk_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email        VARCHAR(190) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  success      TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_email_time (email, attempted_at),
  KEY idx_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P2
CREATE TABLE categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80) NOT NULL,
  slug       VARCHAR(80) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE resource_services (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_name (name)
) ENGINE=InnoDB;

CREATE TABLE vendors (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,                    -- the owner account
  shop_name   VARCHAR(120) NOT NULL,
  description TEXT NULL,
  phone       VARCHAR(20) NULL,
  logo_path   VARCHAR(255) NULL,
  opens_at    TIME NULL,                                -- for the "Open now / Closed" badge
  closes_at   TIME NULL,
  status      ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,                        -- admin who approved/rejected
  reviewed_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vendors_user (user_id),
  KEY idx_vendors_status (status),
  CONSTRAINT fk_vendors_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vendors_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P5 (needed before bookings)
CREATE TABLE providers (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,                         -- login account for the provider dashboard
  name       VARCHAR(120) NOT NULL,
  bio        VARCHAR(255) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_providers_service (service_id),
  CONSTRAINT fk_providers_service FOREIGN KEY (service_id) REFERENCES resource_services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_providers_user    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE time_slots (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id INT UNSIGNED NOT NULL,
  slot_date   DATE NOT NULL,
  start_time  TIME NOT NULL,
  end_time    TIME NOT NULL,
  is_booked   TINYINT(1) NOT NULL DEFAULT 0,            -- book with UPDATE ... WHERE is_booked = 0
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot (provider_id, slot_date, start_time),
  KEY idx_slots_free (provider_id, slot_date, is_booked),
  CONSTRAINT fk_slots_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P4
CREATE TABLE items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vendor_id   INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  name        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  price       DECIMAL(10,2) NOT NULL,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,          -- 0 = sold out; checkout lowers it safely
  status      ENUM('active','hidden','removed') NOT NULL DEFAULT 'active',  -- hidden: vendor; removed: admin
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_items_vendor (vendor_id),
  KEY idx_items_category_status (category_id, status),
  KEY idx_items_name (name),
  CONSTRAINT fk_items_vendor   FOREIGN KEY (vendor_id)   REFERENCES vendors(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE item_images (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id    INT UNSIGNED NOT NULL,
  file_path  VARCHAR(255) NOT NULL,                     -- renamed file under public/uploads/
  alt_text   VARCHAR(150) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_item_images_item (item_id),
  CONSTRAINT fk_item_images_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P3
CREATE TABLE carts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_user (user_id),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id  INT UNSIGNED NOT NULL,
  item_id  INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_item (cart_id, item_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (               -- one order per vendor per checkout
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  vendor_id      INT UNSIGNED NOT NULL,
  status         ENUM('received','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'received',
  total          DECIMAL(10,2) NOT NULL,
  fulfilment     ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
  delivery_spot  VARCHAR(150) NULL,
  contact_phone  VARCHAR(20) NULL,                      -- required for guests on food orders
  payment_method ENUM('pay_on_delivery') NOT NULL DEFAULT 'pay_on_delivery',
  pickup_code    CHAR(4) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_user (user_id, created_at),
  KEY idx_orders_vendor_status (vendor_id, status),
  CONSTRAINT fk_orders_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE RESTRICT,
  CONSTRAINT fk_orders_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  item_id    INT UNSIGNED NOT NULL,
  item_name  VARCHAR(150) NOT NULL,                     -- copied at checkout, so history survives edits
  unit_price DECIMAL(10,2) NOT NULL,
  quantity   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_item  FOREIGN KEY (item_id)  REFERENCES items(id)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P5
CREATE TABLE bookings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  slot_id          INT UNSIGNED NOT NULL,
  status           ENUM('confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'confirmed',
  provider_notes   TEXT NULL,                           -- private: admin must never read this
  reminder_sent_at DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at     DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_bookings_user (user_id, status),
  KEY idx_bookings_slot (slot_id),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_slot FOREIGN KEY (slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P4
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  type       VARCHAR(40) NOT NULL,                      -- e.g. new_order, order_status
  message    VARCHAR(255) NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL,
  comment    VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review (item_id, user_id),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------- P2
CREATE TABLE reports (               -- reported products
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id     INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NOT NULL,
  reason      VARCHAR(500) NOT NULL,
  status      ENUM('open','dismissed','item_hidden','item_removed') NOT NULL DEFAULT 'open',
  resolved_by INT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_status (status),
  CONSTRAINT fk_reports_item     FOREIGN KEY (item_id)     REFERENCES items(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
