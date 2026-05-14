<?php
// server/connection.php - Secure DB connection with prepared statements only.

// Resolve config relative to project root (this file lives in /server).
require_once __DIR__ . '/../config.php';

// Throw exceptions on mysqli errors so failures don't silently corrupt state.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
    error_log('DB connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable. Please try again later.');
}
