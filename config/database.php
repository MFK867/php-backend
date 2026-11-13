<?php
// =======================================================
// PDO Database Connection Manager
// =======================================================

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/config.php';
        $db = $config['db'];

        // 1. Try MySQL connection
        if ($db['driver'] === 'mysql') {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $db['host'],
                    $db['port'],
                    $db['database'],
                    $db['charset']
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                self::$instance = new PDO($dsn, $db['username'], $db['password'], $options);
                return self::$instance;
            } catch (PDOException $e) {
                // If on local development and MySQL is offline, fall back to SQLite
                if ($config['app_env'] !== 'production' || strpos($db['host'], '127.0.0.1') !== false || strpos($db['host'], 'localhost') !== false) {
                    error_log("MySQL connection failed ({$e->getMessage()}). Falling back to SQLite for local development.");
                    return self::getSQLiteFallback();
                }
                throw $e;
            }
        }

        // 2. Fallback to SQLite
        return self::getSQLiteFallback();
    }

    private static function getSQLiteFallback(): PDO {
        $storageDir = dirname(__DIR__) . '/storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $sqliteFile = $storageDir . '/portal.sqlite';

        $isNew = !file_exists($sqliteFile);
        $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        self::$instance = $pdo;

        // Ensure SQLite schema tables exist
        self::initSQLiteSchema($pdo);

        return self::$instance;
    }

    public static function initSQLiteSchema(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'student',
                is_active INTEGER NOT NULL DEFAULT 1,
                email_verified_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                code TEXT NOT NULL UNIQUE
            );

            CREATE TABLE IF NOT EXISTS student_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                student_id TEXT NOT NULL UNIQUE,
                department_id INTEGER NOT NULL,
                degree_program TEXT NOT NULL,
                semester INTEGER NOT NULL DEFAULT 8,
                section TEXT NOT NULL DEFAULT 'A',
                profile_picture TEXT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments (id)
            );

            CREATE TABLE IF NOT EXISTS teacher_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                department_id INTEGER NOT NULL,
                designation TEXT NOT NULL DEFAULT 'Faculty Evaluator',
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments (id)
            );

            CREATE TABLE IF NOT EXISTS project_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT NULL
            );

            CREATE TABLE IF NOT EXISTS technologies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                category TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                submission_code TEXT NOT NULL UNIQUE,
                student_owner_id INTEGER NOT NULL,
                project_title TEXT NOT NULL,
                short_description TEXT NOT NULL,
                detailed_description TEXT NOT NULL,
                problem_statement TEXT NOT NULL,
                proposed_solution TEXT NOT NULL,
                objectives TEXT NOT NULL,
                category_id INTEGER NOT NULL,
                project_type TEXT NOT NULL DEFAULT 'Semester Project',
                academic_session TEXT NOT NULL DEFAULT 'Fall 2026',
                title_image_url TEXT NULL,
                is_locked INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_owner_id) REFERENCES users (id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES project_categories (id)
            );

            CREATE TABLE IF NOT EXISTS project_technologies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                technology_id INTEGER NOT NULL,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (technology_id) REFERENCES technologies (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS project_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                student_name TEXT NOT NULL,
                student_id_number TEXT NOT NULL,
                student_email TEXT NULL,
                member_role TEXT NOT NULL DEFAULT 'Member',
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS project_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                uploaded_by INTEGER NOT NULL,
                file_type TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                stored_filename TEXT NOT NULL,
                storage_path TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users (id)
            );

            CREATE TABLE IF NOT EXISTS project_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL UNIQUE,
                teacher_id INTEGER NULL,
                title_status TEXT NOT NULL DEFAULT 'Pending Approval',
                review_status TEXT NOT NULL DEFAULT 'Submitted',
                teacher_comments TEXT NULL,
                reviewed_at TEXT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS title_review_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                teacher_id INTEGER NOT NULL,
                previous_title TEXT NOT NULL,
                submitted_title TEXT NOT NULL,
                decision TEXT NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES users (id)
            );

            CREATE TABLE IF NOT EXISTS evaluation_criteria (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                maximum_marks REAL NOT NULL,
                description TEXT NULL,
                display_order INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS evaluations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL UNIQUE,
                teacher_id INTEGER NOT NULL,
                total_marks REAL NOT NULL DEFAULT 0.00,
                percentage REAL NOT NULL DEFAULT 0.00,
                overall_grade TEXT NULL,
                overall_feedback TEXT NULL,
                evaluation_status TEXT NOT NULL DEFAULT 'Not Evaluated',
                published_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES users (id)
            );

            CREATE TABLE IF NOT EXISTS evaluation_scores (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                evaluation_id INTEGER NOT NULL,
                criteria_id INTEGER NOT NULL,
                obtained_marks REAL NOT NULL DEFAULT 0.00,
                comments TEXT NULL,
                FOREIGN KEY (evaluation_id) REFERENCES evaluations (id) ON DELETE CASCADE,
                FOREIGN KEY (criteria_id) REFERENCES evaluation_criteria (id)
            );

            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                notification_type TEXT NOT NULL,
                related_project_id INTEGER NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                read_at TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NULL,
                ip_address TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
}
