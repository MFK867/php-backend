<?php
// =======================================================
// Project Directory & Showcase Controller
// =======================================================

class ShowcaseController {
    public static function getProjects(PDO $pdo): void {
        Auth::requireAuth($pdo);

        $search = trim($_GET['search'] ?? '');
        $deptId = isset($_GET['department_id']) && !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

        $sql = "
            SELECT p.*
            FROM projects p
            JOIN users u ON u.id = p.student_owner_id
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
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

        if ($deptId) {
            $sql .= " AND sp.department_id = ?";
            $params[] = $deptId;
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

    public static function getProjectDetail(PDO $pdo, int $id): void {
        Auth::requireAuth($pdo);

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) {
            Response::notFound("Showcase project not found.");
        }

        Response::json(StudentController::formatProject($project, $pdo));
    }
}
