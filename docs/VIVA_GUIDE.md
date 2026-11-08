# Save & Serve: Viva Study Guide

A complete walkthrough of how the project works, written to study from. Read it in order;
every section builds on the one before. File links open the real code, so keep the code
open next to this guide and check each claim yourself.

---

## 0. How to use this guide (a 5-day plan)

| Day | Study | Then practise |
|---|---|---|
| 1 | Sections 1–3: big picture, folders, how one request works | Explain the request lifecycle out loud in 2 minutes |
| 2 | Section 4: the database, table by table | Draw the 8 tables and their links on paper from memory |
| 3 | Sections 5–6: the shared helpers and every user flow | Click through each flow in the browser while reading its code |
| 4 | Sections 7–8: security and the frontend | Explain each security row of the table without looking |
| 5 | Sections 9–11: viva questions, demo script, limitations | Do the full demo twice, answer the questions aloud |

**Golden rule:** for every feature, be able to say *which file*, *which function*, and *which table*.

---

## 1. The big picture

### What problem it solves
Restaurants, bakeries and wedding halls throw away edible food while people nearby go hungry.
The problem is **coordination**: who has food, how much, where, until when, and who will collect it.

### What the system does (one sentence)
Donors post surplus food with a pickup window; verified NGOs and volunteers claim it; the donor
confirms the handover with a pickup code; every step is recorded.

### The four roles
| Role | Can do |
|---|---|
| **Donor** | Post food, edit/withdraw it while unclaimed, see who claimed it, confirm pickup with the code |
| **NGO** (manager) | Claim food for the NGO, manage a team of volunteers, edit NGO address |
| **Volunteer** | Claim food (if verified, or as a member of a verified NGO) |
| **Admin** | Verify or reject NGOs and volunteers |

Everyone logged in also gets: post detail + history timeline, Impact dashboard, Profile page.
Visitors who are not logged in see the public homepage.

### The life of a food post (memorise this)
```
               claim                  donor enters pickup code
 available ─────────────► claimed ─────────────────────────► collected
   │  ▲                       │
   │  └──── claimer cancels ──┘
   ├── donor withdraws ─────► withdrawn
   └── pickup time passes ──► shown as "expired" (calculated, NOT stored)
```

### Technology stack
- **PHP 8** with **PDO** (database access) and **sessions** (login state). No framework.
- **MySQL / MariaDB** database.
- **HTML + CSS + vanilla JavaScript** on the front end.
- Two small bundled JS libraries: **Chart.js** (charts) and **qrcode-generator** (QR codes).

---

## 2. Folder and file map

```
index.php        Public homepage (logged-in users are redirected to home.php)
signup.php       Create an account (donor / ngo / volunteer)
login.php        Log in                       logout.php   Log out (POST + CSRF)
home.php         The dashboard; different content for each role
post_food.php    Create a post, or edit one with ?id=N
post.php         One post: details, history timeline, pickup code + QR
claim.php        Claim a post                 cancel.php   Cancel your claim
collect.php      Donor confirms pickup with the code
withdraw.php     Donor withdraws an unclaimed post
verify.php       Admin verifies / rejects an NGO or volunteer
team.php         NGO manager adds / removes volunteers
profile.php      Edit details, NGO address, change password
impact.php       Charts and leaderboards

config/db.php            Real DB login + APP_SECRET (git-ignored, private)
config/db.example.php    Template of db.php (committed)
includes/auth.php        Sessions, roles, CSRF, claim rules, pickup codes
includes/ui.php          Display helpers: icons, time left, tracker, status
includes/header.php      <head>, sidebar menu (for logged-in pages)
includes/footer.php      Closing tags + scripts
includes/auth_hero.php   The left panel on login/signup
sql/schema.sql           Creates the 8 tables
sql/seed.sql             Demo accounts, posts and two weeks of history
assets/css/style.css     All styling
assets/js/               validate.js, app.js, impact.js, pickup.js, vendor/
```

**Two kinds of PHP pages** (important pattern to explain):
1. **View pages** (`home.php`, `post.php`, `profile.php`, …) load data with `SELECT` and print HTML.
2. **Action pages** (`claim.php`, `cancel.php`, `collect.php`, `withdraw.php`, `verify.php`) receive a
   POST form, change the database, set a *flash* message, and **redirect** back. They print no HTML.
   This is the **Post/Redirect/Get** pattern: refreshing the page after an action never repeats it.

---

## 3. How one request works (the lifecycle)

Example: a volunteer clicks **Claim** on a food card.

1. The browser sends `POST /claim.php` with two hidden fields: `csrf` (the token) and `post_id`.
2. `claim.php` starts with `require_once 'includes/auth.php'`, which:
   - loads `config/db.php` (DB settings and the `db()` function),
   - loads `includes/ui.php` (helpers),
   - calls `session_start()` so `$_SESSION` holds who is logged in.
3. `require_role('ngo', 'volunteer')`: not logged in → redirect to login; wrong role → **403**.
4. `require_post()`: must be POST **and** the `csrf` field must equal the session token, else **400**.
5. `can_claim()`: checks the user is verified (or in a verified NGO).
6. Database work inside a **transaction** (see Flow 6.6).
7. `flash('success', '…')` stores a one-time message in the session.
8. `header('Location: home.php')`: the browser loads the dashboard, which shows and clears the flash.

`db()` in [config/db.example.php](../config/db.example.php) creates **one** PDO connection per request
(`static $pdo`) with three important options:
- `ERRMODE_EXCEPTION`: SQL errors throw exceptions (caught with `try/catch`, so we can roll back),
- `FETCH_ASSOC`: rows come back as arrays with column names as keys,
- `EMULATE_PREPARES => false`: real prepared statements, sent to MySQL separately from the data.

---

## 4. The database (8 tables)

See [docs/erd.mmd](erd.mmd) for the diagram.

| Table | Purpose | Key columns / rules |
|---|---|---|
| `users` | Everyone who logs in | `email` **UNIQUE**, `password_hash`, `role` ENUM(admin, donor, ngo, volunteer), `is_verified` + `rejected_at` (volunteers), `location_id` → locations |
| `locations` | Addresses (address, area, city) | Shared by users, NGOs and food posts |
| `ngos` | Registered organisations | `owner_user_id` → users, `registration_no` **UNIQUE**, `is_verified`, `rejected_at`, `location_id` (office) |
| `ngo_members` | **Link table**: which users belong to which NGO | Primary key (`ngo_id`, `user_id`), `member_role` ENUM(manager, volunteer) |
| `food_categories` | Cooked meals, Bakery, … | `name` UNIQUE |
| `food_posts` | A donation | `donor_id`, `category_id`, `pickup_location_id`, quantity/unit, `pickup_from`, `pickup_until`, `status` ENUM(available, claimed, collected, withdrawn) |
| `claims` | Who claimed which post | `post_id` **UNIQUE** (one claim per post), `claimed_by`, `ngo_id` (nullable), `claimed_at`, `collected_at` |
| `post_status_logs` | History of every status change | `post_id`, `changed_by`, `old_status`, `new_status`, `changed_at` |

### Design decisions you must be able to justify
- **Why a separate `locations` table?** One table reused for user addresses, NGO offices and pickup
  points, instead of repeating address columns in three tables (normalisation).
- **Why `ngo_members`?** Users ↔ NGOs is **many-to-many**: an NGO has many members and a volunteer can
  help several NGOs. Many-to-many needs a link table. Its composite primary key stops duplicate memberships.
- **Why is `claims.post_id` UNIQUE?** The database itself guarantees a post can never have two claims,
  even if the PHP code had a bug.
- **Why `claims.ngo_id` nullable?** A volunteer may claim alone (NULL) or on behalf of an NGO (set).
- **Why `post_status_logs`?** An audit trail: who changed what and when. It powers the timeline on
  `post.php` and the "average time to claim" on the Impact page.
- **Why is "expired" not stored?** It depends on the clock. Storing it would need a background job to
  update rows. Instead `display_status()` in [includes/ui.php](../includes/ui.php) calculates it:
  available + `pickup_until` in the past ⇒ show "expired".
- **Why `withdrawn` instead of deleting?** Deleting would also delete the post's history (CASCADE).
  A status keeps the record and logs the change.
- **Why `rejected_at` (a date) instead of a yes/no?** It records *when* the admin rejected, and
  `NULL` cleanly means "not rejected". Pending = not verified and `rejected_at IS NULL`.
- **Foreign key actions:** `ON DELETE CASCADE` where the child makes no sense without the parent
  (a claim without its post); `ON DELETE SET NULL` where the child can survive (a post whose category
  is deleted).

> Note: `withdrawn` and `rejected_at` were added after the ERD was first submitted. Say so if asked;
> the repo's ERD and `schema.sql` include them, and the README has the `ALTER TABLE` commands.

---

## 5. The shared helper functions

All in [includes/auth.php](../includes/auth.php) unless noted. Know each one in one sentence.

| Function | What it does |
|---|---|
| `e($value)` | Escapes text with `htmlspecialchars` before printing it (prevents XSS) |
| `is_logged_in()` | True if `$_SESSION['user_id']` is set |
| `require_login()` | Redirects to `login.php` if not logged in |
| `require_role(...$roles)` | `require_login()` + 403 if the user's role is not allowed |
| `csrf_token()` | Creates (once per session) and returns a random 32-hex-char token |
| `require_post()` | Rejects (400) anything that is not POST with the correct CSRF token; uses `hash_equals` |
| `flash($type, $msg)` | Stores a one-time message in the session for the next page |
| `log_status(...)` | Inserts a row into `post_status_logs` |
| `verified_ngo_id($pdo, $userId)` | ID of a **verified** NGO the user belongs to, or `null` |
| `can_claim($pdo, $userId, $role)` | True if in a verified NGO, **or** a volunteer with `is_verified = 1` |
| `login_user($user)` | `session_regenerate_id(true)` then stores id, name, role in the session |
| `app_secret()` | The secret key for pickup codes (`APP_SECRET` from `db.php`) |
| `pickup_code(...)` | 6-digit code = HMAC-SHA256 of (claim id, post id, claimer, claim time) with the secret |
| `format_code()` | `"482731"` → `"482 731"` |
| `code_locked_for()` / `code_failed()` | Count wrong codes per post in the session; lock for 10 min after 5 |

In [includes/ui.php](../includes/ui.php): `icon()` (inline SVG icons), `initials()`, `time_left()`
(e.g. "2h 15m left" + colour), `category_style()` (photo per category), `display_status()`
(adds "expired"), `tracker()` (Posted → Claimed → Collected steps), `plural()`, `iso()`, `greeting()`.

---

## 6. Every flow, end to end

For each flow: **what the user does → which file → what is checked → what SQL runs → what the user sees.**

### 6.1 Sign up: [signup.php](../signup.php)
1. User picks a role card (donor / NGO / volunteer) and fills the form. `?role=ngo` in the URL pre-selects a role.
2. **Browser check** first ([assets/js/validate.js](../assets/js/validate.js)): instant errors, no page reload.
3. **Server checks** (the real protection): name required, valid email, phone format, role must be one of
   the three (nobody can sign up as admin), password ≥ 8 chars and matches confirmation, NGO needs name,
   registration number, address and city.
4. Duplicate checks: email already used? NGO registration number already used?
5. Inside a **transaction**: `INSERT INTO users` with `password_hash($password, PASSWORD_DEFAULT)`.
   For an NGO also: `INSERT INTO locations` (office) → `INSERT INTO ngos` → `INSERT INTO ngo_members` as `manager`.
6. Redirect to `login.php?registered=1`.

*Why a transaction?* If creating the NGO failed after the user was created, `rollBack()` undoes the user too.
No half-created accounts.

### 6.2 Log in / log out: [login.php](../login.php), [logout.php](../logout.php)
- Look up the user by email, then `password_verify($password, $hash)`.
- Same message for "no such email" and "wrong password" (doesn't reveal which emails exist).
- On success `login_user()` regenerates the session ID (prevents **session fixation**), then redirect to `home.php`.
- Logout is a **POST form with the CSRF token** (from the sidebar). It empties `$_SESSION`, deletes the cookie,
  and destroys the session. A plain link would let any website log you out.

### 6.3 The dashboard: [home.php](../home.php)
One file, different content per `$_SESSION['role']`:
- **Donor:** welcome banner with plates served; **Live now** cards (available + claimed posts) with the
  tracker; claimed cards show who is coming and the **code box**; **Past posts** list (collected,
  expired with *Repost*, withdrawn).
- **NGO / volunteer:** verification notice if not allowed to claim; **Your pickups** (active claims with
  donor address, phone and pickup code); **Food waiting to be rescued** with filters; **Food you rescued**.
- **Admin:** pending NGO requests (with registration no. and office address) and volunteer requests,
  each with **Verify** / **Reject**.

### 6.4 Post food (and edit): [post_food.php](../post_food.php)
1. Only donors (`require_role('donor')`). Form has CSRF token.
2. Validation: title, a real category id, quantity a whole number ≥ 1, unit, both times, end after start,
   end in the future, address/area/city.
3. Transaction: `INSERT INTO locations` → `INSERT INTO food_posts (status 'available')` → `log_status(NULL → available)`.
4. **Edit mode** `post_food.php?id=N`: only your own post and only while `available`. On save it **re-checks under a row
   lock** (`SELECT … FOR UPDATE`) that nobody claimed it meanwhile, writes the address as a **new** location row
   (old rows may be shared by other posts), then `UPDATE food_posts`. Extending the end time revives an expired post.

### 6.5 Browse and filter (NGO / volunteer)
`home.php` builds the query with **prepared-statement placeholders**:
`… WHERE p.status = 'available' AND p.pickup_until > NOW()` plus optional `AND l.city = ?`,
`AND l.area LIKE ?`, `AND p.category_id = ?`. Values go into the `$params` array, never into the SQL text.
Category is cast with `(int)`. Ordered by `pickup_until ASC` (ending soonest first).

### 6.6 Claim (the most important flow): [claim.php](../claim.php)
```php
$pdo->beginTransaction();
$stmt = $pdo->prepare('SELECT status, pickup_until FROM food_posts WHERE id = ? FOR UPDATE');
// still available and not past pickup time?
INSERT INTO claims (post_id, claimed_by, ngo_id)   -- ngo_id = verified_ngo_id(...)
UPDATE food_posts SET status = 'claimed'
log_status(available → claimed)
$pdo->commit();
```
**How double-claiming is prevented (expect this question):**
1. **`SELECT … FOR UPDATE`** locks the post's row. If two people click Claim at the same moment, the second
   request **waits** until the first commits, then sees `status = 'claimed'` and gets "no longer available".
2. **`UNIQUE (claims.post_id)`** is a second safety net in the database itself.
3. **Transaction:** either all three writes happen or none (`rollBack()` in `catch`).

This was tested with 5 users claiming the same post at the same instant, 10 times: always exactly one claim.

### 6.7 Cancel a claim: [cancel.php](../cancel.php)
Only the claimer, only while `claimed`. Locks the claim, **deletes the claim row**, sets the post back to
`available`, logs `claimed → available`. Someone else can now claim it.

### 6.8 Pickup code and collection: [collect.php](../collect.php)
- When a claim exists, `pickup_code()` calculates a 6-digit code from the claim. The **claimer** sees it on
  their dashboard and as a big code + **QR** on `post.php` (drawn by [assets/js/pickup.js](../assets/js/pickup.js)).
  The donor never sees the code.
- At handover the donor types the code (or scans the QR, which opens `post.php?id=N&code=…` and pre-fills it).
- `collect.php`: only donors, CSRF, checks the lockout, locks the post+claim row, compares codes with
  `hash_equals()`; wrong ⇒ `code_failed()` and "X tries left"; right ⇒ `collected_at = NOW()`,
  status `collected`, log `claimed → collected`.
- **Why HMAC?** Nothing extra is stored in the database; the same claim always gives the same code; a new
  claim (after a cancel) gets a new code; and without `APP_SECRET` (private, in `db.php`) nobody can compute it.
- **Brute force:** 5 wrong tries lock that post for 10 minutes.

### 6.9 Withdraw: [withdraw.php](../withdraw.php)
Donor's own post, only while `available`, row lock, status → `withdrawn`, logged. Hidden from collectors.

### 6.10 Expired posts
Not stored. `display_status()` shows "expired" for available posts past `pickup_until`; `claim.php` also
refuses them. A claimed post past its window stays claimed but shows "Pickup overdue".

### 6.11 Post page and history timeline: [post.php](../post.php)
- **Who can open it:** the donor, the current claimer, the admin, or any NGO/volunteer **only while it is
  available**. Everyone else is redirected.
- Phone numbers and the full address appear only for the donor, the claimer and the admin.
- The timeline reads `post_status_logs` joined with `users` and turns each `(old, new)` pair into a sentence
  ("Claimed by Hope Foundation"), plus a calculated "pickup window closed" step for expired posts.

### 6.12 Admin verify / reject: [verify.php](../verify.php)
`action=verify` sets `is_verified = 1`; `action=reject` sets `rejected_at = NOW()`. Both only change rows that are
still pending (`is_verified = 0 AND rejected_at IS NULL`) and check `rowCount()` to report "not found or already handled".

### 6.13 NGO team: [team.php](../team.php)
Only the NGO's **manager**. Add a member by email: must be an existing **volunteer**, not rejected;
`INSERT IGNORE INTO ngo_members` (the composite primary key prevents duplicates). Remove: only `member_role = 'volunteer'`,
so the NGO can never lose its manager. A member of a **verified** NGO passes `can_claim()` and their claims store `ngo_id`.

### 6.14 Profile: [profile.php](../profile.php)
Three separate forms (a hidden `form` field says which): personal details + address (fills `users.location_id`),
NGO office address (NGO managers), and change password (must type the current one; new ≥ 8 chars and different;
`session_regenerate_id` afterwards).

### 6.15 Impact page: [impact.php](../impact.php)
Pure `SELECT`s, no new tables: plates saved, pickups, success rate (collected ÷ (collected + expired)),
average minutes from first log to first claim, 14-day posted vs collected (from `post_status_logs`),
posts per category, outcomes, top donors and collectors. The data is printed as **escaped JSON** in a
`<script type="application/json">` and drawn by [assets/js/impact.js](../assets/js/impact.js) with Chart.js.

### 6.16 Public homepage: [index.php](../index.php)
Logged-in users are redirected to the dashboard. Visitors see the info bar and navbar, live numbers, how it works,
features, roles with `signup.php?role=…` links, up to 6 open posts (**area and city only, never the address**),
FAQ and footer.

---

## 7. Security: threat → protection → where

| Threat | Protection | Where |
|---|---|---|
| Stolen passwords | `password_hash()` (bcrypt) and `password_verify()`; never stored in plain text | signup, login, profile |
| SQL injection | Prepared statements with `?` placeholders everywhere; ids cast with `(int)` | every query |
| XSS (injected scripts) | All output through `e()` = `htmlspecialchars`; chart data as escaped JSON | every page |
| CSRF (another site submitting forms as you) | POST-only actions + secret per-session token checked with `hash_equals` | `require_post()` |
| Session fixation | `session_regenerate_id(true)` on login and password change | `login_user()`, profile |
| Accessing others' pages | `require_role()` + ownership in every query (`AND donor_id = ?`, `AND claimed_by = ?`) | all actions |
| Double claiming | Row lock `FOR UPDATE` + transaction + `UNIQUE(claims.post_id)` | claim.php |
| Wrong person collecting | HMAC pickup code, compared with `hash_equals`, 5-try lockout | collect.php |
| Leaking contact details | Address/phones only to the donor, claimer and admin; homepage shows area only | home, post, index |
| Fake NGOs / volunteers | Admin verification and rejection before claiming | verify.php |
| Secrets on GitHub | `config/db.php` is git-ignored; `db.example.php` has placeholders | .gitignore |

**Why validate on both browser and server?** Browser checks (JS) are for convenience; anyone can bypass
them, so the server always re-validates.

---

## 8. The front end

- **CSS** ([assets/css/style.css](../assets/css/style.css)): design tokens as CSS variables in `:root`
  (colours, radius, shadows, fonts), so the whole look changes in one place. Responsive with media queries
  (sidebar becomes a top bar on phones). Fonts: Fraunces (headings), DM Sans (text).
- **JS** (all vanilla):
  - `validate.js`: form checks, show/hide password, role cards on signup.
  - `app.js`: live countdowns (update every 30 s from `data-until`), count-up numbers, homepage menu,
    scroll highlighting and fade-in.
  - `impact.js`: draws the charts. `pickup.js`: draws the QR code.
- **Accessibility touches:** labels on inputs, `aria-label`s, all animation disabled for users who set
  "reduce motion".
- **Offline-friendly:** Chart.js and the QR library are stored in `assets/js/vendor/`, not loaded from the internet.

---

## 9. Likely viva questions (with short answers)

**General**
1. *What does your project do?* Connects donors of surplus food with verified NGOs/volunteers who collect it, with a full status history.
2. *Why PHP without a framework?* To show the fundamentals directly: sessions, PDO, prepared statements, transactions, CSRF.
3. *What is the architecture?* Page-per-feature PHP with shared includes; view pages read data, action pages change it and redirect (Post/Redirect/Get).

**Database**
4. *How many tables and why?* Eight (list them). Each has one responsibility; addresses are shared through `locations`.
5. *Explain a many-to-many relationship in your project.* `users` ↔ `ngos` through `ngo_members`.
6. *Primary key of `ngo_members`?* Composite (`ngo_id`, `user_id`), so the same person can't join an NGO twice.
7. *What does ON DELETE CASCADE do here?* Deleting a post deletes its claim and logs; SET NULL keeps a post if its category is deleted.
8. *Why isn't "expired" a column?* It depends on the current time; it's calculated when displayed.
9. *What is normalisation and where did you use it?* Avoiding repeated data, e.g. one `locations` table instead of address columns in three tables.

**Logic**
10. *How do you stop two NGOs claiming the same food?* `FOR UPDATE` row lock in a transaction + `UNIQUE(post_id)`.
11. *What is a transaction?* A group of queries that all succeed (`commit`) or all fail (`rollBack`).
12. *What happens when a claim is cancelled?* The claim row is deleted, the post goes back to available, and it's logged.
13. *How does the pickup code work?* HMAC-SHA256 of the claim data with a secret key, shortened to 6 digits; nothing stored; 5-try lockout.
14. *How is the status history built?* Every status change calls `log_status()`; `post.php` reads `post_status_logs`.
15. *Who can claim?* Verified volunteers, or members of a verified NGO (`can_claim()`).

**Security**
16. *How are passwords stored?* As bcrypt hashes via `password_hash`; checked with `password_verify`.
17. *What is SQL injection and how do you prevent it?* Malicious SQL in input; prevented by prepared statements.
18. *What is XSS?* Injected scripts in output; prevented by escaping with `htmlspecialchars` (`e()`).
19. *What is CSRF?* Another site making your browser submit a form; prevented by a secret token checked on every POST.
20. *Why `hash_equals` instead of `==`?* Constant-time comparison, so attackers can't learn a token from timing.
21. *Why regenerate the session ID on login?* To stop session fixation.
22. *Why is `db.php` not on GitHub?* It holds the database password and the pickup-code secret.

**Front end / other**
23. *Why validate in JS and PHP?* JS for user-friendliness; PHP because JS can be bypassed.
24. *How do the countdowns update?* `app.js` reads `data-until` timestamps and refreshes the text every 30 seconds.
25. *How are the charts made?* PHP calculates numbers → JSON in the page → Chart.js draws them.
26. *What did you test?* An end-to-end script: signup, login, posting, filters, XSS/SQL injection, every page × role, CSRF, the claim race, pickup codes (45 checks).
27. *What would you add next?* See Section 11.

---

## 10. Demo script (about 6 minutes)

Before the viva: reload fresh demo data (`schema.sql` + `seed.sql`) so posts are live, and start the server.
All demo passwords are `password123`.

1. **Homepage** (logged out): info bar, navbar, live numbers, how it works. *"This is what a visitor sees."*
2. **Sign up** page: show the role cards, and that NGOs must give a registration number and address.
3. **Log in as donor** `donor@saveandserve.test`: banner, Live now cards with tracker, Past posts. **Post food** quickly.
4. **Log in as volunteer** `volunteer@saveandserve.test` (a second browser or private window is handy):
   find the new post with a filter, **Claim** it. Show **Your pickups**: donor address, phone, **pickup code**.
   Open the post: big code + **QR** and the timeline.
5. **Back to donor:** "Ali Volunteer is coming". Type a **wrong code** (tries left), then the **right code** → collected.
   Open the post: the full timeline.
6. **Admin** `admin@saveandserve.test`: verify/reject requests (sign up a test NGO beforehand to have one pending).
7. **NGO** `ngo@saveandserve.test`: **My team** page (many-to-many). **Impact** page charts.
8. Finish with the **security table** (Section 7) and the **claim race** explanation.

---

## 11. Honest limitations and future work

Saying these confidently shows understanding:
- No email/SMS notifications (would need a mail service); users see updates on their dashboard.
- Donors can't upload their own food photos; each category has a fixed photo.
- No map of pickup locations (would need latitude/longitude in `locations`).
- No login rate limiting against password guessing.
- No pagination; fine for demo data, needed for thousands of posts.
- If a volunteer is in two verified NGOs, `verified_ngo_id()` picks one (`LIMIT 1`) for the claim.
- Admin cannot undo a rejection or list all users/posts.
- Runs locally; deploying online would need hosting and HTTPS.

---

## 12. Self-test exercises

Do these with the code open; if you can do them, you understand the project.

1. Find where the CSRF token is created and where it is checked. What status code is returned if it's wrong?
2. In `claim.php`, what would go wrong if you removed `FOR UPDATE`? What would still save you?
3. Trace a post from creation to collection and list every row written to `post_status_logs`.
4. Why does editing a post insert a **new** `locations` row instead of updating the old one?
5. Which query shows a volunteer's "Your teams" strip? Which table does it read?
6. Change the pickup-code lockout to 3 tries. Which constant do you edit?
7. Log in as a volunteer and try to open `collect.php` in the address bar. What happens and why?
8. What makes the homepage hide full addresses? Find the SQL.
9. Explain `display_status()` and where it is used.
10. Draw the ERD from memory and mark every UNIQUE constraint.
