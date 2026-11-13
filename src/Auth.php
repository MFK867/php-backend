<?php
// =======================================================
// Authentication & JWT Security Services
// =======================================================

class Auth {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function createToken(array $payload, int $expiryMinutes = 1440): string {
        $config = require dirname(__DIR__) . '/config/config.php';
        $secret = $config['jwt_secret'];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + ($expiryMinutes * 60);

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "$base64Header.$base64Payload.$base64Signature";
    }

    public static function decodeToken(string $token): ?array {
        $config = require dirname(__DIR__) . '/config/config.php';
        $secret = $config['jwt_secret'];

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($header64, $payload64, $sig64) = $parts;

        $sig = self::base64UrlDecode($sig64);
        $expectedSig = hash_hmac('sha256', "$header64.$payload64", $secret, true);

        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payload64), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function getBearerToken(): ?string {
        $headers = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $reqHeaders = apache_request_headers();
            if (isset($reqHeaders['Authorization'])) {
                $headers = trim($reqHeaders['Authorization']);
            } elseif (isset($reqHeaders['authorization'])) {
                $headers = trim($reqHeaders['authorization']);
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function getCurrentUser(PDO $pdo): ?array {
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }

        $payload = self::decodeToken($token);
        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        $userId = (int)$payload['sub'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        return self::formatUserResponse($user, $pdo);
    }

    public static function requireAuth(PDO $pdo, ?array $allowedRoles = null): array {
        $user = self::getCurrentUser($pdo);
        if (!$user) {
            Response::unauthorized("Session token is invalid or expired. Please sign in again.");
        }

        if ($allowedRoles !== null && !in_array($user['role'], $allowedRoles, true)) {
            Response::forbidden("Access restricted: You do not have permissions for this workspace.");
        }

        return $user;
    }

    public static function formatUserResponse(array $user, PDO $pdo): array {
        $studentProfile = null;
        $teacherProfile = null;

        if ($user['role'] === 'student') {
            $stmt = $pdo->prepare("
                SELECT sp.*, d.name AS department_name, d.code AS department_code
                FROM student_profiles sp
                LEFT JOIN departments d ON d.id = sp.department_id
                WHERE sp.user_id = ?
            ");
            $stmt->execute([(int)$user['id']]);
            $sp = $stmt->fetch();
            if ($sp) {
                $studentProfile = [
                    'id' => (int)$sp['id'],
                    'student_id' => $sp['student_id'],
                    'department_id' => (int)$sp['department_id'],
                    'department_name' => $sp['department_name'],
                    'department_code' => $sp['department_code'],
                    'degree_program' => $sp['degree_program'],
                    'semester' => (int)$sp['semester'],
                    'section' => $sp['section'],
                    'profile_picture' => $sp['profile_picture'],
                ];
            }
        }

        if (in_array($user['role'], ['teacher', 'admin'], true)) {
            $stmt = $pdo->prepare("
                SELECT tp.*, d.name AS department_name, d.code AS department_code
                FROM teacher_profiles tp
                LEFT JOIN departments d ON d.id = tp.department_id
                WHERE tp.user_id = ?
            ");
            $stmt->execute([(int)$user['id']]);
            $tp = $stmt->fetch();
            if ($tp) {
                $teacherProfile = [
                    'id' => (int)$tp['id'],
                    'department_id' => (int)$tp['department_id'],
                    'department_name' => $tp['department_name'],
                    'department_code' => $tp['department_code'],
                    'designation' => $tp['designation'],
                ];
            }
        }

        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_active' => (bool)$user['is_active'],
            'email_verified_at' => $user['email_verified_at'],
            'created_at' => $user['created_at'],
            'student_profile' => $studentProfile,
            'teacher_profile' => $teacherProfile,
        ];
    }

    public static function validateStudentEmail(string $email): string {
        $email = trim(strtolower($email));
        if (!preg_match('/^[a-zA-Z0-9_.+-]+@tuf\.edu\.pk$/', $email)) {
            Response::error("Registration is restricted to official student email addresses ending in @tuf.edu.pk.", 422);
        }
        return $email;
    }

    public static function validateRollNumber(string $roll): string {
        $roll = strtoupper(trim($roll));
        if (!preg_match('/^\d{4}-[A-Z]{2,5}-[A-Z]{2,5}-\d{2,5}$/', $roll)) {
            Response::error("Student roll number must start with year (e.g. 2023-BS-AI-025). Format: YYYY-DEGREE-MAJOR-ROLL", 422);
        }
        return $roll;
    }
}
