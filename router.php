<?php
// =======================================================
// Router script for PHP Built-In Development Web Server
// Used with: php -S 127.0.0.1:8000 router.php
// =======================================================

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Serve static file directly
}

require __DIR__ . '/index.php';
