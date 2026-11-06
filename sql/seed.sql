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
(1, 'Site Admin',      'admin@foodshare.test',     '03000000001', @pw, 'admin',     NULL, 0, NULL),
(2, 'Spice Garden',    'donor@foodshare.test',     '03000000002', @pw, 'donor',     'Spice Garden Restaurant', 0, 1),
(3, 'Hope Foundation', 'ngo@foodshare.test',       '03000000003', @pw, 'ngo',       NULL, 0, 3),
(4, 'Ali Volunteer',   'volunteer@foodshare.test', '03000000004', @pw, 'volunteer', NULL, 1, NULL);

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
