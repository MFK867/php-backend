<?php
// =======================================================
// User Notifications Controller
// =======================================================

class NotificationController {
    public static function getNotifications(PDO $pdo): void {
        $user = Auth::requireAuth($pdo);
        $userId = (int)$user['id'];

        $stmt = $pdo->prepare("
            SELECT id, title, message, notification_type, related_project_id, is_read, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['is_read'] = (bool)$r['is_read'];
        }

        Response::json($rows);
    }

    public static function getUnreadCount(PDO $pdo): void {
        $user = Auth::requireAuth($pdo);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([(int)$user['id']]);
        Response::json(['unread_count' => (int)$stmt->fetchColumn()]);
    }

    public static function markRead(PDO $pdo, int $id): void {
        $user = Auth::requireAuth($pdo);
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, (int)$user['id']]);
        Response::json(['message' => 'Notification marked as read.']);
    }

    public static function markAllRead(PDO $pdo): void {
        $user = Auth::requireAuth($pdo);
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([(int)$user['id']]);
        Response::json(['message' => 'All notifications marked as read.']);
    }
}
