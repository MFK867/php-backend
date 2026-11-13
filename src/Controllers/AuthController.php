<?php
// =======================================================
// Authentication Controller
// =======================================================

class AuthController {
    public static function login(PDO $pdo): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error("Please provide both email address and password.", 422);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::unauthorized("Invalid email or password credentials.");
        }

        if (!(bool)$user['is_active']) {
            Response::forbidden("Your account has been deactivated. Please contact the administrator.");
        }

        $token = Auth::createToken([
            'sub'   => (string)$user['id'],
            'role'  => $user['role'],
            'email' => $user['email']
        ]);

        Response::json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'user'         => Auth::formatUserResponse($user, $pdo)
        ]);
    }

    public static function registerStudent(PDO $pdo): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name = trim($data['name'] ?? '');
        $rawEmail = $data['email'] ?? '';
        $rawStudentId = $data['student_id'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($name) || empty($rawEmail) || empty($rawStudentId) || empty($password)) {
            Response::error("All fields (Full Name, Roll Number, Email, Password) are required.", 422);
        }

        // 1. Email format (@tuf.edu.pk)
        $email = Auth::validateStudentEmail($rawEmail);

        // 2. Roll number format
        $studentId = Auth::validateRollNumber($rawStudentId);

        // 3. Password length
        if (strlen($password) < 8) {
            Response::error("Password must be at least 8 characters in length.", 422);
        }

        // 4. Duplicate checks
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkUser->execute([$email]);
        if ($checkUser->fetchColumn()) {
            Response::error("An account is already registered with this university email address.", 409);
        }

        $checkSid = $pdo->prepare("SELECT id FROM student_profiles WHERE student_id = ?");
        $checkSid->execute([$studentId]);
        if ($checkSid->fetchColumn()) {
            Response::error("A student profile with this Student ID is already registered in the system.", 409);
        }

        // 5. Default Department & Degree Program resolution
        $deptId = isset($data['department_id']) && !empty($data['department_id']) ? (int)$data['department_id'] : null;
        if (!$deptId) {
            $firstDept = $pdo->query("SELECT id FROM departments LIMIT 1")->fetchColumn();
            $deptId = $firstDept ? (int)$firstDept : 1;
        }

        $degreeProgram = trim($data['degree_program'] ?? '');
        if (empty($degreeProgram)) {
            if (str_contains($studentId, 'AI')) {
                $degreeProgram = "BS Artificial Intelligence";
            } elseif (str_contains($studentId, 'SE')) {
                $degreeProgram = "BS Software Engineering";
            } elseif (str_contains($studentId, 'CS')) {
                $degreeProgram = "BS Computer Science";
            } elseif (str_contains($studentId, 'IT')) {
                $degreeProgram = "BS Information Technology";
            } elseif (str_contains($studentId, 'EE')) {
                $degreeProgram = "BS Electrical Engineering";
            } else {
                $degreeProgram = "BS Computer Science";
            }
        }

        $semester = isset($data['semester']) ? (int)$data['semester'] : 8;
        $section = !empty($data['section']) ? strtoupper(trim($data['section'])) : 'A';

        // 6. Insert User & Profile inside transaction
        $pdo->beginTransaction();
        try {
            $insUser = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at)
                VALUES (?, ?, ?, 'student', 1, CURRENT_TIMESTAMP)
            ");
            $insUser->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_BCRYPT)
            ]);
            $userId = (int)$pdo->lastInsertId();

            $insProfile = $pdo->prepare("
                INSERT INTO student_profiles (user_id, student_id, department_id, degree_program, semester, section)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insProfile->execute([
                $userId,
                $studentId,
                $deptId,
                $degreeProgram,
                $semester,
                $section
            ]);

            // Welcome Notification
            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type)
                VALUES (?, 'Welcome to the Project Portal', 'Your student account has been registered successfully. You may now submit your project.', 'welcome')
            ");
            $notif->execute([$userId]);

            $pdo->commit();

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();

            $token = Auth::createToken([
                'sub'   => (string)$userId,
                'role'  => 'student',
                'email' => $email
            ]);

            Response::json([
                'access_token' => $token,
                'token_type'   => 'bearer',
                'user'         => Auth::formatUserResponse($newUser, $pdo)
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error("Registration error: " . $e->getMessage(), 500);
        }
    }

    public static function me(PDO $pdo): void {
        $user = Auth::requireAuth($pdo);
        Response::json($user);
    }

    public static function logout(): void {
        Response::json(['message' => 'Logged out successfully.']);
    }
}
