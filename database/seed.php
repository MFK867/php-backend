<?php
// =======================================================
// Database Seeder
// Populates academic metadata & verified login accounts
// Clean slate: ZERO default projects and ZERO grades.
// =======================================================

function seedDatabase(PDO $pdo): void {
    // 1. Academic Departments
    $departments = [
        ['name' => 'Department of Computer Science', 'code' => 'DCS'],
        ['name' => 'Department of Software Engineering', 'code' => 'DSE'],
        ['name' => 'Department of Information Technology', 'code' => 'DIT'],
        ['name' => 'Department of Electrical Engineering', 'code' => 'DEE'],
        ['name' => 'Department of Computational Sciences', 'code' => 'DCSci'],
    ];

    $deptStmt = $pdo->prepare("SELECT id FROM departments WHERE code = ?");
    $insertDept = $pdo->prepare("INSERT INTO departments (name, code) VALUES (?, ?)");

    $deptMap = [];
    foreach ($departments as $d) {
        $deptStmt->execute([$d['code']]);
        $existing = $deptStmt->fetchColumn();
        if (!$existing) {
            $insertDept->execute([$d['name'], $d['code']]);
            $deptMap[$d['code']] = (int)$pdo->lastInsertId();
        } else {
            $deptMap[$d['code']] = (int)$existing;
        }
    }

    // 2. Project Categories
    $categories = [
        ['name' => 'Artificial Intelligence', 'description' => 'Intelligent systems, deep learning, NLP, computer vision, and autonomous agents.'],
        ['name' => 'Machine Learning', 'description' => 'Predictive modeling, supervised/unsupervised learning algorithms, and neural networks.'],
        ['name' => 'Web Development', 'description' => 'Modern full-stack web applications, REST/GraphQL APIs, and cloud services.'],
        ['name' => 'Mobile Application', 'description' => 'Native and cross-platform Android, iOS, and Flutter mobile applications.'],
        ['name' => 'Desktop Application', 'description' => 'Cross-platform and Windows desktop solutions and productivity systems.'],
        ['name' => 'Cybersecurity', 'description' => 'Network defense, vulnerability auditing, cryptography, and penetration testing.'],
        ['name' => 'Blockchain', 'description' => 'Decentralized applications, smart contracts, Web3, and distributed ledgers.'],
        ['name' => 'Internet of Things', 'description' => 'Embedded smart hardware, sensor networks, and edge automation.'],
        ['name' => 'Data Science', 'description' => 'Big data pipelines, data visualization, business intelligence, and statistics.'],
        ['name' => 'Database Systems', 'description' => 'High-performance relational and NoSQL database engines and optimization.'],
        ['name' => 'Software Engineering', 'description' => 'Architectural frameworks, CI/CD pipelines, DevOps, and testing automation.'],
        ['name' => 'Other', 'description' => 'Interdisciplinary applied engineering and research projects.'],
    ];

    $catStmt = $pdo->prepare("SELECT id FROM project_categories WHERE name = ?");
    $insertCat = $pdo->prepare("INSERT INTO project_categories (name, description) VALUES (?, ?)");

    foreach ($categories as $c) {
        $catStmt->execute([$c['name']]);
        if (!$catStmt->fetchColumn()) {
            $insertCat->execute([$c['name'], $c['description']]);
        }
    }

    // 3. Technologies
    $technologies = [
        ['name' => 'Python', 'category' => 'Programming Languages'],
        ['name' => 'JavaScript', 'category' => 'Programming Languages'],
        ['name' => 'TypeScript', 'category' => 'Programming Languages'],
        ['name' => 'Java', 'category' => 'Programming Languages'],
        ['name' => 'PHP', 'category' => 'Programming Languages'],
        ['name' => 'C++', 'category' => 'Programming Languages'],
        ['name' => 'C#', 'category' => 'Programming Languages'],
        ['name' => 'Dart', 'category' => 'Programming Languages'],
        ['name' => 'Kotlin', 'category' => 'Programming Languages'],
        ['name' => 'React', 'category' => 'Frontend'],
        ['name' => 'Vue', 'category' => 'Frontend'],
        ['name' => 'Angular', 'category' => 'Frontend'],
        ['name' => 'Tailwind CSS', 'category' => 'Frontend'],
        ['name' => 'FastAPI', 'category' => 'Backend'],
        ['name' => 'Laravel', 'category' => 'Backend'],
        ['name' => 'Node.js', 'category' => 'Backend'],
        ['name' => 'Django', 'category' => 'Backend'],
        ['name' => 'Flask', 'category' => 'Backend'],
        ['name' => 'MySQL', 'category' => 'Database'],
        ['name' => 'PostgreSQL', 'category' => 'Database'],
        ['name' => 'MongoDB', 'category' => 'Database'],
        ['name' => 'SQLite', 'category' => 'Database'],
        ['name' => 'TensorFlow', 'category' => 'AI/ML'],
        ['name' => 'PyTorch', 'category' => 'AI/ML'],
        ['name' => 'OpenCV', 'category' => 'AI/ML'],
        ['name' => 'Docker', 'category' => 'Tools'],
        ['name' => 'Git', 'category' => 'Tools'],
    ];

    $techStmt = $pdo->prepare("SELECT id FROM technologies WHERE name = ?");
    $insertTech = $pdo->prepare("INSERT INTO technologies (name, category) VALUES (?, ?)");

    foreach ($technologies as $t) {
        $techStmt->execute([$t['name']]);
        if (!$techStmt->fetchColumn()) {
            $insertTech->execute([$t['name'], $t['category']]);
        }
    }

    // 4. Evaluation Criteria (Default 100-Mark Rubric)
    $rubric = [
        ['name' => 'Problem Identification', 'maximum_marks' => 10.0, 'description' => 'Clarity and academic validity of problem definition.', 'display_order' => 1],
        ['name' => 'SRS Documentation', 'maximum_marks' => 15.0, 'description' => 'Completeness, formatting, IEEE standards, and requirements.', 'display_order' => 2],
        ['name' => 'System Design & Architecture', 'maximum_marks' => 15.0, 'description' => 'UML diagrams, database schema, design patterns, and modularity.', 'display_order' => 3],
        ['name' => 'Implementation', 'maximum_marks' => 25.0, 'description' => 'Completeness of core software logic, backend API, and features.', 'display_order' => 4],
        ['name' => 'Code Quality', 'maximum_marks' => 10.0, 'description' => 'Clean code standards, naming conventions, comments, and structure.', 'display_order' => 5],
        ['name' => 'Functionality', 'maximum_marks' => 15.0, 'description' => 'Robust operational reliability, lack of critical defects.', 'display_order' => 6],
        ['name' => 'Innovation & Creativity', 'maximum_marks' => 5.0, 'description' => 'Uniqueness, real-world utility, and creative approach.', 'display_order' => 7],
        ['name' => 'Presentation / Demonstration', 'maximum_marks' => 5.0, 'description' => 'Professional demonstration, technical defense, and Q&A.', 'display_order' => 8],
    ];

    $critStmt = $pdo->prepare("SELECT id FROM evaluation_criteria WHERE name = ?");
    $insertCrit = $pdo->prepare("INSERT INTO evaluation_criteria (name, maximum_marks, description, display_order) VALUES (?, ?, ?, ?)");

    foreach ($rubric as $r) {
        $critStmt->execute([$r['name']]);
        if (!$critStmt->fetchColumn()) {
            $insertCrit->execute([$r['name'], $r['maximum_marks'], $r['description'], $r['display_order']]);
        }
    }

    // 5. Dedicated Administrator User
    $adminEmail = 'faisalkamran867@gmail.com';
    $adminPass  = 'FkAdmin@892#zQ!';
    $userCheck  = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userCheck->execute([$adminEmail]);
    $adminId = $userCheck->fetchColumn();

    if (!$adminId) {
        $insAdmin = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at) VALUES (?, ?, ?, 'admin', 1, CURRENT_TIMESTAMP)");
        $insAdmin->execute([
            'Faisal Kamran',
            $adminEmail,
            password_hash($adminPass, PASSWORD_BCRYPT)
        ]);
        $adminId = (int)$pdo->lastInsertId();

        $insTp = $pdo->prepare("INSERT INTO teacher_profiles (user_id, department_id, designation) VALUES (?, ?, ?)");
        $insTp->execute([$adminId, $deptMap['DCS'] ?? 1, 'Portal Administrator & Senior Evaluator']);
    } else {
        $updAdmin = $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', is_active = 1 WHERE id = ?");
        $updAdmin->execute([password_hash($adminPass, PASSWORD_BCRYPT), $adminId]);
    }

    // 6. Demo Faculty User
    $facEmail = 'dr.farhan@tuf.edu.pk';
    $userCheck->execute([$facEmail]);
    $facId = $userCheck->fetchColumn();
    if (!$facId) {
        $insFac = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at) VALUES (?, ?, ?, 'teacher', 1, CURRENT_TIMESTAMP)");
        $insFac->execute([
            'Dr. Farhan Bashir',
            $facEmail,
            password_hash('TeacherPassword123', PASSWORD_BCRYPT)
        ]);
        $facId = (int)$pdo->lastInsertId();

        $insTp = $pdo->prepare("INSERT INTO teacher_profiles (user_id, department_id, designation) VALUES (?, ?, ?)");
        $insTp->execute([$facId, $deptMap['DCS'] ?? 1, 'Associate Professor & Evaluator']);
    }

    // 7. Demo Student User
    $studEmail = 'ali.ahmad@tuf.edu.pk';
    $userCheck->execute([$studEmail]);
    $studId = $userCheck->fetchColumn();
    if (!$studId) {
        $insStud = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, email_verified_at) VALUES (?, ?, ?, 'student', 1, CURRENT_TIMESTAMP)");
        $insStud->execute([
            'Ali Ahmad',
            $studEmail,
            password_hash('StudentPassword123', PASSWORD_BCRYPT)
        ]);
        $studId = (int)$pdo->lastInsertId();

        $insSp = $pdo->prepare("INSERT INTO student_profiles (user_id, student_id, department_id, degree_program, semester, section) VALUES (?, ?, ?, ?, 8, 'A')");
        $insSp->execute([$studId, '2022-BS-CS-041', $deptMap['DCS'] ?? 1, 'BS Computer Science']);
    }

    // 8. Ensure physical upload directories exist
    $config = require dirname(__DIR__) . '/config/config.php';
    foreach ($config['storage'] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

// Allow execution via browser or CLI
require_once dirname(__DIR__) . '/config/database.php';

try {
    $pdo = Database::getConnection();
    seedDatabase($pdo);

    if (php_sapi_name() === 'cli') {
        echo "Database seeded successfully with metadata and verified login accounts. ZERO projects.\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Database Initialized</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;padding:40px;color:#0f172a}div{background:#fff;border:1px solid #e2e8f0;padding:28px;border-radius:12px;max-width:560px;margin:0 auto;box-shadow:0 1px 3px rgba(0,0,0,0.05)}h2{color:#15803d;margin-top:0;font-size:20px}pre{background:#f1f5f9;padding:14px;border-radius:8px;font-size:12px;line-height:1.6;font-family:monospace}p{font-size:14px;line-height:1.5;color:#334155}</style></head><body><div><h2>✓ Database Initialized Successfully</h2><p>Academic departments, categories, technologies, rubric criteria, and login accounts were verified.</p><pre>Admin Email:    faisalkamran867@gmail.com
Admin Password: FkAdmin@892#zQ!
Role:           admin

Student Email:  ali.ahmad@tuf.edu.pk
Password:       StudentPassword123

Faculty Email:  dr.farhan@tuf.edu.pk
Password:       TeacherPassword123

Active Projects: 0 (Clean Slate)</pre><p style="color:#64748b;font-size:12px;margin-bottom:0">Your backend is ready to receive requests from your Vercel frontend.</p></div></body></html>';
    }
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "Database Error: " . $e->getMessage() . "\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><title>Database Connection Error</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#fff1f2;padding:40px;color:#881337}div{background:#fff;border:1px solid #fecdd3;padding:28px;border-radius:12px;max-width:560px;margin:0 auto;box-shadow:0 1px 3px rgba(0,0,0,0.05)}h2{color:#e11d48;margin-top:0;font-size:20px}pre{background:#fff1f2;padding:14px;border-radius:8px;font-size:12px;word-break:break-all;font-family:monospace}p{font-size:14px;line-height:1.5}</style></head><body><div><h2>✗ Database Connection Error</h2><p>Could not connect to MySQL. Please check your <code>.env</code> file credentials in Hostinger File Manager.</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre></div></body></html>';
    }
}
