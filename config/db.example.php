<?php
// Copy this file to config/db.php and fill in your MySQL credentials.
const DB_HOST = 'localhost';
const DB_NAME = 'food_share';
const DB_USER = 'your_mysql_user';
const DB_PASS = 'your_mysql_password';

// Secret key for pickup codes. Keep it private and use a long random string.
const APP_SECRET = 'change-me-to-a-long-random-string';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}
