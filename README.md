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
