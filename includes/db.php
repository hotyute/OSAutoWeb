<?php
/**
 * PDO Database Connection
 * --------------------------------------------------------
 * Uses PDO with ERRMODE_EXCEPTION so every query failure
 * is caught. ATTR_EMULATE_PREPARES = false forces the
 * MySQL driver to use real prepared statements, closing
 * off SQL-injection at the driver level.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'osrs_portal');
define('DB_USER', 'root');          // change in production
define('DB_PASS', '');              // change in production
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   /* real prepared stmts */
    ]);
} catch (PDOException $e) {
    /* Never expose raw PDO errors to the public */
    error_log('DB Connection Error: ' . $e->getMessage());
    http_response_code(500);
    exit('A database error occurred. Please try again later.');
}