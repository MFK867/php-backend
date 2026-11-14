<?php
// =======================================================
// Front Controller & REST API Router
// =======================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/src/Response.php';

// Handle CORS globally before any route processing or DB connection
Response::handleCors();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Controllers/AuthController.php';
require_once __DIR__ . '/src/Controllers/StudentController.php';
require_once __DIR__ . '/src/Controllers/TeacherController.php';
require_once __DIR__ . '/src/Controllers/ShowcaseController.php';
require_once __DIR__ . '/src/Controllers/FileController.php';
require_once __DIR__ . '/src/Controllers/MetadataController.php';
require_once __DIR__ . '/src/Controllers/NotificationController.php';

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    Response::error("Database connection failed: " . $e->getMessage(), 500);
}

// Extract Method
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
}

// Extract Path
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

// Strip script name or subfolder if deployed in a subdirectory (e.g. /api/)
$scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptName !== '/' && $scriptName !== '\\' && strpos($uri, $scriptName) === 0) {
    $uri = substr($uri, strlen($scriptName));
}

// Normalize path (ensure leading slash, strip trailing slash)
$path = '/' . trim($uri, '/');

// Strip optional leading /api prefix so /api/login and /login both match smoothly
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
    if ($path === '' || $path === false) {
        $path = '/';
    }
}
$path = '/' . trim($path, '/');

// -------------------------------------------------------
// ROUTER DEFINITIONS
// -------------------------------------------------------

// Health Check
if ($path === '/' || $path === '/health') {
    Response::json([
        'status'  => 'healthy',
        'backend' => 'PHP ' . PHP_VERSION,
        'message' => 'Academic Project Submission & Evaluation Portal API is online'
    ]);
}

// 1. Authentication Routes
if ($method === 'GET' && ($path === '/login' || $path === '/login/student' || $path === '/login/teacher')) {
    Response::json([
        'status'          => 'online',
        'endpoint'        => '/login',
        'accepted_method' => 'POST',
        'content_type'    => 'application/json',
        'payload_format'  => ['email' => 'user@tuf.edu.pk', 'password' => 'YourPassword'],
        'message'         => 'Authentication endpoint is online. Submit credentials via HTTP POST.'
    ]);
}
if ($method === 'POST' && ($path === '/login' || $path === '/login/student' || $path === '/login/teacher')) {
    AuthController::login($pdo);
}
if ($method === 'POST' && $path === '/register/student') {
    AuthController::registerStudent($pdo);
}
if ($method === 'GET' && $path === '/user') {
    AuthController::me($pdo);
}
if ($method === 'POST' && $path === '/logout') {
    AuthController::logout();
}

// 2. Metadata Routes
if ($method === 'GET' && $path === '/departments') {
    MetadataController::getDepartments($pdo);
}
if ($method === 'GET' && $path === '/categories') {
    MetadataController::getCategories($pdo);
}
if ($method === 'GET' && $path === '/technologies') {
    MetadataController::getTechnologies($pdo);
}

// 3. Student Routes
if ($method === 'GET' && $path === '/student/dashboard') {
    StudentController::getDashboard($pdo);
}
if ($method === 'GET' && $path === '/student/my-project') {
    StudentController::getMyProject($pdo);
}
if ($method === 'GET' && $path === '/student/projects') {
    StudentController::getProjects($pdo);
}
if ($method === 'POST' && $path === '/student/projects') {
    StudentController::createProject($pdo);
}
if ($method === 'GET' && preg_match('#^/student/projects/(\d+)$#', $path, $m)) {
    StudentController::getProjectDetail($pdo, (int)$m[1]);
}
if ($method === 'PUT' && preg_match('#^/student/projects/(\d+)$#', $path, $m)) {
    StudentController::updateProject($pdo, (int)$m[1]);
}
if ($method === 'POST' && preg_match('#^/student/projects/(\d+)/upload-srs$#', $path, $m)) {
    StudentController::uploadSrs($pdo, (int)$m[1]);
}
if ($method === 'POST' && preg_match('#^/student/projects/(\d+)/upload-code$#', $path, $m)) {
    StudentController::uploadCode($pdo, (int)$m[1]);
}
if ($method === 'GET' && preg_match('#^/student/projects/(\d+)/feedback$#', $path, $m)) {
    StudentController::getFeedback($pdo, (int)$m[1]);
}
if ($method === 'GET' && preg_match('#^/student/projects/(\d+)/grade$#', $path, $m)) {
    StudentController::getGrade($pdo, (int)$m[1]);
}

// 4. File Routes
if ($method === 'POST' && $path === '/files/upload-title-image') {
    FileController::uploadTitleImage($pdo);
}
if ($method === 'GET' && (preg_match('#^/files/(\d+)/download$#', $path, $m) || preg_match('#^/files/download/(\d+)$#', $path, $m))) {
    FileController::downloadFile($pdo, (int)$m[1]);
}
if ($method === 'GET' && preg_match('#^/files/view-image/([^/]+)$#', $path, $m)) {
    FileController::viewImage($m[1]);
}

// 5. Teacher / Admin Routes
if ($method === 'GET' && $path === '/teacher/dashboard') {
    TeacherController::getDashboard($pdo);
}
if ($method === 'GET' && $path === '/teacher/submissions') {
    TeacherController::getSubmissions($pdo);
}
if ($method === 'GET' && preg_match('#^/teacher/submissions/(\d+)$#', $path, $m)) {
    TeacherController::getSubmissionDetail($pdo, (int)$m[1]);
}
if ($method === 'POST' && preg_match('#^/teacher/projects/(\d+)/title-decision$#', $path, $m)) {
    TeacherController::decideTitle($pdo, (int)$m[1]);
}
if ($method === 'POST' && preg_match('#^/teacher/projects/(\d+)/review-status$#', $path, $m)) {
    TeacherController::updateReviewStatus($pdo, (int)$m[1]);
}
if ($method === 'GET' && $path === '/teacher/evaluation-criteria') {
    TeacherController::getEvaluationCriteria($pdo);
}
if ($method === 'POST' && preg_match('#^/teacher/projects/(\d+)/evaluate$#', $path, $m)) {
    TeacherController::evaluateProject($pdo, (int)$m[1]);
}
if ($method === 'GET' && $path === '/teacher/students') {
    TeacherController::getStudents($pdo);
}

// 6. Showcase Directory Routes
if ($method === 'GET' && $path === '/showcase/projects') {
    ShowcaseController::getProjects($pdo);
}
if ($method === 'GET' && preg_match('#^/showcase/projects/(\d+)$#', $path, $m)) {
    ShowcaseController::getProjectDetail($pdo, (int)$m[1]);
}

// 7. Notification Routes
if ($method === 'GET' && $path === '/notifications') {
    NotificationController::getNotifications($pdo);
}
if ($method === 'GET' && $path === '/notifications/unread-count') {
    NotificationController::getUnreadCount($pdo);
}
if ($method === 'POST' && preg_match('#^/notifications/(\d+)/read$#', $path, $m)) {
    NotificationController::markRead($pdo, (int)$m[1]);
}
if ($method === 'POST' && $path === '/notifications/read-all') {
    NotificationController::markAllRead($pdo);
}

// If no route matched
Response::notFound("API endpoint not found: [$method] $path");
