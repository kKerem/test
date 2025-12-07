<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class PageController
{
    public function index(string $siteId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $siteId = (int)$siteId;

        $pdo = Database::connection();

        // Site sahibinin mi kontrolü
        if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare('SELECT id, slug, title, published, version FROM pages WHERE site_id = :site_id ORDER BY id DESC');
        $stmt->execute(['site_id' => $siteId]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json($pages);
    }

    public function store(string $siteId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $siteId = (int)$siteId;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($input['title'] ?? '');
        $slug = trim($input['slug'] ?? '');
        $contentJson = $input['content_json'] ?? ['blocks' => [], 'meta' => ['device' => 'desktop']];

        if ($title === '' || $slug === '') {
            return Response::json(['error' => 'Title and slug are required'], 422);
        }

        $pdo = Database::connection();

        if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        // slug unique per site
        $stmt = $pdo->prepare('SELECT id FROM pages WHERE site_id = :site_id AND slug = :slug LIMIT 1');
        $stmt->execute(['site_id' => $siteId, 'slug' => $slug]);
        if ($stmt->fetch()) {
            return Response::json(['error' => 'Slug already exists for this site'], 422);
        }

        $stmt = $pdo->prepare('
            INSERT INTO pages (site_id, slug, title, content_json, published, version, created_by, created_at)
            VALUES (:site_id, :slug, :title, :content_json, 0, 1, :created_by, NOW())
        ');
        $stmt->execute([
            'site_id' => $siteId,
            'slug' => $slug,
            'title' => $title,
            'content_json' => json_encode($contentJson, JSON_UNESCAPED_UNICODE),
            'created_by' => $userId,
        ]);

        $id = (int)$pdo->lastInsertId();

        return Response::json([
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'published' => false,
            'version' => 1,
        ], 201);
    }

    public function show(string $siteId, string $pageId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $siteId = (int)$siteId;
        $pageId = (int)$pageId;

        $pdo = Database::connection();
        if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare('SELECT id, slug, title, content_json, published, version FROM pages WHERE id = :id AND site_id = :site_id');
        $stmt->execute(['id' => $pageId, 'site_id' => $siteId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $page['content_json'] = $page['content_json'] ? json_decode($page['content_json'], true) : null;

        return Response::json($page);
    }

    public function update(string $siteId, string $pageId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $siteId = (int)$siteId;
        $pageId = (int)$pageId;

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $pdo = Database::connection();
        if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare('SELECT id, version FROM pages WHERE id = :id AND site_id = :site_id');
        $stmt->execute(['id' => $pageId, 'site_id' => $siteId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$page) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $newVersion = (int)$page['version'] + 1;

        $title = isset($input['title']) ? trim($input['title']) : null;
        $contentJson = $input['content_json'] ?? null;

        $stmt = $pdo->prepare('
            UPDATE pages
            SET title = COALESCE(:title, title),
                content_json = COALESCE(:content_json, content_json),
                version = :version,
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'title' => $title,
            'content_json' => $contentJson ? json_encode($contentJson, JSON_UNESCAPED_UNICODE) : null,
            'version' => $newVersion,
            'id' => $pageId,
        ]);

        return Response::json(['message' => 'Updated', 'version' => $newVersion]);
    }

    public function publish(string $siteId, string $pageId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $siteId = (int)$siteId;
        $pageId = (int)$pageId;

        $pdo = Database::connection();
        if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare('
            UPDATE pages
            SET published = 1,
                updated_at = NOW()
            WHERE id = :id AND site_id = :site_id
        ');
        $stmt->execute(['id' => $pageId, 'site_id' => $siteId]);

        if ($stmt->rowCount() === 0) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['message' => 'Published']);
    }

    public function createPreviewToken(string $siteId, string $pageId): Response
    {
        try {
            $this->requireAuth();
            $userId = (int)$_SESSION['user_id'];
            $siteId = (int)$siteId;
            $pageId = (int)$pageId;

            $pdo = Database::connection();
            if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
                return Response::json(['error' => 'Forbidden'], 403);
            }

            // Tablo yoksa oluştur
            $this->ensurePreviewTokensTableExists($pdo);

            // Geçici token oluştur (24 saat geçerli)
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 saat

            // Token'ı veritabanına kaydet
            $stmt = $pdo->prepare('
                INSERT INTO page_preview_tokens (page_id, token, user_id, expires_at, created_at)
                VALUES (:page_id, :token, :user_id, :expires_at, NOW())
            ');
            $stmt->execute([
                'page_id' => $pageId,
                'token' => $token,
                'user_id' => $userId,
                'expires_at' => $expiresAt,
            ]);

            return Response::json([
                'token' => $token,
                'preview_url' => '/preview/' . $token,
                'expires_at' => $expiresAt,
            ]);
        } catch (\PDOException $e) {
            error_log('Preview token error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Database error',
                'message' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            error_log('Preview token error: ' . $e->getMessage());
            return Response::json([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function ensurePreviewTokensTableExists(PDO $pdo): void
    {
        try {
            // Tablo var mı kontrol et
            $stmt = $pdo->query("SHOW TABLES LIKE 'page_preview_tokens'");
            if ($stmt->rowCount() === 0) {
                // Tablo yoksa oluştur
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS page_preview_tokens (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        page_id BIGINT UNSIGNED NOT NULL,
                        token VARCHAR(64) NOT NULL UNIQUE,
                        user_id BIGINT UNSIGNED NOT NULL,
                        expires_at TIMESTAMP NOT NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_preview_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                        CONSTRAINT fk_preview_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_token (token),
                        INDEX idx_expires (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ');
            }
        } catch (\PDOException $e) {
            // Tablo zaten varsa veya başka bir hata varsa sessizce geç
            error_log('Preview tokens table check: ' . $e->getMessage());
        }
    }

    private function userOwnsSite(PDO $pdo, int $userId, int $siteId): bool
    {
        $stmt = $pdo->prepare('SELECT id FROM sites WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $siteId, 'user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function saveSocialSettings(string $siteId, string $pageId): Response
    {
        try {
            $this->requireAuth();
            $userId = (int)$_SESSION['user_id'];
            $siteId = (int)$siteId;
            $pageId = (int)$pageId;

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            if (empty($input)) {
                return Response::json(['error' => 'Settings data required'], 400);
            }

            $pdo = Database::connection();
            if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
                return Response::json(['error' => 'Forbidden'], 403);
            }

            // URL validasyonu
            if (!empty($input['url']) && !filter_var($input['url'], FILTER_VALIDATE_URL)) {
                return Response::json(['error' => 'Invalid URL'], 400);
            }

            // Mevcut sayfa içeriğini al
            $stmt = $pdo->prepare('SELECT content_json FROM pages WHERE id = :id AND site_id = :site_id');
            $stmt->execute(['id' => $pageId, 'site_id' => $siteId]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                return Response::json(['error' => 'Page not found'], 404);
            }

            // Content JSON'u parse et
            $content = $page['content_json'] ? json_decode($page['content_json'], true) : [];
            if (!is_array($content)) {
                $content = [];
            }

            // Sosyal medya ayarlarını ekle
            $content['social_settings'] = $input;

            // Güncelle
            $stmt = $pdo->prepare('
                UPDATE pages
                SET content_json = :content_json,
                    updated_at = NOW()
                WHERE id = :id AND site_id = :site_id
            ');
            $stmt->execute([
                'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                'id' => $pageId,
                'site_id' => $siteId,
            ]);

            return Response::json([
                'ok' => true,
                'saved_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\PDOException $e) {
            error_log('Social settings save error: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'error' => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            error_log('Social settings save error: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'error' => 'Server error'
            ], 500);
        }
    }

    public function loadSocialSettings(string $siteId, string $pageId): Response
    {
        try {
            $this->requireAuth();
            $userId = (int)$_SESSION['user_id'];
            $siteId = (int)$siteId;
            $pageId = (int)$pageId;

            $pdo = Database::connection();
            if (!$this->userOwnsSite($pdo, $userId, $siteId)) {
                return Response::json(['error' => 'Forbidden'], 403);
            }

            $stmt = $pdo->prepare('SELECT content_json FROM pages WHERE id = :id AND site_id = :site_id');
            $stmt->execute(['id' => $pageId, 'site_id' => $siteId]);
            $page = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$page) {
                return Response::json(['ok' => false, 'found' => false], 404);
            }

            $content = $page['content_json'] ? json_decode($page['content_json'], true) : [];
            $socialSettings = $content['social_settings'] ?? null;

            if (!$socialSettings) {
                return Response::json(['ok' => false, 'found' => false]);
            }

            return Response::json([
                'ok' => true,
                'settings' => $socialSettings
            ]);
        } catch (\PDOException $e) {
            error_log('Social settings load error: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'error' => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            error_log('Social settings load error: ' . $e->getMessage());
            return Response::json([
                'ok' => false,
                'error' => 'Server error'
            ], 500);
        }
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Unauthorized'], 401)->send();
            exit;
        }
    }
}


