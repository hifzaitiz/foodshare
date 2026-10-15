# PRD: Food Donation / Surplus Sharing Platform

**Stack:** LAMP (Linux, Apache, MySQL, PHP) with HTML/CSS/JS frontend

## 1. Problem
Restaurants and wedding halls waste edible food while people go hungry. There is no simple way to tell NGOs and volunteers that food is available, where, and until when.

## 2. Goal
A website where donors post surplus food with quantity and pickup time, and NGOs or volunteers claim it. Every post moves through: **available → claimed → collected**.

## 3. Users
| Role | What they do |
|---|---|
| Donor | Posts surplus food, sees who claimed it |
| NGO | Registers (admin verifies), claims food |
| Volunteer | Claims food |
| Admin | Verifies NGOs |

## 4. Features
**Required for submission**
1. Signup with role (donor / ngo / volunteer)
2. Login and logout (PHP sessions, hashed passwords)
3. Home page after login, different for each role:
   - Donor: my food posts
   - NGO / volunteer: available food and my claims
   - Admin: NGOs waiting for verification

4. Donor posts food (`post_food.php`) and marks it collected (`collect.php`)
5. NGO / verified volunteer filters by city, area, category and claims (`claim.php`) or cancels (`cancel.php`)
6. Admin verifies NGOs and volunteers (`verify.php`)

## 5. Rules
- Email must be unique; password at least 8 characters, stored with `password_hash`.
- Only posts with status `available` and pickup time not over are listed.
- A post can be claimed only once (`claims.post_id` is unique).
- Only verified volunteers or members of verified NGOs can claim.
- A claimer can cancel; the post returns to `available`.

## 6. Status flow
`available` → (someone claims) → `claimed` → (donor confirms pickup) → `collected`

## 7. Database (8 tables, see docs/erd.mmd)
| Table | Purpose |
|---|---|
| users | Everyone who logs in; `role` decides what they see |
| locations | Addresses, shared by users, NGOs and food posts |
| ngos | Registered organisations and their verification flag |
| ngo_members | Links volunteers/managers to NGOs (many-to-many) |
| food_categories | Type of food (cooked, bakery...) for filtering |
| food_posts | Food offered by a donor, with quantity, pickup window and status |
| claims | Who claimed which post, and when it was collected |
| post_status_logs | History of each post: available → claimed → collected |

Key points to explain:
- `ngo_members` is the link table: one NGO has many members, one user can belong to several NGOs.
- `post_status_logs` records every status change, with who made it.
- `locations` is one table reused for user, NGO and pickup addresses.
- `claims.ngo_id` is optional: a volunteer can claim alone, an NGO user claims for the NGO.
- `claims.post_id` is unique, so a post cannot be claimed twice.

## 8. Pages
`index.php`, `signup.php`, `login.php`, `logout.php`, `home.php`, `post_food.php`, `claim.php`, `cancel.php`, `collect.php`, `verify.php`
