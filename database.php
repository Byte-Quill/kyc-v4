<?php
/**
 * database.php — Database connection layer.
 *
 * Provides db(), a shared PDO connection to the MySQL/MariaDB database.
 * The connection is created once per request (static singleton) with:
 *   - exceptions on errors (ERRMODE_EXCEPTION)
 *   - associative-array fetches (FETCH_ASSOC)
 *   - real prepared statements (EMULATE_PREPARES off) — SQL-injection safe
 *
 * Connection settings (host, name, user, password) come from config.php,
 * which reads them from the .env file.
 */
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
