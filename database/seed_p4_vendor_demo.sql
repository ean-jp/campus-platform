-- P4 demo data for the vendor pages (OPTIONAL, for testing and the demo).
-- Run AFTER schema.sql and seed.sql:
--   mysql -u root campus_platform < database/seed_p4_vendor_demo.sql
--
-- Turns "Kente Corner" (vendor 5, owner user 8: vendor5@campus-platform.test / Password123!)
-- into a student vendor who sells a mix of things, and gives it some orders.
-- Until P3's checkout exists, these orders are how the vendor Orders page gets data.
-- Re-runnable: it removes only the rows it created (ids 9001+ for orders, 101+ for items).

USE campus_platform;

UPDATE vendors SET vendor_type = 'student', location = 'Hostel 2B common room',
  description = 'Student-run shop: bags, campus wear, gadgets, books and snacks.',
  opens_at = '08:00:00', closes_at = '21:00:00'
WHERE id = 5;

-- conditions + how buyers get things for Kente Corner's existing products
UPDATE items SET item_condition = 'new', allow_pickup = 1, allow_delivery = 1 WHERE vendor_id = 5 AND id IN (31, 32, 34, 36);
UPDATE items SET item_condition = 'like_new', allow_pickup = 1, allow_delivery = 0 WHERE id = 33;
UPDATE items SET item_condition = 'good', allow_pickup = 1, allow_delivery = 0, stock = 0 WHERE id = 37;   -- sold out
UPDATE items SET item_condition = 'new', stock = 3 WHERE id = 35;                                           -- running low

-- a few extra products in other categories, so it looks like a real student seller
DELETE FROM order_items WHERE order_id >= 9001;
DELETE FROM orders WHERE id >= 9001;
DELETE FROM items WHERE id BETWEEN 101 AND 110;
INSERT INTO items (id, vendor_id, category_id, name, description, price, stock, item_condition, allow_pickup, allow_delivery, status) VALUES
(101, 5, 5, 'Wireless Earbuds', 'Bluetooth 5.3 earbuds with charging case. Used for one semester.', 180.00, 2, 'like_new', 1, 0, 'active'),
(102, 5, 3, 'Intro to Economics Textbook', 'Mankiw, 8th edition. Some highlighting in chapters 1 to 4.', 90.00, 1, 'good', 1, 0, 'active'),
(103, 5, 2, 'Chin Chin Pack', 'Crunchy home-made chin chin, 250 g.', 15.00, 25, 'new', 1, 1, 'active'),
(104, 5, 5, 'Desk Lamp (USB)', 'LED lamp with 3 brightness levels.', 65.00, 4, 'good', 1, 0, 'hidden');

-- orders (P3's checkout makes these in the real app). Buyers: user 2 (Ashesi), user 3 (guest)
INSERT INTO orders (id, user_id, vendor_id, status, total, fulfilment, delivery_spot, contact_phone, pickup_code, created_at) VALUES
(9001, 2, 5, 'received',  180.00, 'pickup',   NULL,                 NULL,         '4821', NOW() - INTERVAL 12 MINUTE),
(9002, 3, 5, 'received',   45.00, 'delivery', 'Hostel 3, Room 22',  '0240000003', '1937', NOW() - INTERVAL 25 MINUTE),
(9003, 2, 5, 'preparing', 120.00, 'pickup',   NULL,                 NULL,         '5562', NOW() - INTERVAL 1 HOUR),
(9004, 2, 5, 'ready',      90.00, 'pickup',   NULL,                 NULL,         '2468', NOW() - INTERVAL 2 HOUR),
(9005, 3, 5, 'completed',  70.00, 'pickup',   NULL,                 '0240000003', '7310', NOW() - INTERVAL 1 DAY),
(9006, 2, 5, 'cancelled',  20.00, 'pickup',   NULL,                 NULL,         '8642', NOW() - INTERVAL 2 DAY);
UPDATE orders SET updated_at = created_at + INTERVAL 30 MINUTE WHERE id = 9005;

INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity) VALUES
(9001, 101, 'Wireless Earbuds', 180.00, 1),
(9002, 103, 'Chin Chin Pack', 15.00, 3),
(9003, 33, 'Campus Hoodie', 120.00, 1),
(9004, 102, 'Intro to Economics Textbook', 90.00, 1),
(9005, 32, 'Kente Tote Bag', 70.00, 1),
(9006, 31, 'Kente Bracelet', 20.00, 1);

-- Stage 3: a week of completed orders for the Sales page, and some notifications for the bell
DELETE FROM notifications WHERE user_id = 8;
INSERT INTO orders (id, user_id, vendor_id, status, total, fulfilment, pickup_code, created_at, updated_at) VALUES
(9010, 2, 5, 'completed', 180.00, 'pickup',   '1111', NOW() - INTERVAL 6 DAY - INTERVAL 3 HOUR, NOW() - INTERVAL 6 DAY - INTERVAL 2 HOUR),
(9011, 3, 5, 'completed',  45.00, 'delivery', '1112', NOW() - INTERVAL 5 DAY - INTERVAL 5 HOUR, NOW() - INTERVAL 5 DAY - INTERVAL 4 HOUR),
(9012, 2, 5, 'completed', 140.00, 'pickup',   '1113', NOW() - INTERVAL 4 DAY - INTERVAL 2 HOUR, NOW() - INTERVAL 4 DAY - INTERVAL 1 HOUR),
(9013, 3, 5, 'completed',  90.00, 'pickup',   '1114', NOW() - INTERVAL 3 DAY - INTERVAL 6 HOUR, NOW() - INTERVAL 3 DAY - INTERVAL 5 HOUR),
(9014, 2, 5, 'completed', 240.00, 'pickup',   '1115', NOW() - INTERVAL 2 DAY - INTERVAL 4 HOUR, NOW() - INTERVAL 2 DAY - INTERVAL 3 HOUR),
(9015, 2, 5, 'completed',  30.00, 'delivery', '1116', NOW() - INTERVAL 10 DAY,                  NOW() - INTERVAL 10 DAY + INTERVAL 1 HOUR);
INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity) VALUES
(9010, 101, 'Wireless Earbuds', 180.00, 1),
(9011, 103, 'Chin Chin Pack', 15.00, 3),
(9012, 32, 'Kente Tote Bag', 70.00, 2),
(9013, 102, 'Intro to Economics Textbook', 90.00, 1),
(9014, 33, 'Campus Hoodie', 120.00, 2),
(9015, 103, 'Chin Chin Pack', 15.00, 2);
INSERT INTO notifications (user_id, type, message, link, is_read, created_at) VALUES
(8, 'vendor_approved', 'Your shop Kente Corner was approved. You can now add products.', 'vendor/products.html', 1, NOW() - INTERVAL 9 DAY),
(8, 'order_new', 'New order #9004 from Ama M.', 'vendor/orders.html#order-9004', 1, NOW() - INTERVAL 2 HOUR),
(8, 'order_new', 'New order #9002 (delivery to Hostel 3)', 'vendor/orders.html#order-9002', 0, NOW() - INTERVAL 25 MINUTE),
(8, 'order_new', 'New order #9001 from Ama M.', 'vendor/orders.html#order-9001', 0, NOW() - INTERVAL 12 MINUTE);
