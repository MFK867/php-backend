<?php
// =======================================================
// Academic Metadata Controller (Departments, Categories, Technologies)
// =======================================================

class MetadataController {
    public static function getDepartments(PDO $pdo): void {
        $stmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC");
        Response::json($stmt->fetchAll());
    }

    public static function getCategories(PDO $pdo): void {
        $stmt = $pdo->query("SELECT id, name, description FROM project_categories ORDER BY name ASC");
        Response::json($stmt->fetchAll());
    }

    public static function getTechnologies(PDO $pdo): void {
        $stmt = $pdo->query("SELECT id, name, category FROM technologies ORDER BY category ASC, name ASC");
        Response::json($stmt->fetchAll());
    }
}
