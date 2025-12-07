<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class SiteController
{
    public function index(): Response
    {
        $this->requireAuth();

        $userId = (int)$_SESSION['user_id'];
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT id, title, slug, domain FROM sites WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(['user_id' => $userId]);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json($sites);
    }

    public function store(): Response
    {
        $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($input['title'] ?? '');
        $slug = trim($input['slug'] ?? '');

        if ($title === '' || $slug === '') {
            return Response::json(['error' => 'Title and slug are required'], 422);
        }

        $userId = (int)$_SESSION['user_id'];
        $pdo = Database::connection();

        // slug unique per system
        $stmt = $pdo->prepare('SELECT id FROM sites WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        if ($stmt->fetch()) {
            return Response::json(['error' => 'Slug already taken'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO sites (user_id, title, slug, created_at) VALUES (:user_id, :title, :slug, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'slug' => $slug,
        ]);

        $id = (int)$pdo->lastInsertId();

        return Response::json([
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
        ], 201);
    }
    
    public function update(string $siteId): Response
    {
        $this->requireAuth();
        
        $userId = (int)$_SESSION['user_id'];
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = trim($input['domain'] ?? '');
        
        $pdo = Database::connection();
        
        // Site sahibi kontrolü
        $stmt = $pdo->prepare('SELECT id FROM sites WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => (int)$siteId, 'user_id' => $userId]);
        if (!$stmt->fetch()) {
            return Response::json(['error' => 'Site not found'], 404);
        }
        
        // Domain unique kontrolü (kendi domain'i hariç)
        if ($domain !== '') {
            $stmt = $pdo->prepare('SELECT id FROM sites WHERE domain = :domain AND id != :id LIMIT 1');
            $stmt->execute(['domain' => $domain, 'id' => (int)$siteId]);
            if ($stmt->fetch()) {
                return Response::json(['error' => 'Domain already taken'], 422);
            }
        }
        
        $stmt = $pdo->prepare('UPDATE sites SET domain = :domain WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'domain' => $domain === '' ? null : $domain,
            'id' => (int)$siteId,
            'user_id' => $userId,
        ]);
        
        return Response::json(['message' => 'Domain updated']);
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Unauthorized'], 401)->send();
            exit;
        }
    }
}


