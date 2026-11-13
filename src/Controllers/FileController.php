<?php
// =======================================================
// File Upload & Secure Download Controller
// =======================================================

class FileController {
    public static function uploadTitleImage(PDO $pdo): void {
        Auth::requireAuth($pdo);

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error("No image file provided or upload error occurred.", 400);
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            Response::error("Invalid image format. Allowed: PNG, JPG, JPEG, WEBP", 422);
        }

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $targetDir = $config['storage']['images'];

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $filename = sprintf("img_%s_%s.%s", time(), bin2hex(random_bytes(4)), $ext);
        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            Response::error("Failed to save image to server storage.", 500);
        }

        // Return relative URL that the frontend can display
        $publicUrl = '/api/files/view-image/' . $filename;

        Response::json([
            'message' => 'Image uploaded successfully.',
            'url'     => $publicUrl,
        ]);
    }

    public static function viewImage(string $filename): void {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $safeName = basename($filename);
        $filePath = $config['storage']['images'] . '/' . $safeName;

        if (!file_exists($filePath)) {
            Response::notFound("Image file not found.");
        }

        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default=> 'image/jpeg',
        };

        Response::handleCors();
        header("Content-Type: $mime");
        header("Content-Length: " . filesize($filePath));
        header("Cache-Control: public, max-age=86400");
        readfile($filePath);
        exit();
    }

    public static function downloadFile(PDO $pdo, int $fileId): void {
        Auth::requireAuth($pdo);

        $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            Response::notFound("File record not found in system.");
        }

        $filePath = $file['storage_path'];
        if (!file_exists($filePath)) {
            Response::notFound("File not found on server storage.");
        }

        Response::handleCors();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename($file['original_filename']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit();
    }
}
