<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class AdminController
{
    public function users(): Response
    {
        $this->requireAdmin();
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC');
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return Response::json($users);
    }
    
    public function sites(): Response
    {
        $this->requireAdmin();
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT s.id, s.title, s.slug, s.domain, s.created_at, u.name as owner_name, u.email as owner_email
            FROM sites s
            JOIN users u ON u.id = s.user_id
            ORDER BY s.id DESC
        ');
        $stmt->execute();
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return Response::json($sites);
    }
    
    public function pages(): Response
    {
        $this->requireAdmin();
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT p.id, p.title, p.slug, p.published, p.version, p.created_at,
                   s.title as site_title, s.slug as site_slug,
                   u.name as owner_name
            FROM pages p
            JOIN sites s ON s.id = p.site_id
            LEFT JOIN users u ON u.id = p.created_by
            ORDER BY p.id DESC
        ');
        $stmt->execute();
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return Response::json($pages);
    }
    
    public function impersonate(string $userId): Response
    {
        $this->requireAdmin();
        $adminId = (int)$_SESSION['user_id'];
        $targetUserId = (int)$userId;
        
        $pdo = Database::connection();
        
        // Target user var mı kontrol et
        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetUserId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$targetUser) {
            return Response::json(['error' => 'User not found'], 404);
        }
        
        // Audit log
        $stmt = $pdo->prepare('
            INSERT INTO audits (user_id, event, details, created_at)
            VALUES (:user_id, :event, :details, NOW())
        ');
        $stmt->execute([
            'user_id' => $adminId,
            'event' => 'impersonate_start',
            'details' => json_encode([
                'target_user_id' => $targetUserId,
                'target_email' => $targetUser['email'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]),
        ]);
        
        // Orijinal admin bilgisini sakla
        $_SESSION['original_admin_id'] = $adminId;
        $_SESSION['original_role'] = $_SESSION['role'];
        
        // Target user'a geç
        $_SESSION['user_id'] = $targetUserId;
        $_SESSION['role'] = $targetUser['role'];
        
        return Response::json([
            'message' => 'Impersonating user',
            'user' => [
                'id' => $targetUser['id'],
                'name' => $targetUser['name'],
                'email' => $targetUser['email'],
            ],
        ]);
    }
    
    public function stopImpersonate(): Response
    {
        if (empty($_SESSION['original_admin_id'])) {
            return Response::json(['error' => 'Not impersonating'], 400);
        }
        
        $adminId = $_SESSION['original_admin_id'];
        $originalRole = $_SESSION['original_role'] ?? 'admin';
        
        // Audit log
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO audits (user_id, event, details, created_at)
            VALUES (:user_id, :event, :details, NOW())
        ');
        $stmt->execute([
            'user_id' => $adminId,
            'event' => 'impersonate_stop',
            'details' => json_encode([
                'was_user_id' => $_SESSION['user_id'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]),
        ]);
        
        // Orijinal admin'e geri dön
        $_SESSION['user_id'] = $adminId;
        $_SESSION['role'] = $originalRole;
        unset($_SESSION['original_admin_id']);
        unset($_SESSION['original_role']);
        
        return Response::json(['message' => 'Stopped impersonating']);
    }
    
    public function audits(): Response
    {
        $this->requireAdmin();
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            SELECT a.id, a.event, a.details, a.created_at,
                   u.name as user_name, u.email as user_email
            FROM audits a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.id DESC
            LIMIT 100
        ');
        $stmt->execute();
        $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Details JSON decode
        foreach ($audits as &$audit) {
            $audit['details'] = $audit['details'] ? json_decode($audit['details'], true) : null;
        }
        
        return Response::json($audits);
    }
    
    public function status(): Response
    {
        $this->requireAdmin();
        
        $isImpersonating = !empty($_SESSION['original_admin_id']);
        
        return Response::json([
            'is_impersonating' => $isImpersonating,
            'current_user_id' => (int)$_SESSION['user_id'],
            'original_admin_id' => $isImpersonating ? (int)$_SESSION['original_admin_id'] : null,
        ]);
    }
    
    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Unauthorized'], 401)->send();
            exit;
        }
        
        // Impersonate durumunda original_admin_id varsa admin sayılır
        if (!empty($_SESSION['original_admin_id'])) {
            return;
        }
        
        if (($_SESSION['role'] ?? 'user') !== 'admin') {
            Response::json(['error' => 'Forbidden - Admin only'], 403)->send();
            exit;
        }
    }
}

