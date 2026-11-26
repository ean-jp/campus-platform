-- Campus Platform sample data. Run AFTER schema.sql (it re-creates the tables empty first).
-- Every test account below uses the password:  Password123!
USE campus_platform;
-- Clear old data, children first. Uses DELETE (not TRUNCATE) so it works even when foreign key
-- checks are ON, which is phpMyAdmin's default. Then reset the id counters.
DELETE FROM reports;
DELETE FROM reviews;
DELETE FROM notifications;
DELETE FROM bookings;
DELETE FROM order_items;
DELETE FROM orders;
DELETE FROM cart_items;
DELETE FROM carts;
DELETE FROM item_images;
DELETE FROM items;
DELETE FROM time_slots;
DELETE FROM providers;
DELETE FROM vendors;
DELETE FROM resource_services;
DELETE FROM categories;
DELETE FROM login_attempts;
DELETE FROM verification_codes;
DELETE FROM user_emails;
DELETE FROM users;
ALTER TABLE reports AUTO_INCREMENT = 1;
ALTER TABLE reviews AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE bookings AUTO_INCREMENT = 1;
ALTER TABLE order_items AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE cart_items AUTO_INCREMENT = 1;
ALTER TABLE carts AUTO_INCREMENT = 1;
ALTER TABLE item_images AUTO_INCREMENT = 1;
ALTER TABLE items AUTO_INCREMENT = 1;
ALTER TABLE time_slots AUTO_INCREMENT = 1;
ALTER TABLE providers AUTO_INCREMENT = 1;
ALTER TABLE vendors AUTO_INCREMENT = 1;
ALTER TABLE resource_services AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE login_attempts AUTO_INCREMENT = 1;
ALTER TABLE verification_codes AUTO_INCREMENT = 1;
ALTER TABLE user_emails AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

-- Users (id 1 admin, 2 Ashesi student, 3 guest, 4-9 vendor owners)
INSERT INTO users (id, full_name, email, phone, password_hash, account_type, role, status, email_verified_at) VALUES
(1, 'Admin User', 'admin@campus-platform.test', '0240000001', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'ashesi', 'admin', 'active', NOW()),
(2, 'Ama Mensah', 'ama.mensah@ashesi.edu.gh', '0240000002', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'ashesi', 'user', 'active', NOW()),
(3, 'Kofi Owusu', 'kofi.owusu@gmail.com', '0240000003', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(4, 'Mama Esi''s Kitchen Owner', 'vendor1@campus-platform.test', '0240000004', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(5, 'Campus Bites Owner', 'vendor2@campus-platform.test', '0240000005', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(6, 'Brew & Chill Owner', 'vendor3@campus-platform.test', '0240000006', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(7, 'Print & Pages Owner', 'vendor4@campus-platform.test', '0240000007', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(8, 'Kente Corner Owner', 'vendor5@campus-platform.test', '0240000008', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW()),
(9, 'Gadget Hub Owner', 'vendor6@campus-platform.test', '0240000009', '$2y$10$24G8vZkOkrd5FLM2tUxuxe5R1RUGP8qaAV42IYm0wgWR83ZFDS8hC', 'guest', 'user', 'active', NOW());

INSERT INTO user_emails (user_id, email, is_ashesi, verified_at) VALUES
(2, 'ama.mensah@ashesi.edu.gh', 1, NOW());

-- Categories
INSERT INTO categories (id, name, slug, sort_order) VALUES
(1, 'Food & Meals', 'food-meals', 1),
(2, 'Snacks & Drinks', 'snacks-drinks', 2),
(3, 'Stationery & Books', 'stationery-books', 3),
(4, 'Fashion & Accessories', 'fashion-accessories', 4),
(5, 'Electronics & Gadgets', 'electronics-gadgets', 5);

-- Vendors (5 approved, 1 pending so the admin can approve one in the demo)
INSERT INTO vendors (id, user_id, shop_name, description, phone, opens_at, closes_at, status, reviewed_by, reviewed_at) VALUES
(1, 4, 'Mama Esi''s Kitchen', 'Home-style Ghanaian meals, cooked fresh daily.', '0200000001', '07:30:00', '18:00:00', 'approved', 1, NOW()),
(2, 5, 'Campus Bites', 'Quick snacks, pastries and light bites.', '0200000002', '08:00:00', '20:00:00', 'approved', 1, NOW()),
(3, 6, 'Brew & Chill', 'Coffee, smoothies and cold drinks.', '0200000003', '07:00:00', '19:00:00', 'approved', 1, NOW()),
(4, 7, 'Print & Pages', 'Stationery, notebooks and printing supplies.', '0200000004', '09:00:00', '17:00:00', 'approved', 1, NOW()),
(5, 8, 'Kente Corner', 'Kente accessories, bags and campus wear.', '0200000005', '10:00:00', '18:00:00', 'approved', 1, NOW()),
(6, 9, 'Gadget Hub', 'Phone accessories and small electronics.', '0200000006', NULL, NULL, 'pending', NULL, NULL);

-- Items (43 products across the 5 categories; vendor 6 is pending)
INSERT INTO items (id, vendor_id, category_id, name, description, price, stock) VALUES
(1, 1, 1, 'Jollof Rice with Chicken', 'Smoky jollof, grilled chicken and shito.', 35.00, 40),
(2, 1, 1, 'Waakye Special', 'Waakye with egg, gari, spaghetti and stew.', 30.00, 35),
(3, 1, 1, 'Banku and Tilapia', 'Fresh banku with grilled tilapia and pepper.', 45.00, 25),
(4, 1, 1, 'Fried Rice and Beef', 'Vegetable fried rice with spiced beef.', 35.00, 30),
(5, 1, 1, 'Red Red with Plantain', 'Black-eyed beans stew with fried plantain.', 25.00, 30),
(6, 1, 1, 'Kenkey and Fried Fish', 'Ga kenkey, fried fish and hot pepper.', 28.00, 20),
(7, 1, 1, 'Plain Rice and Stew', 'Steamed rice with tomato stew.', 20.00, 50),
(8, 1, 1, 'Vegetable Salad Bowl', 'Fresh salad with boiled egg.', 22.00, 20),
(9, 2, 2, 'Meat Pie', 'Flaky pastry with minced beef filling.', 12.00, 60),
(10, 2, 2, 'Chicken Samosa (3 pcs)', 'Crispy samosas with dipping sauce.', 15.00, 50),
(11, 2, 2, 'Doughnut', 'Freshly fried sugar doughnut.', 5.00, 80),
(12, 2, 2, 'Bofrot (5 pcs)', 'Ghanaian puff-puff, freshly fried.', 8.00, 70),
(13, 2, 2, 'Spring Rolls (4 pcs)', 'Vegetable spring rolls.', 14.00, 40),
(14, 2, 2, 'Peanut Packet', 'Roasted groundnuts.', 4.00, 100),
(15, 2, 2, 'Plantain Chips', 'Crunchy salted plantain chips.', 6.00, 90),
(16, 2, 2, 'Fruit Cup', 'Pineapple, watermelon and pawpaw.', 10.00, 30),
(17, 3, 2, 'Iced Coffee', 'Cold brew over ice.', 18.00, 50),
(18, 3, 2, 'Mango Smoothie', 'Fresh mango blended with yoghurt.', 20.00, 40),
(19, 3, 2, 'Sobolo (500ml)', 'Chilled hibiscus drink with ginger.', 10.00, 60),
(20, 3, 2, 'Fresh Orange Juice', 'Squeezed to order.', 15.00, 40),
(21, 3, 2, 'Hot Chocolate', 'Rich hot cocoa.', 14.00, 35),
(22, 3, 2, 'Bottled Water (750ml)', 'Chilled bottled water.', 3.00, 200),
(23, 4, 3, 'A4 Notebook (200 pages)', 'Ruled hardcover notebook.', 25.00, 60),
(24, 4, 3, 'Ballpoint Pens (pack of 10)', 'Blue ballpoint pens.', 12.00, 80),
(25, 4, 3, 'Highlighter Set (5 colours)', 'Assorted colour highlighters.', 15.00, 50),
(26, 4, 3, 'Scientific Calculator', 'Casio-style scientific calculator.', 85.00, 20),
(27, 4, 3, 'Sticky Notes Pack', 'Assorted colour sticky notes.', 8.00, 70),
(28, 4, 3, 'A4 Printing (per page)', 'Black-and-white printing.', 0.50, 500),
(29, 4, 3, 'USB Flash Drive 32GB', '32GB USB 3.0 drive.', 60.00, 25),
(30, 4, 3, 'Pencil Case', 'Zip pencil case.', 10.00, 45),
(31, 5, 4, 'Kente Bracelet', 'Handwoven kente bracelet.', 20.00, 40),
(32, 5, 4, 'Kente Tote Bag', 'Roomy tote with kente trim.', 70.00, 20),
(33, 5, 4, 'Campus Hoodie', 'Soft fleece hoodie.', 120.00, 30),
(34, 5, 4, 'Beaded Necklace', 'Handmade beaded necklace.', 35.00, 25),
(35, 5, 4, 'Baseball Cap', 'Adjustable cotton cap.', 40.00, 35),
(36, 5, 4, 'Kente Bow Tie', 'Handmade kente bow tie.', 30.00, 20),
(37, 5, 4, 'Canvas Backpack', 'Durable canvas backpack.', 95.00, 15),
(38, 6, 5, 'Phone Charger (USB-C)', 'Fast USB-C charger.', 45.00, 40),
(39, 6, 5, 'Earphones', 'Wired earphones with mic.', 30.00, 50),
(40, 6, 5, 'Power Bank 10000mAh', 'Slim portable power bank.', 120.00, 20),
(41, 6, 5, 'Phone Case', 'Shockproof silicone case.', 25.00, 60),
(42, 6, 5, 'Laptop Stand', 'Foldable aluminium stand.', 80.00, 15),
(43, 6, 5, 'Bluetooth Speaker', 'Portable waterproof speaker.', 150.00, 10);

-- Services and providers (match the sample data in app/Views/Resources)
INSERT INTO resource_services (id, name, description) VALUES
(1, 'Academic Advising', 'Get guidance on your courses and academic progress.'),
(2, 'Peer Tutoring', 'Receive academic assistance from peer tutors.'),
(3, 'Counselling', 'Access confidential student support services.'),
(4, 'Career Services', 'Get advice on internships and career development.');

INSERT INTO providers (id, service_id, name) VALUES
(1, 1, 'Academic Advisor A'),
(2, 1, 'Academic Advisor B'),
(3, 2, 'Peer Tutor A'),
(4, 2, 'Peer Tutor B'),
(5, 3, 'Counsellor A'),
(6, 4, 'Career Advisor A');

-- Time slots: the next 7 days from the day you import, 3 per provider per day
INSERT INTO time_slots (provider_id, slot_date, start_time, end_time) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(5, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:00:00', '14:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00', '11:50:00'),
(6, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '14:00:00', '14:50:00');
