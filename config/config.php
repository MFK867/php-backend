<?php
// =======================================================
// Application Configuration File
// =======================================================

// Simple .env loader if .env file exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

return [
    'app_name' => getenv('APP_NAME') ?: 'Student Project Submission & Evaluation Portal',
    'app_env'  => getenv('APP_ENV') ?: 'production',

    // Database Configuration (Hostinger MySQL)
    'db' => [
        'driver'   => getenv('DB_CONNECTION') ?: 'mysql', // 'mysql' or 'sqlite'
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'tuf_student_portal',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ],

    // JWT Authentication Secret
    'jwt_secret' => getenv('JWT_SECRET') ?: 'hostinger-production-secure-jwt-key-2026-portal',
    'jwt_expiry_minutes' => 60 * 24, // 24 hours

    // Allowed Student Email Domain
    'student_email_domain' => 'tuf.edu.pk',

    // Upload directories (Absolute Paths)
    'storage' => [
        'base'   => dirname(__DIR__) . '/storage/uploads',
        'srs'    => dirname(__DIR__) . '/storage/uploads/srs',
        'code'   => dirname(__DIR__) . '/storage/uploads/code',
        'images' => dirname(__DIR__) . '/storage/uploads/images',
    ],

    // Maximum file upload sizes in bytes
    'limits' => [
        'srs_max_bytes'  => 100 * 1024 * 1024,  // 100MB
        'code_max_bytes' => 500 * 1024 * 1024,  // 500MB
        'image_max_bytes'=> 15 * 1024 * 1024,   // 15MB
    ],
];
