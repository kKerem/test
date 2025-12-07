<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class MediaController
{
    public function upload(): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];

        if (empty($_FILES['file'])) {
            return Response::json(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'Upload error'], 400);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedTypes, true)) {
            return Response::json(['error' => 'Invalid file type'], 422);
        }

        $size = (int)$file['size'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($size > $maxSize) {
            return Response::json(['error' => 'File too large'], 422);
        }

        $uploadDir = __DIR__ . '/../../public/media';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(16));
        $filename = $basename . '.' . $ext;
        $targetPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return Response::json(['error' => 'Failed to move uploaded file'], 500);
        }

        $width = null;
        $height = null;
        if (str_starts_with($mime, 'image/')) {
            [$width, $height] = getimagesize($targetPath) ?: [null, null];
        }

        $publicPath = '/media/' . $filename;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('
            INSERT INTO media (user_id, path, mime, size, width, height, created_at)
            VALUES (:user_id, :path, :mime, :size, :width, :height, NOW())
        ');
        $stmt->execute([
            'user_id' => $userId,
            'path' => $publicPath,
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ]);

        $id = (int)$pdo->lastInsertId();

        return Response::json([
            'id' => $id,
            'path' => $publicPath,
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ], 201);
    }

    public function index(): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, path, mime, size, width, height, created_at FROM media WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(['user_id' => $userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json($items);
    }

    public function destroy(string $mediaId): Response
    {
        $this->requireAuth();
        $userId = (int)$_SESSION['user_id'];
        $mediaId = (int)$mediaId;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, path FROM media WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $mediaId, 'user_id' => $userId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$media) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $filePath = __DIR__ . '/../../public' . $media['path'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $stmt = $pdo->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute(['id' => $mediaId]);

        return Response::json(['message' => 'Deleted']);
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            Response::json(['error' => 'Unauthorized'], 401)->send();
            exit;
        }
    }
}


