# FoodShare – Surplus Food Sharing Platform

Donors (restaurants, wedding halls) post surplus food; NGOs and verified volunteers claim and collect it.
Every post moves through **available → claimed → collected**. See [PRD.md](PRD.md) and [docs/erd.mmd](docs/erd.mmd).

**Stack:** PHP 8 (PDO), MySQL/MariaDB, Apache, HTML/CSS/JS.

## Setup
1. Create the database and demo data:
   ```bash
   mysql -u root -p < sql/schema.sql
   mysql -u root -p < sql/seed.sql
   ```
2. Configure the connection:
   ```bash
   cp config/db.example.php config/db.php   # then edit user/password
   ```
3. Serve the folder with Apache (e.g. under `/var/www/html/`) or for quick testing:
   ```bash
   php -S localhost:8000
   ```
   and open http://localhost:8000.

## Demo accounts (password: `password123`)
| Role | Email |
|---|---|
| Admin | admin@foodshare.test |
| Donor | donor@foodshare.test |
| NGO | ngo@foodshare.test |
| Volunteer | volunteer@foodshare.test |

## Project structure
```
config/      DB connection (db.php is git-ignored; use db.example.php)
includes/    auth helpers, header, footer
sql/         schema.sql, seed.sql
docs/        ERD and project documentation
assets/      CSS and client-side validation JS
*.php        pages: signup, login, home, post_food, claim, cancel, collect, verify
```

## Image credits
Photos in `assets/img/` are from [Unsplash](https://unsplash.com) and used under the
[Unsplash License](https://unsplash.com/license) (free for commercial and non-commercial use).

| File | Source |
|---|---|
| hero-volunteers.jpg | https://images.unsplash.com/photo-1593113598332-cd288d649433 |
| cat-cooked.jpg | https://images.unsplash.com/photo-1504674900247-0877df9cc836 |
| cat-bakery.jpg | https://images.unsplash.com/photo-1608198093002-ad4e005484ec |
| cat-fresh.jpg | https://images.unsplash.com/photo-1488459716781-31db52582fe9 |
| cat-packaged.jpg | https://images.unsplash.com/photo-1604719312566-8912e9227c6a |
| cat-event.jpg | https://images.unsplash.com/photo-1519225421980-715cb0215aed |
| cat-default.jpg | https://images.unsplash.com/photo-1555939594-58d7cb561ad1 |
