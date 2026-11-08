USE food_share;

-- All seed accounts use the password: password123
SET @pw = '$2y$10$2JWV.Q1K7nMql1KajTlkvuEUkP83U2Q6RdBezO6/yyE9q9Bd63vRy';

INSERT INTO locations (id, address, area, city) VALUES
(1, '12 Main Boulevard', 'Gulberg', 'Lahore'),
(2, 'Royal Palm Marquee, Canal Road', 'Johar Town', 'Lahore'),
(3, '5 Hope Street', 'Model Town', 'Lahore'),
(4, 'Liberty Market, Block C', 'Gulberg', 'Lahore'),
(5, 'F-7 Markaz, Jinnah Super', 'F-7', 'Islamabad');

INSERT INTO users (id, full_name, email, phone, password_hash, role, organization_name, is_verified, location_id) VALUES
(1, 'Site Admin',      'admin@saveandserve.test',     '03000000001', @pw, 'admin',     NULL, 0, NULL),
(2, 'Spice Garden',    'donor@saveandserve.test',     '03000000002', @pw, 'donor',     'Spice Garden Restaurant', 0, 1),
(3, 'Hope Foundation', 'ngo@saveandserve.test',       '03000000003', @pw, 'ngo',       NULL, 0, 3),
(4, 'Ali Volunteer',   'volunteer@saveandserve.test', '03000000004', @pw, 'volunteer', NULL, 1, NULL);

INSERT INTO ngos (id, owner_user_id, name, registration_no, location_id, is_verified) VALUES
(1, 3, 'Hope Foundation', 'NGO-2024-77', 3, 1);

INSERT INTO ngo_members (ngo_id, user_id, member_role) VALUES
(1, 3, 'manager'),
(1, 4, 'volunteer');

INSERT INTO food_categories (id, name) VALUES
(1, 'Cooked meals'), (2, 'Bakery'), (3, 'Fruits & vegetables'), (4, 'Packaged food'), (5, 'Wedding / event leftovers');

INSERT INTO food_posts (id, donor_id, category_id, pickup_location_id, title, description, quantity, unit, pickup_from, pickup_until, status) VALUES
(1, 2, 1, 1, 'Chicken biryani', 'Freshly cooked, packed in boxes.', 40, 'plates', NOW(), DATE_ADD(NOW(), INTERVAL 4 HOUR), 'available'),
(2, 2, 2, 1, 'Bread and buns', 'Baked today, unsold.', 25, 'packs', NOW(), DATE_ADD(NOW(), INTERVAL 8 HOUR), 'available'),
(3, 2, 5, 2, 'Wedding dinner surplus', 'Rice, curry and salad.', 60, 'plates', NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR), 'claimed'),
(4, 2, 3, 4, 'Seasonal fruit crates', 'Bananas, apples and oranges, slightly ripe.', 15, 'kg', NOW(), DATE_ADD(NOW(), INTERVAL 6 HOUR), 'available'),
(5, 2, 4, 1, 'Packaged juice and biscuits', 'Sealed packs, best before next month.', 50, 'packs', NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 'available'),
(6, 2, 5, 2, 'Mehndi function leftovers', 'Pulao, korma and naan, kept warm.', 80, 'plates', NOW(), DATE_ADD(NOW(), INTERVAL 3 HOUR), 'available'),
(7, 2, 1, 5, 'Daal chawal', 'Lunch surplus, freshly cooked.', 30, 'plates', NOW(), DATE_ADD(NOW(), INTERVAL 5 HOUR), 'available');

INSERT INTO claims (post_id, claimed_by, ngo_id) VALUES
(3, 3, 1);

INSERT INTO post_status_logs (post_id, changed_by, old_status, new_status) VALUES
(1, 2, NULL, 'available'),
(2, 2, NULL, 'available'),
(3, 2, NULL, 'available'),
(3, 3, 'available', 'claimed'),
(4, 2, NULL, 'available'),
(5, 2, NULL, 'available'),
(6, 2, NULL, 'available'),
(7, 2, NULL, 'available');

-- ---------------------------------------------------------------------------
-- Two weeks of past activity, so the Impact page charts have something to show.
-- Times are relative to NOW(); every post has a matching status history.
-- ---------------------------------------------------------------------------
INSERT INTO locations (id, address, area, city) VALUES
(6, 'Daily Bread, MM Alam Road', 'Gulberg', 'Lahore'),
(7, 'Rizq Welfare Centre, Commercial Market', 'Satellite Town', 'Rawalpindi');

INSERT INTO users (id, full_name, email, phone, password_hash, role, organization_name, is_verified, location_id) VALUES
(5, 'Royal Palm Marquee', 'marquee@saveandserve.test', '03000000005', @pw, 'donor',     'Royal Palm Marquee', 0, 2),
(6, 'Daily Bread',        'bakery@saveandserve.test',  '03000000006', @pw, 'donor',     'Daily Bread Bakery', 0, 6),
(7, 'Rizq Welfare',       'rizq@saveandserve.test',    '03000000007', @pw, 'ngo',       NULL, 0, 7),
(8, 'Sara Khan',          'sara@saveandserve.test',    '03000000008', @pw, 'volunteer', NULL, 1, NULL);

INSERT INTO ngos (id, owner_user_id, name, registration_no, location_id, is_verified) VALUES
(2, 7, 'Rizq Welfare', 'NGO-2023-31', 7, 1);

INSERT INTO ngo_members (ngo_id, user_id, member_role) VALUES
(2, 7, 'manager');

INSERT INTO food_posts (id, donor_id, category_id, pickup_location_id, title, description, quantity, unit, pickup_from, pickup_until, status, created_at) VALUES
(101, 2, 1, 1, 'Chicken karahi', NULL, 35, 'plates', DATE_SUB(NOW(), INTERVAL 18660 MINUTE), DATE_SUB(NOW(), INTERVAL 18420 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 18660 MINUTE)),
(102, 5, 5, 2, 'Walima dinner surplus', NULL, 90, 'plates', DATE_SUB(NOW(), INTERVAL 18180 MINUTE), DATE_SUB(NOW(), INTERVAL 17940 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 18180 MINUTE)),
(103, 6, 2, 6, 'Day-old bread loaves', NULL, 30, 'packs', DATE_SUB(NOW(), INTERVAL 17460 MINUTE), DATE_SUB(NOW(), INTERVAL 17220 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 17460 MINUTE)),
(104, 2, 3, 1, 'Fresh salad vegetables', NULL, 12, 'kg', DATE_SUB(NOW(), INTERVAL 17100 MINUTE), DATE_SUB(NOW(), INTERVAL 16860 MINUTE), 'available', DATE_SUB(NOW(), INTERVAL 17100 MINUTE)),
(105, 5, 5, 2, 'Mehndi buffet leftovers', NULL, 70, 'plates', DATE_SUB(NOW(), INTERVAL 15240 MINUTE), DATE_SUB(NOW(), INTERVAL 15000 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 15240 MINUTE)),
(106, 6, 2, 6, 'Croissants and buns', NULL, 40, 'packs', DATE_SUB(NOW(), INTERVAL 14520 MINUTE), DATE_SUB(NOW(), INTERVAL 14280 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 14520 MINUTE)),
(107, 2, 1, 1, 'Daal and rice', NULL, 25, 'plates', DATE_SUB(NOW(), INTERVAL 14280 MINUTE), DATE_SUB(NOW(), INTERVAL 14040 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 14280 MINUTE)),
(108, 2, 4, 1, 'Juice boxes', NULL, 60, 'packs', DATE_SUB(NOW(), INTERVAL 12960 MINUTE), DATE_SUB(NOW(), INTERVAL 12720 MINUTE), 'withdrawn', DATE_SUB(NOW(), INTERVAL 12960 MINUTE)),
(109, 5, 1, 2, 'Barat dinner surplus', NULL, 110, 'plates', DATE_SUB(NOW(), INTERVAL 12420 MINUTE), DATE_SUB(NOW(), INTERVAL 12180 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 12420 MINUTE)),
(110, 6, 2, 6, 'Sandwich bread', NULL, 20, 'packs', DATE_SUB(NOW(), INTERVAL 11700 MINUTE), DATE_SUB(NOW(), INTERVAL 11460 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 11700 MINUTE)),
(111, 2, 1, 1, 'Vegetable biryani', NULL, 30, 'plates', DATE_SUB(NOW(), INTERVAL 10020 MINUTE), DATE_SUB(NOW(), INTERVAL 9780 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 10020 MINUTE)),
(112, 6, 2, 6, 'Cupcakes', NULL, 48, 'packs', DATE_SUB(NOW(), INTERVAL 9840 MINUTE), DATE_SUB(NOW(), INTERVAL 9600 MINUTE), 'available', DATE_SUB(NOW(), INTERVAL 9840 MINUTE)),
(113, 5, 5, 2, 'Engagement party food', NULL, 65, 'plates', DATE_SUB(NOW(), INTERVAL 8040 MINUTE), DATE_SUB(NOW(), INTERVAL 7800 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 8040 MINUTE)),
(114, 2, 3, 1, 'Seasonal fruit', NULL, 18, 'kg', DATE_SUB(NOW(), INTERVAL 7260 MINUTE), DATE_SUB(NOW(), INTERVAL 7020 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 7260 MINUTE)),
(115, 6, 2, 6, 'Rusks and cookies', NULL, 35, 'packs', DATE_SUB(NOW(), INTERVAL 7020 MINUTE), DATE_SUB(NOW(), INTERVAL 6780 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 7020 MINUTE)),
(116, 2, 1, 1, 'Chicken pulao', NULL, 40, 'plates', DATE_SUB(NOW(), INTERVAL 5700 MINUTE), DATE_SUB(NOW(), INTERVAL 5460 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 5700 MINUTE)),
(117, 5, 5, 2, 'Wedding reception surplus', NULL, 95, 'plates', DATE_SUB(NOW(), INTERVAL 5220 MINUTE), DATE_SUB(NOW(), INTERVAL 4980 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 5220 MINUTE)),
(118, 6, 2, 6, 'Bread rolls', NULL, 25, 'packs', DATE_SUB(NOW(), INTERVAL 4440 MINUTE), DATE_SUB(NOW(), INTERVAL 4200 MINUTE), 'available', DATE_SUB(NOW(), INTERVAL 4440 MINUTE)),
(119, 2, 4, 1, 'Packaged snacks', NULL, 45, 'packs', DATE_SUB(NOW(), INTERVAL 4200 MINUTE), DATE_SUB(NOW(), INTERVAL 3960 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 4200 MINUTE)),
(120, 2, 1, 1, 'Aloo keema', NULL, 28, 'plates', DATE_SUB(NOW(), INTERVAL 2820 MINUTE), DATE_SUB(NOW(), INTERVAL 2580 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 2820 MINUTE)),
(121, 5, 5, 2, 'Birthday party food', NULL, 50, 'plates', DATE_SUB(NOW(), INTERVAL 2280 MINUTE), DATE_SUB(NOW(), INTERVAL 2040 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 2280 MINUTE)),
(122, 6, 2, 6, 'Naan and paratha', NULL, 60, 'packs', DATE_SUB(NOW(), INTERVAL 1620 MINUTE), DATE_SUB(NOW(), INTERVAL 1380 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 1620 MINUTE)),
(123, 2, 3, 1, 'Bananas and apples', NULL, 15, 'kg', DATE_SUB(NOW(), INTERVAL 1320 MINUTE), DATE_SUB(NOW(), INTERVAL 1080 MINUTE), 'available', DATE_SUB(NOW(), INTERVAL 1320 MINUTE)),
(124, 5, 1, 2, 'Dinner surplus', NULL, 55, 'plates', DATE_SUB(NOW(), INTERVAL 960 MINUTE), DATE_SUB(NOW(), INTERVAL 720 MINUTE), 'collected', DATE_SUB(NOW(), INTERVAL 960 MINUTE));

INSERT INTO claims (post_id, claimed_by, ngo_id, claimed_at, collected_at) VALUES
(101, 3, 1, DATE_SUB(NOW(), INTERVAL 18635 MINUTE), DATE_SUB(NOW(), INTERVAL 18540 MINUTE)),
(102, 7, 2, DATE_SUB(NOW(), INTERVAL 18162 MINUTE), DATE_SUB(NOW(), INTERVAL 18067 MINUTE)),
(103, 8, NULL, DATE_SUB(NOW(), INTERVAL 17405 MINUTE), DATE_SUB(NOW(), INTERVAL 17310 MINUTE)),
(105, 3, 1, DATE_SUB(NOW(), INTERVAL 15228 MINUTE), DATE_SUB(NOW(), INTERVAL 15133 MINUTE)),
(106, 4, 1, DATE_SUB(NOW(), INTERVAL 14480 MINUTE), DATE_SUB(NOW(), INTERVAL 14385 MINUTE)),
(107, 8, NULL, DATE_SUB(NOW(), INTERVAL 14190 MINUTE), DATE_SUB(NOW(), INTERVAL 14095 MINUTE)),
(109, 7, 2, DATE_SUB(NOW(), INTERVAL 12405 MINUTE), DATE_SUB(NOW(), INTERVAL 12310 MINUTE)),
(110, 3, 1, DATE_SUB(NOW(), INTERVAL 11665 MINUTE), DATE_SUB(NOW(), INTERVAL 11570 MINUTE)),
(111, 4, 1, DATE_SUB(NOW(), INTERVAL 10000 MINUTE), DATE_SUB(NOW(), INTERVAL 9905 MINUTE)),
(113, 7, 2, DATE_SUB(NOW(), INTERVAL 8030 MINUTE), DATE_SUB(NOW(), INTERVAL 7935 MINUTE)),
(114, 8, NULL, DATE_SUB(NOW(), INTERVAL 7215 MINUTE), DATE_SUB(NOW(), INTERVAL 7120 MINUTE)),
(115, 3, 1, DATE_SUB(NOW(), INTERVAL 6960 MINUTE), DATE_SUB(NOW(), INTERVAL 6865 MINUTE)),
(116, 7, 2, DATE_SUB(NOW(), INTERVAL 5686 MINUTE), DATE_SUB(NOW(), INTERVAL 5591 MINUTE)),
(117, 3, 1, DATE_SUB(NOW(), INTERVAL 5211 MINUTE), DATE_SUB(NOW(), INTERVAL 5116 MINUTE)),
(119, 4, 1, DATE_SUB(NOW(), INTERVAL 4130 MINUTE), DATE_SUB(NOW(), INTERVAL 4035 MINUTE)),
(120, 8, NULL, DATE_SUB(NOW(), INTERVAL 2798 MINUTE), DATE_SUB(NOW(), INTERVAL 2703 MINUTE)),
(121, 7, 2, DATE_SUB(NOW(), INTERVAL 2264 MINUTE), DATE_SUB(NOW(), INTERVAL 2169 MINUTE)),
(122, 3, 1, DATE_SUB(NOW(), INTERVAL 1590 MINUTE), DATE_SUB(NOW(), INTERVAL 1495 MINUTE)),
(124, 7, 2, DATE_SUB(NOW(), INTERVAL 949 MINUTE), DATE_SUB(NOW(), INTERVAL 854 MINUTE));

INSERT INTO post_status_logs (post_id, changed_by, old_status, new_status, changed_at) VALUES
(101, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 18660 MINUTE)),
(101, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 18635 MINUTE)),
(101, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 18540 MINUTE)),
(102, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 18180 MINUTE)),
(102, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 18162 MINUTE)),
(102, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 18067 MINUTE)),
(103, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 17460 MINUTE)),
(103, 8, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 17405 MINUTE)),
(103, 6, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 17310 MINUTE)),
(104, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 17100 MINUTE)),
(105, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 15240 MINUTE)),
(105, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 15228 MINUTE)),
(105, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 15133 MINUTE)),
(106, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 14520 MINUTE)),
(106, 4, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 14480 MINUTE)),
(106, 6, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 14385 MINUTE)),
(107, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 14280 MINUTE)),
(107, 8, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 14190 MINUTE)),
(107, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 14095 MINUTE)),
(108, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 12960 MINUTE)),
(108, 2, 'available', 'withdrawn', DATE_SUB(NOW(), INTERVAL 12910 MINUTE)),
(109, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 12420 MINUTE)),
(109, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 12405 MINUTE)),
(109, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 12310 MINUTE)),
(110, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 11700 MINUTE)),
(110, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 11665 MINUTE)),
(110, 6, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 11570 MINUTE)),
(111, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 10020 MINUTE)),
(111, 4, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 10000 MINUTE)),
(111, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 9905 MINUTE)),
(112, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 9840 MINUTE)),
(113, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 8040 MINUTE)),
(113, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 8030 MINUTE)),
(113, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 7935 MINUTE)),
(114, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 7260 MINUTE)),
(114, 8, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 7215 MINUTE)),
(114, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 7120 MINUTE)),
(115, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 7020 MINUTE)),
(115, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 6960 MINUTE)),
(115, 6, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 6865 MINUTE)),
(116, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 5700 MINUTE)),
(116, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 5686 MINUTE)),
(116, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 5591 MINUTE)),
(117, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 5220 MINUTE)),
(117, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 5211 MINUTE)),
(117, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 5116 MINUTE)),
(118, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 4440 MINUTE)),
(119, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 4200 MINUTE)),
(119, 4, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 4130 MINUTE)),
(119, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 4035 MINUTE)),
(120, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 2820 MINUTE)),
(120, 8, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 2798 MINUTE)),
(120, 2, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 2703 MINUTE)),
(121, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 2280 MINUTE)),
(121, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 2264 MINUTE)),
(121, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 2169 MINUTE)),
(122, 6, NULL, 'available', DATE_SUB(NOW(), INTERVAL 1620 MINUTE)),
(122, 3, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 1590 MINUTE)),
(122, 6, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 1495 MINUTE)),
(123, 2, NULL, 'available', DATE_SUB(NOW(), INTERVAL 1320 MINUTE)),
(124, 5, NULL, 'available', DATE_SUB(NOW(), INTERVAL 960 MINUTE)),
(124, 7, 'available', 'claimed', DATE_SUB(NOW(), INTERVAL 949 MINUTE)),
(124, 5, 'claimed', 'collected', DATE_SUB(NOW(), INTERVAL 854 MINUTE));
