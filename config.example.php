<?php
// Copy this file to config.php and fill in your database credentials.
// On HostPinnacle cPanel these come from "MySQL Databases".

const DB_HOST = 'localhost';
const DB_NAME = 'your_db_name';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';

date_default_timezone_set('Africa/Nairobi');
session_start();

// Per-visitor key used for "My plans" and RSVPs — the server-side
// equivalent of the sample's localStorage. Real accounts come later.
if (empty($_SESSION['key'])) {
    $_SESSION['key'] = bin2hex(random_bytes(16));
}
define('SESSION_KEY', $_SESSION['key']);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}
