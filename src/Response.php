<?php
// =======================================================
// JSON Response & CORS Handler
// =======================================================

class Response {
    public static function handleCors(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    public static function json(mixed $data, int $statusCode = 200): void {
        self::handleCors();
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }

    public static function error(string $message, int $statusCode = 400, mixed $extra = null): void {
        $response = ['detail' => $message];
        if ($extra !== null) {
            $response['errors'] = $extra;
        }
        self::json($response, $statusCode);
    }

    public static function unauthorized(string $message = 'Invalid authentication credentials.'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Access forbidden.'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Requested resource not found.'): void {
        self::error($message, 404);
    }
}
