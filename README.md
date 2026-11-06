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
| hero-sharing.jpg | https://images.unsplash.com/photo-1576867757603-05b134ebc379 |
| cat-cooked.jpg | https://images.unsplash.com/photo-1631515243349-e0cb75fb8d3a |
| cat-bakery.jpg | https://images.unsplash.com/photo-1549931319-a545dcf3bc73 |
| cat-fresh.jpg | https://images.unsplash.com/photo-1619566636858-adf3ef46400b |
| cat-packaged.jpg | https://images.unsplash.com/photo-1584473457406-6240486418e9 |
| cat-event.jpg | https://images.unsplash.com/photo-1555244162-803834f70033 |
| cat-default.jpg | https://images.unsplash.com/photo-1565557623262-b51c2513a641 |
