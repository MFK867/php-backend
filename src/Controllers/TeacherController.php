<?php
// =======================================================
// Teacher & Admin Evaluation Controller
// =======================================================

class TeacherController {
    public static function getDashboard(PDO $pdo): void {
        Auth::requireAuth($pdo, ['teacher', 'admin']);

        $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
        $totalProjects = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

        $pendingTitles = (int)$pdo->query("
            SELECT COUNT(*) FROM projects p
            LEFT JOIN project_reviews pr ON pr.project_id = p.id
            WHERE pr.title_status = 'Pending Approval' OR pr.title_status IS NULL
        ")->fetchColumn();

        $approvedTitles = (int)$pdo->query("
            SELECT COUNT(*) FROM project_reviews WHERE title_status = 'Approved'
        ")->fetchColumn();

        $pendingEvals = (int)$pdo->query("
            SELECT COUNT(*) FROM projects p
            LEFT JOIN evaluations e ON e.project_id = p.id
            WHERE e.id IS NULL OR e.evaluation_status != 'Published'
        ")->fetchColumn();

        $completedEvals = (int)$pdo->query("
            SELECT COUNT(*) FROM evaluations WHERE evaluation_status = 'Published'
        ")->fetchColumn();

        Response::json([
            'total_students'           => $totalStudents,
            'total_projects'           => $totalProjects,
            'pending_title_approvals'  => $pendingTitles,
            'approved_titles'          => $approvedTitles,
            'pending_evaluations'      => $pendingEvals,
            'completed_evaluations'    => $completedEvals,
        ]);
    }

    public static function getSubmissions(PDO $pdo): void {
        Auth::requireAuth($pdo, ['teacher', 'admin']);

        $search = trim($_GET['search'] ?? '');
        $status = strtoupper(trim($_GET['status'] ?? ''));
        $catId = isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

        $sql = "
            SELECT p.*
            FROM projects p
            JOIN users u ON u.id = p.student_owner_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN evaluations e ON e.project_id = p.id
            WHERE 1=1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (
                p.project_title LIKE ? OR
                p.submission_code LIKE ? OR
                u.name LIKE ? OR
                sp.student_id LIKE ?
            )";
            $term = "%$search%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($catId) {
            $sql .= " AND p.category_id = ?";
            $params[] = $catId;
        }

        if ($status === 'PENDING') {
            $sql .= " AND (e.id IS NULL OR e.evaluation_status != 'Published')";
        } elseif ($status === 'GRADED') {
            $sql .= " AND e.evaluation_status = 'Published'";
        }

        $sql .= " ORDER BY p.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();

        $result = [];
        foreach ($projects as $p) {
            $result[] = StudentController::formatProject($p, $pdo);
        }

        Response::json($result);
    }

    public static function getSubmissionDetail(PDO $pdo, int $id): void {
        Auth::requireAuth($pdo, ['teacher', 'admin']);

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Submission not found.");
        }

        Response::json(StudentController::formatProject($project, $pdo));
    }

    public static function decideTitle(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['teacher', 'admin']);
        $teacherId = (int)$user['id'];

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found.");
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $decision = trim($data['decision'] ?? 'Approved');
        $reason = trim($data['reason'] ?? 'Title approved by faculty evaluator.');

        $pdo->beginTransaction();
        try {
            // Update or create review
            $upd = $pdo->prepare("
                INSERT INTO project_reviews (project_id, teacher_id, title_status, reviewed_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    teacher_id = VALUES(teacher_id),
                    title_status = VALUES(title_status),
                    reviewed_at = CURRENT_TIMESTAMP
            ");
            // If SQLite, handle fallback
            try {
                $upd->execute([$id, $teacherId, $decision]);
            } catch (Exception $e) {
                $pdo->prepare("UPDATE project_reviews SET teacher_id = ?, title_status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE project_id = ?")
                    ->execute([$teacherId, $decision, $id]);
            }

            // History
            $insHist = $pdo->prepare("
                INSERT INTO title_review_history (project_id, teacher_id, previous_title, submitted_title, decision, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insHist->execute([
                $id,
                $teacherId,
                $project['project_title'],
                $project['project_title'],
                $decision,
                $reason
            ]);

            // Notification for student
            $notif = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, notification_type, related_project_id)
                VALUES (?, ?, ?, 'title_status', ?)
            ");
            $notif->execute([
                (int)$project['student_owner_id'],
                "Project Title $decision",
                "Your project title '{$project['project_title']}' has been reviewed: $decision. Comments: $reason",
                $id
            ]);

            $pdo->commit();
            Response::json(['message' => "Project title marked as '$decision'."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error("Title decision failed: " . $e->getMessage(), 500);
        }
    }

    public static function updateReviewStatus(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['teacher', 'admin']);
        $teacherId = (int)$user['id'];

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found.");
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = trim($data['review_status'] ?? 'Under Review');
        $comments = trim($data['comments'] ?? '');

        $upd = $pdo->prepare("
            UPDATE project_reviews SET
                teacher_id = ?,
                review_status = ?,
                teacher_comments = ?,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE project_id = ?
        ");
        $upd->execute([$teacherId, $status, $comments, $id]);

        Response::json(['message' => "Review workflow status updated to '$status'."]);
    }

    public static function getEvaluationCriteria(PDO $pdo): void {
        Auth::requireAuth($pdo);
        $stmt = $pdo->query("SELECT * FROM evaluation_criteria ORDER BY display_order ASC");
        Response::json($stmt->fetchAll());
    }

    public static function evaluateProject(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo, ['teacher', 'admin']);
        $teacherId = (int)$user['id'];

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Project not found.");
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $scoresList = $data['scores'] ?? [];
        $feedback = trim($data['overall_feedback'] ?? '');
        $publish = (bool)($data['publish'] ?? false);

        // Fetch criteria map for max marks
        $critStmt = $pdo->query("SELECT id, maximum_marks FROM evaluation_criteria");
        $critMap = [];
        $maxTotal = 0.0;
        foreach ($critStmt->fetchAll() as $row) {
            $critMap[(int)$row['id']] = (float)$row['maximum_marks'];
            $maxTotal += (float)$row['maximum_marks'];
        }

        $totalMarks = 0.0;
        foreach ($scoresList as $s) {
            $obtained = (float)($s['obtained_marks'] ?? 0);
            $totalMarks += $obtained;
        }

        $percentage = $maxTotal > 0 ? ($totalMarks / $maxTotal) * 100 : 0.0;

        // Determine grade
        if ($percentage >= 85) $grade = 'A+';
        elseif ($percentage >= 80) $grade = 'A';
        elseif ($percentage >= 75) $grade = 'B+';
        elseif ($percentage >= 70) $grade = 'B';
        elseif ($percentage >= 65) $grade = 'C+';
        elseif ($percentage >= 60) $grade = 'C';
        elseif ($percentage >= 50) $grade = 'D';
        else $grade = 'F';

        $evalStatus = $publish ? 'Published' : 'Evaluation in Progress';

        $pdo->beginTransaction();
        try {
            // Check existing evaluation
            $checkEval = $pdo->prepare("SELECT id FROM evaluations WHERE project_id = ?");
            $checkEval->execute([$id]);
            $evalId = $checkEval->fetchColumn();

            if (!$evalId) {
                $insEval = $pdo->prepare("
                    INSERT INTO evaluations (
                        project_id, teacher_id, total_marks, percentage, overall_grade,
                        overall_feedback, evaluation_status, published_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insEval->execute([
                    $id,
                    $teacherId,
                    $totalMarks,
                    $percentage,
                    $grade,
                    $feedback,
                    $evalStatus,
                    $publish ? date('Y-m-d H:i:s') : null
                ]);
                $evalId = (int)$pdo->lastInsertId();
            } else {
                $evalId = (int)$evalId;
                $updEval = $pdo->prepare("
                    UPDATE evaluations SET
                        teacher_id = ?,
                        total_marks = ?,
                        percentage = ?,
                        overall_grade = ?,
                        overall_feedback = ?,
                        evaluation_status = ?,
                        published_at = CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE published_at END,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updEval->execute([
                    $teacherId,
                    $totalMarks,
                    $percentage,
                    $grade,
                    $feedback,
                    $evalStatus,
                    $publish ? 1 : 0,
                    $evalId
                ]);
            }

            // Lock project if published
            if ($publish) {
                $pdo->prepare("UPDATE projects SET is_locked = 1 WHERE id = ?")->execute([$id]);
            }

            // Save individual scores
            $delScores = $pdo->prepare("DELETE FROM evaluation_scores WHERE evaluation_id = ?");
            $delScores->execute([$evalId]);

            $insScore = $pdo->prepare("
                INSERT INTO evaluation_scores (evaluation_id, criteria_id, obtained_marks, comments)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($scoresList as $s) {
                $cid = (int)($s['criteria_id'] ?? 0);
                if (isset($critMap[$cid])) {
                    $insScore->execute([
                        $evalId,
                        $cid,
                        (float)($s['obtained_marks'] ?? 0),
                        trim($s['comments'] ?? '') ?: null
                    ]);
                }
            }

            // Student notification if published
            if ($publish) {
                $notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, notification_type, related_project_id)
                    VALUES (?, 'Project Evaluation Published', ?, 'grade_published', ?)
                ");
                $notif->execute([
                    (int)$project['student_owner_id'],
                    "Your capstone has been evaluated: $totalMarks/100 marks (Grade: $grade).",
                    $id
                ]);
            }

            $pdo->commit();

            Response::json([
                'evaluation_id'     => $evalId,
                'total_marks'       => $totalMarks,
                'percentage'        => $percentage,
                'overall_grade'     => $grade,
                'evaluation_status' => $evalStatus,
                'message'           => $publish ? 'Evaluation published to student.' : 'Draft evaluation saved.'
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error("Evaluation submission failed: " . $e->getMessage(), 500);
        }
    }

    public static function getStudents(PDO $pdo): void {
        Auth::requireAuth($pdo, ['teacher', 'admin']);

        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, sp.student_id, sp.degree_program, sp.semester, sp.section, d.name AS department_name
            FROM users u
            JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN departments d ON d.id = sp.department_id
            WHERE u.role = 'student'
            ORDER BY u.name ASC
        ");
        Response::json($stmt->fetchAll());
    }
}
