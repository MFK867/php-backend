<?php
// =======================================================
// Student Project Controller
// =======================================================

class StudentController {
    public static function formatProject(array $p, PDO $pdo): array {
        $projectId = (int)$p['id'];

        // 1. Owner & Profile
        $stmtOwner = $pdo->prepare("
            SELECT u.name, u.email, sp.student_id, sp.degree_program, d.name AS department_name
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN departments d ON d.id = sp.department_id
            WHERE u.id = ?
        ");
        $stmtOwner->execute([(int)$p['student_owner_id']]);
        $owner = $stmtOwner->fetch() ?: [];

        // 2. Category
        $stmtCat = $pdo->prepare("SELECT id, name, description FROM project_categories WHERE id = ?");
        $stmtCat->execute([(int)$p['category_id']]);
        $category = $stmtCat->fetch() ?: null;

        // 3. Technologies
        $stmtTech = $pdo->prepare("
            SELECT t.id, t.name, t.category
            FROM project_technologies pt
            JOIN technologies t ON t.id = pt.technology_id
            WHERE pt.project_id = ?
            ORDER BY t.name ASC
        ");
        $stmtTech->execute([$projectId]);
        $technologies = $stmtTech->fetchAll();

        // 4. Team Members
        $stmtMembers = $pdo->prepare("
            SELECT id, student_name, student_id_number, student_email, member_role
            FROM project_members
            WHERE project_id = ?
            ORDER BY id ASC
        ");
        $stmtMembers->execute([$projectId]);
        $members = $stmtMembers->fetchAll();

        // 5. Files
        $stmtFiles = $pdo->prepare("
            SELECT id, file_type, original_filename, stored_filename, mime_type, file_size, uploaded_at
            FROM project_files
            WHERE project_id = ?
        ");
        $stmtFiles->execute([$projectId]);
        $files = $stmtFiles->fetchAll();

        $srsFile = null;
        $codeFile = null;
        foreach ($files as $f) {
            if ($f['file_type'] === 'srs') {
                $srsFile = $f;
            } elseif ($f['file_type'] === 'source_code') {
                $codeFile = $f;
            }
        }

        // 6. Review
        $stmtReview = $pdo->prepare("
            SELECT title_status, review_status, teacher_comments, reviewed_at
            FROM project_reviews
            WHERE project_id = ?
        ");
        $stmtReview->execute([$projectId]);
        $review = $stmtReview->fetch() ?: null;

        // 7. Evaluation & Rubric Scores
        $stmtEval = $pdo->prepare("
            SELECT id, total_marks, percentage, overall_grade, overall_feedback, evaluation_status, published_at
            FROM evaluations
            WHERE project_id = ?
        ");
        $stmtEval->execute([$projectId]);
        $evaluation = $stmtEval->fetch();

        if ($evaluation) {
            $stmtScores = $pdo->prepare("
                SELECT es.criteria_id, ec.name AS criteria_name, ec.maximum_marks, es.obtained_marks, es.comments
                FROM evaluation_scores es
                JOIN evaluation_criteria ec ON ec.id = es.criteria_id
                WHERE es.evaluation_id = ?
                ORDER BY ec.display_order ASC
            ");
            $stmtScores->execute([(int)$evaluation['id']]);
            $evaluation['scores'] = $stmtScores->fetchAll();
            $evaluation['total_marks'] = (float)$evaluation['total_marks'];
            $evaluation['percentage'] = (float)$evaluation['percentage'];
        }

        return [
            'id'                   => $projectId,
            'submission_code'      => $p['submission_code'],
            'student_owner_id'     => (int)$p['student_owner_id'],
            'project_title'        => $p['project_title'],
            'short_description'    => $p['short_description'],
            'detailed_description' => $p['detailed_description'],
            'problem_statement'    => $p['problem_statement'],
            'proposed_solution'    => $p['proposed_solution'],
            'objectives'           => $p['objectives'],
            'category_id'          => (int)$p['category_id'],
            'project_type'         => $p['project_type'],
            'academic_session'     => $p['academic_session'],
            'title_image_url'      => $p['title_image_url'],
            'is_locked'            => (bool)$p['is_locked'],
            'created_at'           => $p['created_at'],
            'updated_at'           => $p['updated_at'],
            'student_owner'        => [
                'name'  => $owner['name'] ?? 'Unknown',
                'email' => $owner['email'] ?? '',
            ],
            'student_profile'      => [
                'student_id'      => $owner['student_id'] ?? '',
                'degree_program'  => $owner['degree_program'] ?? '',
                'department_name' => $owner['department_name'] ?? 'Computing',
            ],
            'category'             => $category,
            'technologies'         => $technologies,
            'team_members'         => $members,
            'files'                => $files,
            'srs_document_file'    => $srsFile,
            'code_archive_file'    => $codeFile,
            'review'               => $review,
            'evaluation'           => $evaluation,
        ];
    }

    public static function getMyProject(PDO $pdo): void {
        $user = Auth::requireAuth($pdo, ['student']);

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE student_owner_id = ? LIMIT 1");
        $stmt->execute([(int)$user['id']]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::json(null);
        }

        Response::json(self::formatProject($project, $pdo));
    }

    public static function getProjects(PDO $pdo): void {
        $user = Auth::requireAuth($pdo, ['student']);

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE student_owner_id = ? ORDER BY id DESC");
        $stmt->execute([(int)$user['id']]);
        $projects = $stmt->fetchAll();

        $result = [];
        foreach ($projects as $p) {
            $result[] = self::formatProject($p, $pdo);
        }

        Response::json($result);
    }

    public static function getProjectDetail(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo);

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found.");
        }

        // Students can only view their own private project
        if ($user['role'] === 'student' && (int)$project['student_owner_id'] !== (int)$user['id']) {
            Response::forbidden("Unauthorized access: You may only view your own submitted project.");
        }

        Response::json(self::formatProject($project, $pdo));
    }

    public static function createProject(PDO $pdo): void {
        $user = Auth::requireAuth($pdo, ['student']);
        $userId = (int)$user['id'];

        // 1. One project per student check
        $checkExisting = $pdo->prepare("SELECT id FROM projects WHERE student_owner_id = ?");
        $checkExisting->execute([$userId]);
        if ($checkExisting->fetchColumn()) {
            Response::error("You have already submitted a project. Each student is limited to only one project submission. Please edit your existing project.", 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $title = trim($data['project_title'] ?? '');
        $shortDesc = trim($data['short_description'] ?? '');
        $probStmt = trim($data['problem_statement'] ?? '');
        $objectives = trim($data['objectives'] ?? '');
        $catId = (int)($data['category_id'] ?? 1);

        if (empty($title) || empty($shortDesc) || empty($probStmt) || empty($objectives)) {
            Response::error("Project title, summary, problem statement, and objectives are required.", 422);
        }

        // 2. Team member validation: Max 2 additional members (1 lead + 2 = 3 students total)
        $members = $data['members'] ?? [];
        if (count($members) > 2) {
            Response::error("A project team can have at most 3 students (1 student lead + up to 2 team members).", 422);
        }

        foreach ($members as $m) {
            if (!empty($m['student_id_number'])) {
                Auth::validateRollNumber($m['student_id_number']);
            }
        }

        // 3. Generate Submission Code: PRJ-YYYY-XXX
        $year = date('Y');
        $count = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $code = sprintf("PRJ-%s-%03d", $year, $count + 1);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects (
                    submission_code, student_owner_id, project_title, short_description,
                    detailed_description, problem_statement, proposed_solution, objectives,
                    category_id, project_type, academic_session, title_image_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $code,
                $userId,
                $title,
                $shortDesc,
                $data['detailed_description'] ?? $shortDesc,
                $probStmt,
                $data['proposed_solution'] ?? $shortDesc,
                $objectives,
                $catId,
                $data['project_type'] ?? 'Semester Project',
                $data['academic_session'] ?? 'Fall 2026',
                $data['title_image_url'] ?? null
            ]);
            $projectId = (int)$pdo->lastInsertId();

            // Insert Leader in members table
            $insLeader = $pdo->prepare("
                INSERT INTO project_members (project_id, student_name, student_id_number, student_email, member_role)
                VALUES (?, ?, ?, ?, 'Leader')
            ");
            $insLeader->execute([
                $projectId,
                $user['name'],
                $user['student_profile']['student_id'] ?? 'Student',
                $user['email']
            ]);

            // Insert Additional Members
            $insMember = $pdo->prepare("
                INSERT INTO project_members (project_id, student_name, student_id_number, student_email, member_role)
                VALUES (?, ?, ?, ?, 'Member')
            ");
            foreach ($members as $m) {
                if (!empty($m['student_name'])) {
                    $insMember->execute([
                        $projectId,
                        trim($m['student_name']),
                        strtoupper(trim($m['student_id_number'] ?? '')),
                        trim($m['student_email'] ?? '')
                    ]);
                }
            }

            // Insert Technologies
            self::saveTechnologies($pdo, $projectId, $data);

            // Create initial Project Review record
            $insReview = $pdo->prepare("
                INSERT INTO project_reviews (project_id, title_status, review_status)
                VALUES (?, 'Pending Approval', 'Submitted')
            ");
            $insReview->execute([$projectId]);

            $pdo->commit();

            $fetch = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $fetch->execute([$projectId]);
            Response::json(self::formatProject($fetch->fetch(), $pdo));
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error("Project creation failed: " . $e->getMessage(), 500);
        }
    }

    public static function updateProject(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['student']);
        $userId = (int)$user['id'];

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND student_owner_id = ?");
        $stmt->execute([$id, $userId]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found or not owned by you.");
        }

        if ((bool)$project['is_locked']) {
            Response::error("Cannot edit a locked or evaluated project.", 400);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate team member limit
        $members = $data['members'] ?? [];
        if (count($members) > 2) {
            Response::error("A project team can have at most 3 students (1 student lead + up to 2 team members).", 422);
        }

        foreach ($members as $m) {
            if (!empty($m['student_id_number'])) {
                Auth::validateRollNumber($m['student_id_number']);
            }
        }

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("
                UPDATE projects SET
                    project_title = ?,
                    short_description = ?,
                    detailed_description = ?,
                    problem_statement = ?,
                    proposed_solution = ?,
                    objectives = ?,
                    category_id = ?,
                    academic_session = ?,
                    title_image_url = COALESCE(?, title_image_url),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $upd->execute([
                trim($data['project_title'] ?? $project['project_title']),
                trim($data['short_description'] ?? $project['short_description']),
                trim($data['detailed_description'] ?? $project['detailed_description']),
                trim($data['problem_statement'] ?? $project['problem_statement']),
                trim($data['proposed_solution'] ?? $project['proposed_solution']),
                trim($data['objectives'] ?? $project['objectives']),
                (int)($data['category_id'] ?? $project['category_id']),
                trim($data['academic_session'] ?? $project['academic_session']),
                $data['title_image_url'] ?? null,
                $id
            ]);

            // Refresh technologies
            self::saveTechnologies($pdo, $id, $data);

            // Refresh team members (Keep leader, replace members)
            $delMembers = $pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND member_role != 'Leader'");
            $delMembers->execute([$id]);

            $insMember = $pdo->prepare("
                INSERT INTO project_members (project_id, student_name, student_id_number, student_email, member_role)
                VALUES (?, ?, ?, ?, 'Member')
            ");
            foreach ($members as $m) {
                if (!empty($m['student_name'])) {
                    $insMember->execute([
                        $id,
                        trim($m['student_name']),
                        strtoupper(trim($m['student_id_number'] ?? '')),
                        trim($m['student_email'] ?? '')
                    ]);
                }
            }

            $pdo->commit();

            $fetch = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $fetch->execute([$id]);
            Response::json(self::formatProject($fetch->fetch(), $pdo));
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error("Project update failed: " . $e->getMessage(), 500);
        }
    }

    private static function saveTechnologies(PDO $pdo, int $projectId, array $data): void {
        $delTech = $pdo->prepare("DELETE FROM project_technologies WHERE project_id = ?");
        $delTech->execute([$projectId]);

        $insPt = $pdo->prepare("INSERT INTO project_technologies (project_id, technology_id) VALUES (?, ?)");

        // 1. Direct IDs
        if (!empty($data['technology_ids']) && is_array($data['technology_ids'])) {
            foreach ($data['technology_ids'] as $tid) {
                $insPt->execute([$projectId, (int)$tid]);
            }
        }

        // 2. Custom string technologies
        $custom = $data['custom_technologies'] ?? [];
        if (is_string($custom)) {
            $custom = array_filter(array_map('trim', explode(',', $custom)));
        }

        $findTech = $pdo->prepare("SELECT id FROM technologies WHERE name = ?");
        $createTech = $pdo->prepare("INSERT INTO technologies (name, category) VALUES (?, 'Custom')");

        foreach ($custom as $techName) {
            $techName = trim($techName);
            if ($techName === '') continue;

            $findTech->execute([$techName]);
            $tid = $findTech->fetchColumn();
            if (!$tid) {
                $createTech->execute([$techName]);
                $tid = (int)$pdo->lastInsertId();
            }

            // Link
            $checkLinked = $pdo->prepare("SELECT id FROM project_technologies WHERE project_id = ? AND technology_id = ?");
            $checkLinked->execute([$projectId, $tid]);
            if (!$checkLinked->fetchColumn()) {
                $insPt->execute([$projectId, $tid]);
            }
        }
    }

    public static function uploadSrs(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['student']);
        self::handleFileUpload($pdo, $id, (int)$user['id'], 'srs', ['pdf', 'docx', 'doc']);
    }

    public static function uploadCode(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['student']);
        self::handleFileUpload($pdo, $id, (int)$user['id'], 'source_code', ['zip', 'rar', '7z', 'tar', 'gz']);
    }

    private static function handleFileUpload(PDO $pdo, int $projectId, int $userId, string $fileType, array $allowedExts): void {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND student_owner_id = ?");
        $stmt->execute([$projectId, $userId]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found.");
        }

        if ((bool)$project['is_locked']) {
            Response::error("Cannot upload files to a locked project.", 400);
        }

        if (empty($_FILES['file'])) {
            Response::error("No file was received in request payload.", 400);
        }

        $uploadErr = $_FILES['file']['error'];
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errMap = [
                UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the server maximum upload limit (upload_max_filesize).",
                UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive.",
                UPLOAD_ERR_PARTIAL    => "The file was only partially uploaded.",
                UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "Server is missing temporary upload directory.",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk on server.",
                UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
            ];
            $msg = $errMap[$uploadErr] ?? "Upload error code: $uploadErr";
            Response::error("File upload failed: $msg", 400);
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {
            Response::error("Invalid file format. Allowed extensions: " . implode(', ', $allowedExts), 422);
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $targetDir = $fileType === 'srs' ? $config['storage']['srs'] : $config['storage']['code'];

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        @chmod($targetDir, 0777);

        $storedName = sprintf("%s_%s_%s.%s", $project['submission_code'], $fileType, time(), $ext);
        $targetPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error("Failed to save uploaded file to storage.", 500);
        }

        // Delete previous file of same type
        $delOld = $pdo->prepare("SELECT storage_path FROM project_files WHERE project_id = ? AND file_type = ?");
        $delOld->execute([$projectId, $fileType]);
        while ($oldPath = $delOld->fetchColumn()) {
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
        $delRecord = $pdo->prepare("DELETE FROM project_files WHERE project_id = ? AND file_type = ?");
        $delRecord->execute([$projectId, $fileType]);

        // Insert new file record
        $ins = $pdo->prepare("
            INSERT INTO project_files (project_id, uploaded_by, file_type, original_filename, stored_filename, storage_path, mime_type, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $projectId,
            $userId,
            $fileType,
            $file['name'],
            $storedName,
            $targetPath,
            $file['type'] ?: 'application/octet-stream',
            $file['size']
        ]);

        Response::json([
            'message'           => 'File uploaded successfully.',
            'original_filename' => $file['name'],
            'file_size'         => $file['size']
        ]);
    }

    public static function getFeedback(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo);
        $stmt = $pdo->prepare("SELECT * FROM project_reviews WHERE project_id = ?");
        $stmt->execute([$id]);
        $review = $stmt->fetch();
        Response::json($review ?: ['title_status' => 'Pending Approval', 'review_status' => 'Submitted', 'teacher_comments' => null]);
    }

    public static function getGrade(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo);
        $stmt = $pdo->prepare("SELECT * FROM evaluations WHERE project_id = ?");
        $stmt->execute([$id]);
        $eval = $stmt->fetch();
        Response::json($eval ?: null);
    }

    public static function getDashboard(PDO $pdo): void {
        $user = Auth::requireAuth($pdo, ['student']);
        $userId = (int)$user['id'];

        $total = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE student_owner_id = ?");
        $total->execute([$userId]);

        $pending = $pdo->prepare("
            SELECT COUNT(*) FROM projects p
            JOIN project_reviews pr ON pr.project_id = p.id
            WHERE p.student_owner_id = ? AND pr.review_status IN ('Submitted', 'Under Review')
        ");
        $pending->execute([$userId]);

        $approved = $pdo->prepare("
            SELECT COUNT(*) FROM projects p
            JOIN project_reviews pr ON pr.project_id = p.id
            WHERE p.student_owner_id = ? AND pr.review_status = 'Approved'
        ");
        $approved->execute([$userId]);

        $completed = $pdo->prepare("
            SELECT COUNT(*) FROM projects p
            JOIN evaluations e ON e.project_id = p.id
            WHERE p.student_owner_id = ? AND e.evaluation_status = 'Published'
        ");
        $completed->execute([$userId]);

        Response::json([
            'total_projects'        => (int)$total->fetchColumn(),
            'pending_reviews'       => (int)$pending->fetchColumn(),
            'approved_projects'     => (int)$approved->fetchColumn(),
            'completed_evaluations' => (int)$completed->fetchColumn(),
        ]);
    }
}
